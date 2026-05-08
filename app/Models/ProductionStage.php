<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionStage extends Model
{
    protected $fillable = ['production_id', 'stage', 'status', 'started_at', 'finished_at', 'notes'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const STAGES = ['design', 'sample', 'cutting', 'sewing', 'qc', 'packing'];

    public function production() { return $this->belongsTo(Production::class); }
}
