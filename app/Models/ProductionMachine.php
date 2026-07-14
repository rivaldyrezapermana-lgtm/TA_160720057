<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionMachine extends Model
{
    protected $fillable = ['machine_category_id', 'name', 'code', 'status', 'capacity', 'notes'];

    protected $casts = ['capacity' => 'integer'];

    public const STATUSES = ['active', 'maintenance', 'inactive'];

    public function productions()
    {
        return $this->hasMany(Production::class);
    }

    public function category()
    {
        return $this->belongsTo(MachineCategory::class, 'machine_category_id');
    }
}
