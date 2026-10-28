<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Target dan hasil satu ukuran dalam sebuah batch produksi. */
class ProductionSize extends Model
{
    protected $fillable = ['production_id', 'size', 'planned_qty', 'actual_qty'];

    protected $casts = [
        'planned_qty' => 'integer',
        'actual_qty' => 'integer',
    ];

    public function production()
    {
        return $this->belongsTo(Production::class);
    }
}
