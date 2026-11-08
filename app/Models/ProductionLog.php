<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu catatan tombol Pause: menandai posisi produksi pada suatu waktu. */
class ProductionLog extends Model
{
    protected $table = 'log_produksi';

    protected $fillable = ['produksi_id', 'waktu', 'keterangan', 'user_id'];

    protected $casts = ['waktu' => 'datetime'];

    public function production()
    {
        return $this->belongsTo(Production::class, 'produksi_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mis. "Senin, 9 November 2026 14:32". Aplikasi menyimpan waktu dalam UTC;
     * toko berada di Surabaya, jadi tampilannya memakai WIB.
     */
    public function getWaktuLabelAttribute(): string
    {
        return $this->waktu->copy()
            ->timezone('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat('l, j F Y H:i');
    }
}
