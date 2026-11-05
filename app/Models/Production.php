<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'production_machine_id', 'code', 'planned_qty', 'actual_qty',
        'start_date', 'end_date', 'status', 'notes', 'sample_approved_at', 'sample_revision_count',
        'sample_size', 'sample_source_production_id',
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

    /** Rincian target/hasil per ukuran, urut sesuai urutan ukuran produk. */
    public function sizes()
    {
        return $this->hasMany(ProductionSize::class)->orderBy('id');
    }

    public function sampleRevisions()
    {
        return $this->hasMany(ProductionSampleRevision::class)->orderByDesc('revision_no');
    }

    /** Sketsa desain yang diunggah di tahap Desain, urut sesuai waktu unggah. */
    public function designSketches()
    {
        return $this->hasMany(ProductionDesignSketch::class)->orderBy('id');
    }

    /**
     * Sketsa terkunci begitu tahap Desain selesai (cutting dan sewing sudah
     * bekerja dari sketsa itu) atau batch sudah ditutup.
     */
    public function designSketchesLocked(): bool
    {
        if ($this->status === 'completed') {
            return true;
        }

        return $this->stages()->where('stage', 'design')->where('status', 'completed')->exists();
    }

    /** Batch lebih dari 1 pcs wajib membuat sampel dulu. */
    public function hasSamplePhase(): bool
    {
        return (int) $this->planned_qty > 1;
    }

    /** Batch ini membuat sampelnya sendiri (bukan memakai sampel batch lain), jadi butuh ukuran sampel. */
    public function makesOwnSample(): bool
    {
        return $this->hasSamplePhase() && $this->sample_source_production_id === null;
    }

    public function sampleApproved(): bool
    {
        return $this->sample_approved_at !== null;
    }

    /** Batch asal sampel, kalau batch ini memakai sampel yang sudah ada. */
    public function sampleSource()
    {
        return $this->belongsTo(Production::class, 'sample_source_production_id');
    }

    /**
     * Batch lain dari produk yang sama yang sampelnya sudah ada: sampelnya
     * disetujui, atau batchnya sudah selesai (produk jadinya sekaligus bukti
     * sampel). Yang terbaru dipakai.
     */
    public function existingSampleBatch(): ?Production
    {
        return static::sampleBatchFor((int) $this->product_id, $this->getKey());
    }

    /** Batch terbaru produk ini yang sampelnya sudah ada (dipakai juga sebelum batch dibuat). */
    public static function sampleBatchFor(int $productId, ?int $exceptId = null): ?Production
    {
        return static::query()
            ->where('product_id', $productId)
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->where(fn ($q) => $q->whereNotNull('sample_approved_at')->orWhere('status', 'completed'))
            ->latest('id')
            ->first();
    }

    /**
     * Fase sampel batch ini masih bisa dilewati: belum disetujui, belum ada
     * tahap sampel yang dimulai, dan belum pernah direvisi.
     */
    public function canReuseSample(): bool
    {
        $sampleStages = $this->stages->where('phase', 'sample');

        return $this->hasSamplePhase()
            && ! $this->sampleApproved()
            && $sampleStages->isNotEmpty()
            && $sampleStages->every(fn (ProductionStage $s) => $s->status === 'pending')
            && (int) $this->sample_revision_count === 0;
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
            // Batch lama yang memakai sampel batch lain masih punya Pola sebagai
            // tahap sebelumnya — persetujuan saja tidak cukup, polanya harus
            // selesai dulu. Batch baru yang memakai sampel tidak punya Persiapan,
            // jadi cutting massal adalah tahap pertama dan langsung terbuka.
            return $this->hasSamplePhase()
                ? $this->sampleApproved() && ($prev->phase === 'sample' || $prev->status === 'completed')
                : $prev->status === 'completed';
        }

        if ($stage->phase === 'mass') {
            return (int) $prev->output_qty >= $this->gateQty();
        }

        return $prev->status === 'completed';
    }

    /**
     * Tahap massal berikutnya yang menerima output tahap ini, atau null kalau
     * tahap ini yang terakhir (QC & Packing) atau bukan tahap massal.
     */
    public function nextQtyStage(ProductionStage $stage): ?ProductionStage
    {
        if ($stage->phase !== 'mass' || ! $stage->carriesQty()) {
            return null;
        }

        $next = $this->nextStage($stage);

        return ($next !== null && $next->phase === 'mass' && $next->carriesQty()) ? $next : null;
    }

    /**
     * Jumlah pcs yang sudah diterima tahap. Di fase massal `input_qty` adalah
     * sisa yang belum dikerjakan dan `output_qty` adalah total yang sudah
     * diteruskan, jadi yang diterima = sisa + output.
     */
    public function stageReceived(ProductionStage $stage): int
    {
        if (! $stage->carriesQty()) {
            return 0;
        }

        if ($stage->phase === 'sample') {
            return 1;
        }

        return (int) $stage->input_qty + (int) $stage->output_qty;
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
            if ($prev->phase !== 'sample' && $prev->status !== 'completed') {
                return 'previous_incomplete';
            }

            return $this->hasSamplePhase() ? 'sample_not_approved' : 'previous_incomplete';
        }

        return 'gate_50';
    }
}
