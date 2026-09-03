<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'production_machine_id', 'code', 'planned_qty', 'actual_qty',
        'start_date', 'end_date', 'status', 'notes', 'sample_approved_at', 'sample_revision_count',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'sample_approved_at' => 'datetime',
        'sample_revision_count' => 'integer',
    ];

    public const STATUSES = ['planned', 'in_progress', 'qc', 'completed', 'cancelled'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function machine()
    {
        return $this->belongsTo(ProductionMachine::class, 'production_machine_id');
    }

    public function stages()
    {
        return $this->hasMany(ProductionStage::class);
    }

    public function productionMaterials()
    {
        return $this->hasMany(ProductionMaterial::class);
    }

    public function sampleRevisions()
    {
        return $this->hasMany(ProductionSampleRevision::class)->orderByDesc('revision_no');
    }

    /** Batch lebih dari 1 pcs wajib membuat sampel dulu. */
    public function hasSamplePhase(): bool
    {
        return (int) $this->planned_qty > 1;
    }

    public function sampleApproved(): bool
    {
        return $this->sample_approved_at !== null;
    }

    /** Pcs sampel yang memakan bahan: sampel yang disetujui plus tiap putaran revisi. */
    public function sampleUnits(): int
    {
        // Dihitung dari baris tahap yang benar-benar ada, bukan dari planned_qty.
        // Batch hasil migrasi punya target massal tapi tidak punya fase sampel —
        // kainnya memang tidak pernah dipotong untuk sampel.
        if (! $this->stages->contains('phase', 'sample')) {
            return 0;
        }

        return 1 + (int) $this->sample_revision_count;
    }

    /** Half the planned quantity, rounded up — the handoff threshold. */
    public function gateQty(): int
    {
        return (int) ceil(0.5 * (int) $this->planned_qty);
    }

    /** Progress of a stage as a percentage of planned quantity. */
    public function stageProgressPct(ProductionStage $stage): int
    {
        return $this->planned_qty > 0 ? (int) round($stage->output_qty / $this->planned_qty * 100) : 0;
    }

    private function previousStage(ProductionStage $stage): ?ProductionStage
    {
        return $this->stages->firstWhere('sort_order', (int) $stage->sort_order - 1);
    }

    private function nextStage(ProductionStage $stage): ?ProductionStage
    {
        return $this->stages->firstWhere('sort_order', (int) $stage->sort_order + 1);
    }

    /**
     * Dua jenis gate. Tahap fase massal selain yang pertama memakai ambang 50%;
     * sisanya menunggu tahap sebelumnya selesai. Pintu masuk fase massal dijaga
     * persetujuan sampel.
     */
    public function stageUnlocked(ProductionStage $stage): bool
    {
        $prev = $this->previousStage($stage);

        if ($prev === null) {
            return true;
        }

        if ($stage->phase === 'mass' && $prev->phase !== 'mass') {
            return $this->hasSamplePhase()
                ? $this->sampleApproved()
                : $prev->status === 'completed';
        }

        if ($stage->phase === 'mass') {
            return (int) $prev->output_qty >= $this->gateQty();
        }

        return $prev->status === 'completed';
    }

    /** Batas atas input tahap: target batch, dibatasi output tahap massal sebelumnya. */
    public function stageMaxInput(ProductionStage $stage): int
    {
        if (! $stage->carriesQty()) {
            return 0;
        }

        if ($stage->phase === 'sample') {
            return 1;
        }

        $prev = $this->previousStage($stage);
        $planned = (int) $this->planned_qty;

        if ($prev === null || $prev->phase !== 'mass' || ! $prev->carriesQty()) {
            return $planned;
        }

        return min($planned, (int) $prev->output_qty);
    }

    /** Batas bawah output tahap: jumlah yang sudah diterima tahap berikutnya di fase yang sama. */
    public function stageMinOutput(ProductionStage $stage): int
    {
        if (! $stage->carriesQty() || $stage->phase === 'sample') {
            return 0;
        }

        $next = $this->nextStage($stage);

        if ($next === null || $next->phase !== $stage->phase || ! $next->carriesQty()) {
            return 0;
        }

        return (int) $next->input_qty;
    }

    /**
     * Sebab sebuah tahap masih terkunci: 'sample_not_approved', 'gate_50', atau
     * 'previous_incomplete'. Cabangnya sengaja mengikuti stageUnlocked() supaya
     * pesan yang dibaca operator tidak pernah menyimpang dari aturan gate.
     */
    public function lockCause(ProductionStage $stage): string
    {
        $prev = $this->previousStage($stage);

        if ($prev === null || $stage->phase !== 'mass') {
            return 'previous_incomplete';
        }

        if ($prev->phase !== 'mass') {
            return $this->hasSamplePhase() ? 'sample_not_approved' : 'previous_incomplete';
        }

        return 'gate_50';
    }
}
