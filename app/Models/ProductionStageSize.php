<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sisa (input) dan output satu ukuran di satu tahap massal. */
class ProductionStageSize extends Model
{
    protected $fillable = ['production_stage_id', 'size', 'input_qty', 'output_qty'];

    protected $casts = [
        'input_qty' => 'integer',
        'output_qty' => 'integer',
    ];

    public function stage()
    {
        return $this->belongsTo(ProductionStage::class, 'production_stage_id');
    }
}
