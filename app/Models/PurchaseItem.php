<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'material_id', 'qty', 'unit_cost', 'subtotal'];

    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function material() { return $this->belongsTo(Material::class); }
}
