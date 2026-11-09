<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'sku', 'description',
        'price', 'hpp', 'stock', 'image', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'hpp' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sizes()
    {
        return $this->hasMany(ProductSize::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function productions()
    {
        return $this->hasMany(Production::class);
    }

    public function materials()
    {
        return $this->hasMany(ProductMaterial::class);
    }

    public function salesHistories()
    {
        return $this->hasMany(SalesHistory::class);
    }

    /**
     * Resep yang berlaku untuk satu ukuran: baris ukuran itu, atau baris tanpa
     * ukuran kalau ukuran itu belum punya resep sendiri. Tanpa ukuran (batch
     * lama yang tidak dirinci) memakai resep ukuran dengan biaya tertinggi.
     */
    public function recipeForSize(?string $size = null, ?Collection $lines = null): Collection
    {
        $lines ??= $this->materials;
        $generic = $lines->whereNull('size')->values();

        if ($size !== null) {
            $own = $lines->where('size', $size)->values();

            return $own->isNotEmpty() ? $own : $generic;
        }

        if ($generic->isNotEmpty()) {
            return $generic;
        }

        return $lines->groupBy('size')
            ->sortByDesc(fn (Collection $rows) => self::recipeCost($rows))
            ->first()?->values() ?? new Collection;
    }

    /**
     * HPP = Σ(qty_required × harga bahan) dari resep. Dengan ukuran: HPP ukuran
     * itu. Tanpa ukuran: HPP tertinggi, supaya peringatan harga jual di bawah
     * HPP muncul kalau ada satu saja ukuran yang rugi.
     */
    public function computeHpp(?string $size = null): float
    {
        return self::recipeCost($this->recipeForSize($size, $this->materials()->with('material')->get()));
    }

    /**
     * HPP tiap ukuran produk, urut sesuai master ukuran. Selalu dihitung dari
     * resep dan harga bahan terbaru.
     *
     * @return array<string, float>
     */
    public function hppBySize(): array
    {
        $lines = $this->materials()->with('material')->get();
        $sizes = $this->sizes()->pluck('size')
            ->merge($lines->whereNotNull('size')->pluck('size'))
            ->unique();
        $order = Size::ordered()->pluck('name')->flip();

        return $sizes
            ->sortBy(fn (string $size) => $order[$size] ?? PHP_INT_MAX)
            ->mapWithKeys(fn (string $size) => [$size => self::recipeCost($this->recipeForSize($size, $lines))])
            ->all();
    }

    private static function recipeCost(iterable $lines): float
    {
        $total = 0.0;
        foreach ($lines as $line) {
            $total += (float) $line->qty_required * (float) ($line->material->unit_cost ?? 0);
        }

        return $total;
    }

    /** Next sequential SKU: highest existing SKU-<digits> + 1, zero-padded to 4. */
    public static function nextSku(): string
    {
        $max = static::where('sku', 'like', 'SKU-%')
            ->pluck('sku')
            ->map(fn (string $sku) => preg_match('/^SKU-(\d+)$/', $sku, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return 'SKU-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
