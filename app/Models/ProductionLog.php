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

    public const MULAI = 'mulai';

    public const HASIL = 'hasil';

    public const SELESAI_HARI = 'selesai_hari';

    public const PAUSE = 'pause';

    protected $fillable = [
        'produksi_id', 'jenis', 'production_stage_id', 'waktu', 'keterangan',
        'input_qty', 'output_qty', 'sisa_qty', 'rincian', 'user_id',
    ];

    protected $casts = [
        'waktu' => 'datetime',
        'input_qty' => 'integer',
        'output_qty' => 'integer',
        'sisa_qty' => 'integer',
        'rincian' => 'array',
    ];

    protected static function booted(): void
    {
        // Catatan tanpa jenis mengikuti aturan data lama: ada pcs berarti hasil, selain itu pause.
        static::creating(function (ProductionLog $log) {
            $log->jenis ??= ($log->production_stage_id !== null || $log->output_qty !== null) ? self::HASIL : self::PAUSE;
        });
    }

    /**
     * Pcs per ukuran pada catatan hasil. Catatan lama hanya punya teks
     * seperti "M 30, L 20", jadi teks itu dibaca kalau rincian kosong.
     *
     * @return array<string, int>
     */
    public function sizeQty(): array
    {
        if (is_array($this->rincian)) {
            return array_map('intval', $this->rincian);
        }

        preg_match_all('/([^,\s]+)\s+(\d+)/', (string) $this->keterangan, $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn ($m) => [$m[1] => (int) $m[2]])->all();
    }

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
