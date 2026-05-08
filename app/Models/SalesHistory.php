<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesHistory extends Model
{
    protected $fillable = ['product_id', 'year', 'month', 'demand', 'stock_end', 'produced'];

    public function product() { return $this->belongsTo(Product::class); }
}
