<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'sku', 'description',
        'price', 'stock', 'image', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category() { return $this->belongsTo(Category::class); }
    public function sizes() { return $this->hasMany(ProductSize::class); }
    public function orderItems() { return $this->hasMany(OrderItem::class); }
    public function productions() { return $this->hasMany(Production::class); }
    public function materials() { return $this->hasMany(ProductMaterial::class); }
    public function salesHistories() { return $this->hasMany(SalesHistory::class); }
}
