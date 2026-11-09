<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu catatan log: tombol Pause (tanpa jumlah) atau hasil harian sebuah tahap.
 * Pada hasil harian, input_qty adalah sisa tahap sebelum disimpan, output_qty
 * adalah pcs yang diselesaikan, dan sisa_qty adalah sisa sesudahnya.
 */
class ProductionLog extends Model
{
    protected $table = 'log_produksi';

    protected $fillable = [
        'produksi_id', 'production_stage_id', 'waktu', 'keterangan', 'input_qty', 'output_qty', 'sisa_qty', 'user_id',
    ];

    protected $casts = [
        'waktu' => 'datetime',
        'input_qty' => 'integer',
        'output_qty' => 'integer',
        'sisa_qty' => 'integer',
    ];

    public function stage()
    {
        return $this->belongsTo(ProductionStage::class, 'production_stage_id');
    }

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
