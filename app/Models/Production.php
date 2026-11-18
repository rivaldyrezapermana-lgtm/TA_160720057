<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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

    /** Catatan Mulai, hasil Simpan, Selesai Hari Ini, dan Pause, urut dari yang paling lama. */
    public function logs()
    {
        return $this->hasMany(ProductionLog::class, 'produksi_id')->orderBy('waktu')->orderBy('id');
    }

    /**
     * Status hari produksi: 'berjalan' setelah Mulai, 'selesai' setelah Selesai
     * Hari Ini pada hari yang sama, atau 'belum'.
     *
     * @return array{state: string, log: ?ProductionLog}
     */
    public function dayStatus(): array
    {
        $logs = $this->logs->values();
        $startAt = $logs->keys()->filter(fn ($i) => $logs[$i]->jenis === ProductionLog::MULAI)->last();
        $closeAt = $logs->keys()->filter(fn ($i) => $logs[$i]->jenis === ProductionLog::SELESAI_HARI)->last();

        if ($startAt !== null && ($closeAt === null || $startAt > $closeAt)) {
            return ['state' => 'berjalan', 'log' => $logs[$startAt]];
        }

        if ($closeAt !== null && $logs[$closeAt]->waktu->copy()->timezone('Asia/Jakarta')->isSameDay(now('Asia/Jakarta'))) {
            return ['state' => 'selesai', 'log' => $logs[$closeAt]];
        }

        return ['state' => 'belum', 'log' => null];
    }

    /** Catatan hasil Simpan yang belum ditutup dengan Selesai Hari Ini. */
    public function openDayEntries(): Collection
    {
        $logs = $this->logs->values();
        $closeAt = $logs->keys()->filter(fn ($i) => $logs[$i]->jenis === ProductionLog::SELESAI_HARI)->last();

        return $logs->slice($closeAt === null ? 0 : $closeAt + 1)
            ->filter(fn (ProductionLog $log) => $log->jenis === ProductionLog::HASIL && $log->stage !== null)
            ->values();
    }

    /**
     * Rekap pcs yang dicatat sejak hari terakhir ditutup, per tahap dan per
     * ukuran. Input = output + sisa terakhir, jadi tiap baris selalu cocok
     * walaupun tahap menerima pcs tambahan dari tahap sebelumnya.
     *
     * @return array{stages: list<array{stage: string, input: int, output: int, sisa: int, sizes: array<string, int>}>, total: int, sizes: array<string, int>, unsized: int}
     */
    public function openDaySummary(): array
    {
        $order = Size::ordered()->pluck('name')->flip();
        $sorted = fn (array $sizes) => collect($sizes)
            ->filter(fn (int $qty) => $qty > 0)
            ->sortBy(fn ($qty, $size) => $order[$size] ?? PHP_INT_MAX)
            ->all();

        $stages = $this->openDayEntries()
            ->groupBy('production_stage_id')
            ->sortBy(fn (Collection $entries) => $entries->first()->stage->sort_order)
            ->map(function (Collection $entries) use ($sorted) {
                $output = (int) $entries->sum('output_qty');
                $sisa = (int) $entries->last()->sisa_qty;
                $sizes = [];
                foreach ($entries as $entry) {
                    foreach ($entry->sizeQty() as $size => $qty) {
                        $sizes[$size] = ($sizes[$size] ?? 0) + $qty;
                    }
                }

                return [
                    'stage' => $entries->first()->stage->label(),
                    'input' => $output + $sisa,
                    'output' => $output,
                    'sisa' => $sisa,
                    'sizes' => $sorted($sizes),
                ];
            })
            ->values()
            ->all();

        $sizes = [];
        foreach ($stages as $row) {
            foreach ($row['sizes'] as $size => $qty) {
                $sizes[$size] = ($sizes[$size] ?? 0) + $qty;
            }
        }
        $total = array_sum(array_column($stages, 'output'));

        return ['stages' => $stages, 'total' => $total, 'sizes' => $sorted($sizes), 'unsized' => $total - array_sum($sizes)];
    }

    /**
     * Kalimat ringkasan harian, mis. "Selesai mengerjakan Cutting dan QC & Packing,
     * total 500 pcs: S 120 pcs, M 150 pcs, L 130 pcs, XL 100 pcs."
     */
    public static function daySummaryText(array $summary): string
    {
        if ($summary['total'] <= 0) {
            return 'Selesai hari ini tanpa pcs yang dicatat.';
        }

        $names = array_column($summary['stages'], 'stage');
        $last = array_pop($names);
        $stages = $names === [] ? $last : implode(', ', $names).' dan '.$last;

        $parts = collect($summary['sizes'])->map(fn (int $qty, $size) => $size.' '.$qty.' pcs')->values();
        if ($parts->isNotEmpty() && $summary['unsized'] > 0) {
            $parts->push('tanpa rincian ukuran '.$summary['unsized'].' pcs');
        }

        return 'Selesai mengerjakan '.$stages.', total '.$summary['total'].' pcs'
            .($parts->isEmpty() ? '' : ': '.$parts->implode(', ')).'.';
    }

    /** Catatan untuk daftar Log Produksi. Hasil Simpan sudah tercakup di rekap harian. */
    public function timelineLogs(): Collection
    {
        return $this->logs->reject(fn (ProductionLog $log) => $log->jenis === ProductionLog::HASIL)->values();
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

    /**
     * Total pemakaian bahan untuk pcs per ukuran, plus sampel yang sudah dibuat.
     * Kunci kosong berarti pcs yang tidak dirinci per ukuran.
     *
     * @param  array<string, int>  $unitsBySize
     * @return array<int, float> material_id => jumlah
     */
    public function materialUsage(array $unitsBySize): array
    {
        $samples = $this->sampleUnits();
        if ($samples > 0) {
            $key = (string) ($this->sample_size ?? '');
            $unitsBySize[$key] = ($unitsBySize[$key] ?? 0) + $samples;
        }

        $usage = [];
        foreach ($unitsBySize as $size => $units) {
            $size = (string) $size;
            foreach ($this->product->recipeForSize($size === '' ? null : $size) as $line) {
                $usage[$line->material_id] = ($usage[$line->material_id] ?? 0) + (float) $line->qty_required * (int) $units;
            }
        }

        return $usage;
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
     * Posisi produksi saat ini dalam satu kalimat pendek, mis. "Tahap Sewing Massal:
     * 20 dari 50 pcs". Tahap massal bisa berjalan bersamaan (gate 50%), jadi semua
     * tahap yang sedang berjalan disebut; kalau tidak ada, tahap berikutnya.
     */
    public function positionSummary(): string
    {
        $stages = $this->stages->sortBy('sort_order');

        $current = $stages->where('status', 'in_progress');
        if ($current->isEmpty()) {
            $current = $stages->where('status', 'pending')->take(1);
        }

        if ($current->isEmpty()) {
            return $stages->isEmpty() ? 'Batch ini belum memiliki tahap produksi.' : 'Semua tahap sudah selesai.';
        }

        return $current->map(fn (ProductionStage $s) => $this->stagePosition($s))->implode('; ');
    }

    private function stagePosition(ProductionStage $stage): string
    {
        $label = $stage->label();
        if ($stage->phase === 'mass') {
            $label .= ' Massal';
        } elseif ($stage->phase === 'sample' && $stage->stage !== 'qc_packing') {
            $label .= ' Sampel';   // label QC fase sampel sudah "QC Sampel"
        }

        $pending = $stage->status === 'pending';

        if (! $stage->carriesQty()) {
            return 'Tahap '.$label.': '.($pending ? 'belum dimulai' : 'sedang dikerjakan');
        }

        return 'Tahap '.$label.': '.(int) $stage->output_qty.' dari '.$this->stageReceived($stage).' pcs'
            .($pending ? ' (belum dimulai)' : '');
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
