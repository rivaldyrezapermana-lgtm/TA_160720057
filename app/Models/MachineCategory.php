<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineCategory extends Model
{
    protected $fillable = ['name', 'code', 'stage', 'notes'];

    public function machines()
    {
        return $this->hasMany(ProductionMachine::class);
    }
}
