<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionMachine extends Model
{
    protected $fillable = ['machine_category_id', 'name', 'kode_mesin', 'status', 'capacity', 'notes'];

    protected $casts = ['capacity' => 'integer'];

    public const STATUSES = ['active', 'maintenance', 'inactive'];

    protected static function booted(): void
    {
        static::creating(function (ProductionMachine $machine) {
            $machine->kode_mesin ??= static::nextKode();
        });
    }

    /** Kode berikutnya setelah nomor terbesar, mis. MSN-008. Nomor mesin yang dihapus tidak dipakai ulang selama masih ada nomor di atasnya. */
    public static function nextKode(): string
    {
        $last = static::pluck('kode_mesin')
            ->map(fn (string $kode) => (int) substr($kode, strlen('MSN-')))
            ->max() ?? 0;

        return sprintf('MSN-%03d', $last + 1);
    }

    /** Mis. "MSN-001 — Mesin Jahit Juki A". */
    public function getDisplayNameAttribute(): string
    {
        return $this->kode_mesin.' — '.$this->name;
    }

    public function productions()
    {
        return $this->hasMany(Production::class);
    }

    public function category()
    {
        return $this->belongsTo(MachineCategory::class, 'machine_category_id');
    }
}
