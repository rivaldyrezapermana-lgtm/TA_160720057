<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSize extends Model
{
    protected $fillable = ['product_id', 'size', 'price', 'chest_cm', 'length_cm', 'sleeve_cm', 'stock'];

    protected $casts = ['price' => 'decimal:2'];

    public function product() { return $this->belongsTo(Product::class); }
}
