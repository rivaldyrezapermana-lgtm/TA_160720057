<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionStage extends Model
{
    protected $fillable = [
        'production_id', 'phase', 'sort_order', 'stage', 'status',
        'input_qty', 'output_qty', 'started_at', 'finished_at', 'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'sort_order' => 'integer',
        'input_qty' => 'integer',
        'output_qty' => 'integer',
    ];

    public const STAGES = ['design', 'pola', 'cutting', 'sewing', 'qc_packing'];

    public const PHASES = ['common', 'sample', 'mass'];

    /** Tahap yang membawa jumlah pcs. Desain dan pola tidak menghasilkan barang. */
    public const QTY_STAGES = ['cutting', 'sewing', 'qc_packing'];

    /** Tahap yang memakai mesin. Desain dikerjakan tanpa mesin. */
    public const MACHINE_STAGES = ['pola', 'cutting', 'sewing', 'qc_packing'];

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    /** Mesin yang dipakai tahap ini; satu tahap dapat memakai beberapa mesin. */
    public function machines()
    {
        return $this->belongsToMany(ProductionMachine::class, 'production_stage_machine')
            ->withTimestamps()
            ->orderBy('kode_mesin');
    }

    /** Rincian per ukuran (hanya tahap massal pada batch berukuran). */
    public function sizeRows()
    {
        return $this->hasMany(ProductionStageSize::class)->orderBy('id');
    }

    /** Samakan total tahap dengan jumlah rincian per ukurannya. */
    public function syncTotalsFromSizes(): void
    {
        $this->input_qty = (int) $this->sizeRows->sum('input_qty');
        $this->output_qty = (int) $this->sizeRows->sum('output_qty');
    }

    public function carriesQty(): bool
    {
        return in_array($this->stage, self::QTY_STAGES, true);
    }

    public function usesMachine(): bool
    {
        return in_array($this->stage, self::MACHINE_STAGES, true);
    }

    public function label(): string
    {
        return self::stageLabel($this->stage, $this->phase);
    }

    /** Label tahap. `qc_packing` berbeda antara fase sampel dan fase massal. */
    public static function stageLabel(string $stage, ?string $phase = null): string
    {
        if ($stage === 'qc_packing') {
            return $phase === 'sample' ? 'QC Sampel' : 'QC & Packing';
        }

        return match ($stage) {
            'design' => 'Desain',
            'pola' => 'Pembuatan Pola',
            'cutting' => 'Cutting',
            'sewing' => 'Sewing',
            default => $stage,
        };
    }
}
