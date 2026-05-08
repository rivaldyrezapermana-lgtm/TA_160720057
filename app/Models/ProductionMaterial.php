<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionMaterial extends Model
{
    protected $fillable = ['production_id', 'material_id', 'qty_used'];

    public function production() { return $this->belongsTo(Production::class); }
    public function material() { return $this->belongsTo(Material::class); }
}
