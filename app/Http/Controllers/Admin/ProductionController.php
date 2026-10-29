<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionMachine;
use App\Models\ProductionStage;
use App\Models\ProductSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductionController extends Controller
{
    public function index()
    {
        return view('admin.productions.index');
    }

    public function data(Request $request)
    {
        $rows = Production::with('product', 'sizes')
            ->latest()
            ->get()
            ->map(fn (Production $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'product' => $p->product?->name ?? '—',
                'planned' => $p->planned_qty,
                'actual' => $p->actual_qty,
                'sizes' => $p->sizes->map(fn ($s) => $s->size.' '.$s->planned_qty)->implode(' · '),
                'start' => optional($p->start_date)->translatedFormat('d M'),
                'end' => optional($p->end_date)->translatedFormat('d M') ?? '-',
                'status' => $p->status,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function create(Request $request)
    {
        $products = Product::with('sizes')->orderBy('name')->get(['id', 'name']);
        $machines = ProductionMachine::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        // Ukuran tiap produk untuk form; ?product_id=&sizes[M]= datang dari halaman rekomendasi.
        $productSizes = $products->mapWithKeys(fn (Product $p) => [
            $p->id => $p->sizes->sortBy('id')->pluck('size')->values(),
        ]);
        $selectedProduct = old('product_id', $request->query('product_id'));
        $prefillSizes = old('sizes', $request->query('sizes', []));

        return view('admin.productions.create', compact('products', 'machines', 'productSizes', 'selectedProduct', 'prefillSizes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'production_machine_id' => ['nullable', 'exists:production_machines,id'],
            'planned_qty' => ['nullable', 'integer', 'min:1'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['nullable', 'integer', 'min:0'],
            'sample_size' => ['nullable', 'string', 'max:10'],
            'code' => ['nullable', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $product = Product::with('sizes')->findOrFail($data['product_id']);
        $plan = $this->resolvePlan($product, $data);
        if ($plan['error'] !== null) {
            return back()->withErrors($plan['error'])->withInput();
        }
        $data['planned_qty'] = $plan['planned'];

        $production = DB::transaction(function () use ($data, $plan) {
            $production = Production::create([
                'product_id' => $data['product_id'],
                'user_id' => auth()->id(),
                'production_machine_id' => $data['production_machine_id'] ?? null,
                'code' => ($data['code'] ?? null) ?: 'TMP',
                'planned_qty' => $data['planned_qty'],
                'actual_qty' => 0,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'status' => 'planned',
                'notes' => $data['notes'] ?? null,
                'sample_size' => $plan['sample_size'],
            ]);

            foreach ($plan['sizes'] as $size => $qty) {
                $production->sizes()->create(['size' => $size, 'planned_qty' => $qty]);
            }

            if (empty($data['code'])) {
                $production->forceFill(['code' => 'PRD-'.now()->year.'-'.str_pad((string) $production->id, 4, '0', STR_PAD_LEFT)])->save();
            }

            foreach ($this->stageBlueprint((int) $data['planned_qty']) as $row) {
                $production->stages()->create($row + ['status' => 'pending', 'output_qty' => 0]);
            }

            return $production;
        });

        return redirect()->route('admin.productions.show', $production->id)
            ->with('success', 'Batch produksi dibuat.');
    }

    /**
     * Target batch dari input form. Produk yang punya ukuran wajib dirinci per
     * ukuran dan targetnya adalah jumlah rinciannya; produk tanpa ukuran memakai
     * planned_qty apa adanya. Ukuran sampel default ke ukuran dengan target
     * terbanyak.
     *
     * @return array{planned: int, sizes: array<string, int>, sample_size: ?string, error: ?array<string, string>}
     */
    private function resolvePlan(Product $product, array $data): array
    {
        $plan = ['planned' => 0, 'sizes' => [], 'sample_size' => null, 'error' => null];

        if ($product->sizes->isEmpty()) {
            if (empty($data['planned_qty'])) {
                $plan['error'] = ['planned_qty' => 'Target Qty wajib diisi.'];
            }
            $plan['planned'] = (int) ($data['planned_qty'] ?? 0);

            return $plan;
        }

        $input = $data['sizes'] ?? [];
        foreach ($product->sizes->sortBy('id') as $s) {
            $qty = (int) ($input[$s->size] ?? 0);
            if ($qty > 0) {
                $plan['sizes'][$s->size] = $qty;
            }
        }

        $plan['planned'] = array_sum($plan['sizes']);
        if ($plan['planned'] < 1) {
            $plan['error'] = ['sizes' => 'Isi jumlah minimal untuk satu ukuran.'];

            return $plan;
        }

        if ($plan['planned'] > 1) {
            $sample = ($data['sample_size'] ?? null) ?: null;
            if ($sample !== null && ! isset($plan['sizes'][$sample])) {
                $plan['error'] = ['sample_size' => 'Ukuran sampel harus salah satu ukuran yang diproduksi.'];

                return $plan;
            }
            $plan['sample_size'] = $sample ?? (string) array_search(max($plan['sizes']), $plan['sizes'], true);
        }

        return $plan;
    }

    /**
     * Susunan tahap sebuah batch. Fase sampel hanya ada kalau target lebih dari
     * 1 pcs — batch 1 pcs memang produknya sekaligus sampelnya.
     *
     * @return list<array{phase: string, sort_order: int, stage: string, input_qty: int}>
     */
    private function stageBlueprint(int $plannedQty): array
    {
        $rows = [
            ['phase' => 'common', 'stage' => 'design', 'input_qty' => 0],
            ['phase' => 'common', 'stage' => 'pola', 'input_qty' => 0],
        ];

        if ($plannedQty > 1) {
            $rows[] = ['phase' => 'sample', 'stage' => 'cutting', 'input_qty' => 1];
            $rows[] = ['phase' => 'sample', 'stage' => 'sewing', 'input_qty' => 1];
            $rows[] = ['phase' => 'sample', 'stage' => 'qc_packing', 'input_qty' => 1];
        }

        $rows[] = ['phase' => 'mass', 'stage' => 'cutting', 'input_qty' => $plannedQty];
        $rows[] = ['phase' => 'mass', 'stage' => 'sewing', 'input_qty' => 0];
        $rows[] = ['phase' => 'mass', 'stage' => 'qc_packing', 'input_qty' => 0];

        foreach ($rows as $i => $row) {
            $rows[$i]['sort_order'] = $i + 1;
        }

        return $rows;
    }

    public function show(Production $production)
    {
        $production->load([
            'product.materials.material',
            'machine',
            'stages' => fn ($q) => $q->orderBy('sort_order'),
            'stages.machine',
            'productionMaterials.material',
            'sampleRevisions.user',
            'sizes',
            'designSketches',
        ]);

        // Machines available per stage: those whose category maps to the stage.
        $machinesByStage = [];
        foreach (ProductionStage::STAGES as $s) {
            $machinesByStage[$s] = ProductionMachine::where('status', 'active')
                ->whereHas('category', fn ($q) => $q->where('stage', $s))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }

        // Estimated material usage: recipe × (target + sampel yang dibuat).
        $units = (int) $production->planned_qty + $production->sampleUnits();

        $bahan = $production->product->materials->map(fn ($line) => [
            'name' => $line->material?->name ?? '—',
            'unit' => $line->material?->unit ?? '',
            'per_unit' => (int) $line->qty_required,
            'est' => (int) $line->qty_required * $units,
        ]);

        return view('admin.productions.show', compact('production', 'machinesByStage', 'bahan'));
    }

    public function edit(Production $production)
    {
        $production->load(['machine', 'sizes', 'product.sizes']);
        $products = Product::orderBy('name')->get(['id', 'name']);
        $machines = ProductionMachine::where('status', 'active')
            ->orWhere('id', $production->production_machine_id)
            ->orderBy('name')->get(['id', 'name']);

        return view('admin.productions.edit', compact('production', 'products', 'machines'));
    }

    public function update(Request $request, Production $production)
    {
        if ($production->status === 'completed') {
            return back()->with('error', 'Batch sudah selesai. Data batch tidak bisa diubah lagi.');
        }

        $data = $request->validate([
            'production_machine_id' => ['nullable', 'exists:production_machines,id'],
            'planned_qty' => ['nullable', 'integer', 'min:1'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['nullable', 'integer', 'min:0'],
            'sample_size' => ['nullable', 'string', 'max:10'],
            'actual_qty' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(Production::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        // Batch yang dibuat dengan rincian ukuran tetap dirinci per ukuran; batch
        // lama (tanpa rincian) tetap memakai satu angka target.
        $production->load('sizes', 'product.sizes');
        $plan = null;
        if ($production->sizes->isNotEmpty()) {
            $plan = $this->resolvePlan($production->product, $data);
            if ($plan['error'] !== null) {
                return back()->withErrors($plan['error'])->withInput();
            }
            $data['planned_qty'] = $plan['planned'];
        } elseif (empty($data['planned_qty'])) {
            return back()->withErrors(['planned_qty' => 'Target Qty wajib diisi.'])->withInput();
        }

        // Fase sampel ditentukan saat batch dibuat, jadi target tidak boleh melintasi
        // batas 1 pcs ↔ produksi massal.
        if (((int) $production->planned_qty === 1) !== ((int) $data['planned_qty'] === 1)) {
            return back()->withErrors([
                'planned_qty' => 'Target tidak bisa diubah dari 1 pcs ke produksi massal (atau sebaliknya). Buat batch baru.',
            ])->withInput();
        }

        $minPlanned = (int) $production->stages()->max('input_qty');
        if ((int) $data['planned_qty'] < $minPlanned) {
            return back()->withErrors([
                'planned_qty' => 'Target produksi tidak boleh lebih kecil dari input tahap yang sudah berjalan ('.$minPlanned.' pcs).',
            ])->withInput();
        }

        DB::transaction(function () use ($production, $data, $plan) {
            $production->fill([
                'production_machine_id' => $data['production_machine_id'] ?? null,
                'planned_qty' => $data['planned_qty'],
                'actual_qty' => $data['actual_qty'] ?? $production->actual_qty,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ]);

            if ($plan !== null) {
                $production->sample_size = $plan['sample_size'];
                $production->sizes()->whereNotIn('size', array_keys($plan['sizes']))->delete();
                foreach ($plan['sizes'] as $size => $qty) {
                    $production->sizes()->updateOrCreate(['size' => $size], ['planned_qty' => $qty]);
                }
            }

            $production->save();
        });

        return redirect()->route('admin.productions.show', $production->id)
            ->with('success', 'Batch produksi diperbarui.');
    }

    public function destroy(Production $production)
    {
        if (! in_array($production->status, ['planned', 'cancelled'], true)) {
            return back()->with('error', 'Batch yang sudah berjalan tidak bisa dihapus. Tandai sebagai cancelled.');
        }

        // Baris sketsa ikut terhapus lewat cascade, tapi filenya harus dihapus sendiri.
        Storage::disk('public')->delete($production->designSketches()->pluck('path')->all());
        $production->delete();

        return redirect()->route('admin.productions.index')->with('success', 'Batch dihapus.');
    }

    /**
     * Update a stage's quantities and machine. Tahap fase massal tunduk pada gate
     * 50%; tahap lain menunggu tahap sebelumnya selesai. Menyelesaikan QC & Packing
     * fase massal menutup batch: stok produk bertambah dan bahan resep dipotong,
     * tepat satu kali.
     */
    public function updateStage(Request $request, Production $production, ProductionStage $stage)
    {
        abort_unless($stage->production_id === $production->id, 404);
        $production->load(['stages', 'product.materials']);
        $stage = $production->stages->firstWhere('id', $stage->id);

        if ($production->status === 'completed') {
            return back()->with('error', 'Batch sudah selesai. Tahapan tidak bisa diubah lagi.');
        }

        $action = $request->input('action', 'save');

        if (in_array($action, ['approve_sample', 'revise_sample'], true)) {
            if ($stage->phase !== 'sample' || $stage->stage !== 'qc_packing') {
                return back()->with('error', 'Aksi ini hanya berlaku untuk tahap QC Sampel.');
            }

            return $action === 'approve_sample'
                ? $this->approveSample($production, $stage)
                : $this->reviseSample($request, $production);
        }

        $qtyEditable = $stage->carriesQty() && $stage->phase === 'mass';

        if ($qtyEditable) {
            $maxInput = $production->stageMaxInput($stage);
            $minOutput = $production->stageMinOutput($stage);

            $data = $request->validate([
                'input_qty' => ['nullable', 'integer', 'min:0', 'max:'.$maxInput],
                'output_qty' => ['nullable', 'integer', 'min:'.$minOutput, 'lte:input_qty'],
                'production_machine_id' => ['nullable', 'exists:production_machines,id'],
            ], [
                'input_qty.max' => 'Input maksimal '.$maxInput.' pcs (dibatasi target batch dan output tahap sebelumnya).',
                'output_qty.min' => 'Output minimal '.$minOutput.' pcs karena tahap berikutnya sudah menerima sebanyak itu.',
                'output_qty.lte' => 'Output tidak boleh melebihi input.',
            ]);
        } elseif ($stage->phase === 'sample') {
            $data = $request->validate([
                'input_qty' => ['nullable', 'integer', 'in:1'],
                'output_qty' => ['nullable', 'integer', 'in:1'],
                'production_machine_id' => ['nullable', 'exists:production_machines,id'],
            ], [
                'input_qty.in' => 'Tahap sampel selalu 1 pcs.',
                'output_qty.in' => 'Tahap sampel selalu 1 pcs.',
            ]);
        } else {
            $data = $request->validate([
                'production_machine_id' => ['nullable', 'exists:production_machines,id'],
            ]);
        }

        // Machine must belong to the category mapped to this stage.
        if (! empty($data['production_machine_id'])) {
            $machine = ProductionMachine::with('category')->find($data['production_machine_id']);
            if (! $machine || $machine->category?->stage !== $stage->stage) {
                return back()->with('error', 'Mesin yang dipilih tidak sesuai dengan tahap ini.');
            }
        }

        if ($action === 'start' && ! $production->stageUnlocked($stage)) {
            return back()->with('error', $this->lockReason($production, $stage));
        }

        if ($action === 'finish' && $stage->stage === 'design' && ! $production->designSketches()->exists()) {
            return back()->with('error', 'Unggah minimal satu sketsa desain sebelum menyelesaikan tahap Desain.');
        }

        // Menutup batch yang dirinci per ukuran: hasil QC harus dibagi ke tiap ukuran.
        $sizeOutput = [];
        $isMassQcFinish = $action === 'finish' && $stage->phase === 'mass' && $stage->stage === 'qc_packing';
        if ($isMassQcFinish && $production->sizes()->exists()) {
            $sizeOutput = $this->resolveSizeOutput($request, $production, (int) ($data['output_qty'] ?? $stage->output_qty));
            if (is_string($sizeOutput)) {
                return back()->withErrors(['size_output' => $sizeOutput])->withInput();
            }
        }

        DB::transaction(function () use ($production, $stage, $action, $data, $qtyEditable, $sizeOutput) {
            if ($qtyEditable) {
                $stage->input_qty = $data['input_qty'] ?? $stage->input_qty;
                $stage->output_qty = $data['output_qty'] ?? $stage->output_qty;
            }

            $stage->production_machine_id = $data['production_machine_id'] ?? $stage->production_machine_id;

            if ($action === 'start' && $stage->status === 'pending') {
                $stage->status = 'in_progress';
                $stage->started_at = now();
                if ($production->status === 'planned') {
                    $production->forceFill(['status' => 'in_progress'])->save();
                }
            }

            if ($action === 'finish') {
                $stage->status = 'completed';
                $stage->finished_at = now();

                // Fase sampel selalu satu potong.
                if ($stage->phase === 'sample') {
                    $stage->input_qty = 1;
                    $stage->output_qty = 1;
                }
            }

            $stage->save();

            $isMassQc = $stage->phase === 'mass' && $stage->stage === 'qc_packing';

            if ($isMassQc && $action === 'start' && $production->status !== 'completed') {
                $production->forceFill(['status' => 'qc'])->save();
            }

            if ($isMassQc && $action === 'finish' && $production->status !== 'completed') {
                $this->completeBatch($production, $stage, $sizeOutput);
            }
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Tahap diperbarui.');
    }

    /**
     * Hasil akhir per ukuran saat QC & Packing massal selesai. Batch satu ukuran
     * terisi otomatis; selebihnya tiap ukuran tidak boleh melebihi targetnya dan
     * totalnya harus sama dengan output QC.
     *
     * @return array<string, int>|string rincian per ukuran, atau pesan error
     */
    private function resolveSizeOutput(Request $request, Production $production, int $output): array|string
    {
        $sizes = $production->sizes()->get();

        if ($sizes->count() === 1) {
            return [$sizes->first()->size => $output];
        }

        $request->validate([
            'size_output' => ['nullable', 'array'],
            'size_output.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $input = $request->input('size_output', []);
        $result = [];
        foreach ($sizes as $s) {
            $qty = (int) ($input[$s->size] ?? 0);
            if ($qty > $s->planned_qty) {
                return 'Hasil ukuran '.$s->size.' tidak boleh melebihi targetnya ('.$s->planned_qty.' pcs).';
            }
            $result[$s->size] = $qty;
        }

        if (array_sum($result) !== $output) {
            return 'Jumlah hasil per ukuran ('.array_sum($result).' pcs) harus sama dengan output QC & Packing ('.$output.' pcs).';
        }

        return $result;
    }

    /** Alasan sebuah tahap masih terkunci, dalam kalimat yang bisa dibaca operator. */
    private function lockReason(Production $production, ProductionStage $stage): string
    {
        return match ($production->lockCause($stage)) {
            'sample_not_approved' => 'Sampel belum disetujui. Selesaikan dan setujui sampel sebelum memulai produksi massal.',
            'gate_50' => 'Tahap sebelumnya belum mencapai 50%. Belum bisa dimulai.',
            default => 'Tahap sebelumnya belum selesai. Belum bisa dimulai.',
        };
    }

    /** Sampel disetujui: fase massal terbuka. */
    private function approveSample(Production $production, ProductionStage $sampleQc)
    {
        if ($sampleQc->status !== 'completed') {
            return back()->with('error', 'Selesaikan tahap QC Sampel dulu sebelum menyetujui sampel.');
        }

        $production->forceFill(['sample_approved_at' => now()])->save();

        return back()->with('success', 'Sampel disetujui. Produksi massal bisa dimulai.');
    }

    /**
     * Sampel perlu diperbaiki: catat alasannya dan buka kembali seluruh tahap
     * fase sampel supaya satu potong dibuat ulang.
     */
    private function reviseSample(Request $request, Production $production)
    {
        $data = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ], [
            'notes.required' => 'Catatan revisi wajib diisi.',
        ]);

        DB::transaction(function () use ($production, $data) {
            // Kunci baris batch dulu. Dua klik "Perlu revisi" beruntun tidak boleh
            // menghitung nomor revisi yang sama dan menabrak unique constraint
            // (production_id, revision_no) dengan error mentah.
            $locked = Production::whereKey($production->getKey())->lockForUpdate()->firstOrFail();
            $revisionNo = (int) $locked->sample_revision_count + 1;

            $locked->sampleRevisions()->create([
                'revision_no' => $revisionNo,
                'notes' => $data['notes'],
                'user_id' => auth()->id(),
            ]);

            $locked->forceFill([
                'sample_revision_count' => $revisionNo,
                'sample_approved_at' => null,
            ])->save();

            $locked->stages()->where('phase', 'sample')->update([
                'status' => 'pending',
                'input_qty' => 1,
                'output_qty' => 0,
                'started_at' => null,
                'finished_at' => null,
            ]);
        });

        return back()->with('success', 'Sampel ditandai perlu revisi. Tahap sampel dibuka kembali.');
    }

    /**
     * Finalize a batch: credit product stock and consume recipe materials.
     * Hasil per ukuran masuk ke stok ukuran produk yang sama.
     * Sampel memakan bahan tapi tidak masuk stok jual.
     * Guarded by status so it can only run once.
     *
     * @param  array<string, int>  $sizeOutput
     */
    private function completeBatch(Production $production, ProductionStage $qcPacking, array $sizeOutput = []): void
    {
        $actual = (int) $qcPacking->output_qty;

        $production->forceFill([
            'status' => 'completed',
            'actual_qty' => $actual,
            'end_date' => $production->end_date ?? now()->toDateString(),
        ])->save();

        Product::where('id', $production->product_id)->increment('stock', $actual);

        foreach ($sizeOutput as $size => $qty) {
            $production->sizes()->where('size', $size)->update(['actual_qty' => $qty]);

            if ($qty > 0) {
                ProductSize::firstOrCreate(['product_id' => $production->product_id, 'size' => $size])
                    ->increment('stock', $qty);
            }
        }

        $units = $actual + $production->sampleUnits();

        foreach ($production->product->materials as $line) {
            $used = (int) $line->qty_required * $units;
            if ($used <= 0) {
                continue;
            }

            $production->productionMaterials()->create([
                'material_id' => $line->material_id,
                'qty_used' => $used,
            ]);

            Material::where('id', $line->material_id)->decrement('stock', $used);
        }
    }
}
