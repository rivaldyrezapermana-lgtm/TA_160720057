<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = ['name', 'code', 'unit', 'stock', 'min_stock', 'unit_cost'];

    protected $casts = ['unit_cost' => 'decimal:2'];

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function productMaterials()
    {
        return $this->hasMany(ProductMaterial::class);
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock <= (float) $this->min_stock;
    }

    /**
     * Terima barang dari pembelian: stok bertambah dan harga menjadi rata-rata
     * bergerak. Contoh: stok 10 @ Rp 40.000 + beli 10 @ Rp 50.000 → stok 20 @ Rp 45.000.
     * Stok yang kosong atau minus tidak punya nilai, jadi harganya mengikuti pembelian.
     */
    public function receivePurchase(int $qty, float $unitCost): void
    {
        $oldStock = max(0.0, (float) $this->stock);
        $oldValue = $oldStock * (float) $this->unit_cost;
        $newQty = $oldStock + $qty;

        $this->stock = (float) $this->stock + $qty;
        $this->unit_cost = $newQty > 0 ? round(($oldValue + $qty * $unitCost) / $newQty, 2) : $unitCost;
        $this->save();

        $this->refreshProductHpp();
    }

    /** Samakan catatan HPP tiap produk yang resepnya memakai bahan ini. */
    public function refreshProductHpp(): void
    {
        Product::whereHas('materials', fn ($q) => $q->where('material_id', $this->id))
            ->get()
            ->each(fn (Product $product) => $product->forceFill(['hpp' => $product->computeHpp()])->save());
    }

    /** Jumlah bahan tanpa nol di belakang koma, mis. 12 atau 12,5. */
    public static function formatQty(float|string|int|null $qty): string
    {
        $formatted = number_format((float) $qty, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    public function getStockLabelAttribute(): string
    {
        return self::formatQty($this->stock);
    }
}
