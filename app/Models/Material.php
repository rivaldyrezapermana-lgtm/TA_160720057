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
