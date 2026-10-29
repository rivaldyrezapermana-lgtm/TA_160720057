<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionDesignSketch extends Model
{
    /** Sketsa hanya ditambah atau dihapus, tidak pernah diubah. */
    public const UPDATED_AT = null;

    protected $fillable = ['production_id', 'path', 'original_name', 'user_id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
