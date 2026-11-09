<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMaterial extends Model
{
    /** `size` kosong berarti baris ini berlaku untuk ukuran yang tidak punya resep sendiri. */
    protected $fillable = ['product_id', 'material_id', 'size', 'qty_required'];

    protected $casts = ['qty_required' => 'decimal:2'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
