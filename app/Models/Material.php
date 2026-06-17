<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = ['name', 'code', 'unit', 'stock', 'min_stock', 'unit_cost'];

    protected $casts = ['unit_cost' => 'decimal:2'];

    public function purchaseItems() { return $this->hasMany(PurchaseItem::class); }
    public function productMaterials() { return $this->hasMany(ProductMaterial::class); }

    public function isLowStock(): bool { return $this->stock <= $this->min_stock; }
}
