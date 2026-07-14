<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionStage extends Model
{
    protected $fillable = ['production_id', 'stage', 'status', 'input_qty', 'output_qty', 'production_machine_id', 'started_at', 'finished_at', 'notes'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'input_qty' => 'integer',
        'output_qty' => 'integer',
    ];

    public const STAGES = ['design', 'sample', 'cutting', 'sewing', 'qc', 'packing'];

    public function production() { return $this->belongsTo(Production::class); }

    public function machine() { return $this->belongsTo(ProductionMachine::class, 'production_machine_id'); }
}
