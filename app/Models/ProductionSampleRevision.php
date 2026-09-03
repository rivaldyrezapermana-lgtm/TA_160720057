<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionSampleRevision extends Model
{
    /** Baris riwayat tidak pernah diubah, jadi tidak butuh updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = ['production_id', 'revision_no', 'notes', 'user_id'];

    protected $casts = [
        'revision_no' => 'integer',
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
