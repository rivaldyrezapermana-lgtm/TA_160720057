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

        // Produk yang sudah punya sampel tidak butuh pilihan ukuran sampel
        // (kriterianya sama dengan Production::sampleBatchFor).
        $productsWithSample = Production::query()
            ->where(fn ($q) => $q->whereNotNull('sample_approved_at')->orWhere('status', 'completed'))
            ->distinct()->pluck('product_id')->map(fn ($id) => (int) $id)->values();

        return view('admin.productions.create', compact('products', 'machines', 'productSizes', 'selectedProduct', 'prefillSizes', 'productsWithSample'));
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

        // Cek sampel sebelum batch dibuat: produk yang sudah punya sampel tidak
        // diberi fase sampel sama sekali (dan tidak butuh ukuran sampel), sehingga
        // batch tidak pernah tersimpan setengah jadi dengan Tahap Sampel.
        $existingSample = Production::sampleBatchFor($product->id);

        $plan = $this->resolvePlan($product, $data, $existingSample === null);
        if ($plan['error'] !== null) {
            return back()->withErrors($plan['error'])->withInput();
        }
        $data['planned_qty'] = $plan['planned'];

        $source = (int) $data['planned_qty'] > 1 ? $existingSample : null;

        $production = DB::transaction(function () use ($data, $plan, $source) {
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

            if ($source !== null) {
                $production->forceFill([
                    'sample_approved_at' => now(),
                    'sample_source_production_id' => $source->id,
                ])->save();
            }

            foreach ($plan['sizes'] as $size => $qty) {
                $production->sizes()->create(['size' => $size, 'planned_qty' => $qty]);
            }

            if (empty($data['code'])) {
                $production->forceFill(['code' => 'PRD-'.now()->year.'-'.str_pad((string) $production->id, 4, '0', STR_PAD_LEFT)])->save();
            }

            foreach ($this->stageBlueprint((int) $data['planned_qty'], $source !== null) as $row) {
                $stage = $production->stages()->create($row + ['status' => 'pending', 'output_qty' => 0]);

                // Tahap massal dirinci per ukuran: cutting menerima target tiap ukuran.
                if ($stage->phase === 'mass' && $stage->carriesQty()) {
                    foreach ($plan['sizes'] as $size => $qty) {
                        $stage->sizeRows()->create([
                            'size' => $size,
                            'input_qty' => $stage->stage === 'cutting' ? $qty : 0,
                            'output_qty' => 0,
                        ]);
                    }
                }
            }

            return $production;
        });

        $show = route('admin.productions.show', $production->id);

        if (! $production->hasSamplePhase()) {
            return redirect($show)->with('success', 'Batch produksi dibuat.');
        }

        if ($source !== null) {
            return redirect($show.'#produksi-massal')->with('success',
                'Batch produksi dibuat. Memakai sampel dari '.$source->code.' — langsung produksi massal.');
        }

        return redirect($show.'#tahap-sampel')->with('success',
            'Batch produksi dibuat. Produk ini belum punya sampel, jadi mulai dari Tahap Sampel.');
    }

    /** Tombol Cek Sampel di form buat batch: info sampel produk yang dipilih (JSON). */
    public function sampleStatus(Request $request)
    {
        $request->validate(['product_id' => ['required', 'exists:products,id']]);

        $source = Production::sampleBatchFor((int) $request->query('product_id'));

        if ($source === null) {
            return response()->json(['has_sample' => false]);
        }

        $source->load('designSketches');

        return response()->json([
            'has_sample' => true,
            'code' => $source->code,
            'url' => route('admin.productions.show', $source->id),
            'approved_at' => $source->sample_approved_at?->translatedFormat('d M Y'),
            'sketches' => $source->designSketches
                ->map(fn ($s) => Storage::disk('public')->url($s->path))->values(),
        ]);
    }

    /**
     * Target batch dari input form. Produk yang punya ukuran wajib dirinci per
     * ukuran dan targetnya adalah jumlah rinciannya; produk tanpa ukuran memakai
     * planned_qty apa adanya. Ukuran sampel default ke ukuran dengan target
     * terbanyak, dan dikosongkan kalau batch memakai sampel yang sudah ada.
     *
     * @return array{planned: int, sizes: array<string, int>, sample_size: ?string, error: ?array<string, string>}
     */
    private function resolvePlan(Product $product, array $data, bool $needsSampleSize = true): array
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

        if ($plan['planned'] > 1 && $needsSampleSize) {
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
     * 1 pcs — batch 1 pcs memang produknya sekaligus sampelnya. Batch yang memakai
     * sampel dari batch lain tidak punya Persiapan maupun fase sampel: desain dan
     * pola hanya dikerjakan saat membuat sampel, jadi langsung produksi massal.
     *
     * @return list<array{phase: string, sort_order: int, stage: string, input_qty: int}>
     */
    private function stageBlueprint(int $plannedQty, bool $reusesSample = false): array
    {
        $rows = [];

        if (! $reusesSample) {
            $rows[] = ['phase' => 'common', 'stage' => 'design', 'input_qty' => 0];
            $rows[] = ['phase' => 'common', 'stage' => 'pola', 'input_qty' => 0];
        }

        if ($plannedQty > 1 && ! $reusesSample) {
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
            'stages.sizeRows',
            'productionMaterials.material',
            'sampleRevisions.user',
            'sizes',
            'designSketches',
        ]);

        // Machines available per stage: those whose category maps to the stage.
        $machinesByStage = [];
        foreach (ProductionStage::MACHINE_STAGES as $s) {
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

        // Untuk tombol Cek Sampel: sampel produk ini dari batch lain, kalau ada.
        $production->load('sampleSource.designSketches');
        $existingSample = $production->sampleSource
            ?? ($production->sampleApproved() ? null : $production->existingSampleBatch()?->load('designSketches'));

        return view('admin.productions.show', compact('production', 'machinesByStage', 'bahan', 'existingSample'));
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
            $plan = $this->resolvePlan($production->product, $data, $production->makesOwnSample());
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

        // Cutting massal menerima seluruh target; yang sudah dipotong tidak bisa dibatalkan.
        $massCutting = $production->stages()->where('phase', 'mass')->where('stage', 'cutting')->first();
        $minPlanned = (int) ($massCutting?->output_qty ?? 0);
        if ((int) $data['planned_qty'] < $minPlanned) {
            return back()->withErrors([
                'planned_qty' => 'Target produksi tidak boleh lebih kecil dari jumlah yang sudah dipotong ('.$minPlanned.' pcs).',
            ])->withInput();
        }

        // Per ukuran: target ukuran tidak boleh di bawah yang sudah dipotong untuk ukuran itu.
        $massCutting?->load('sizeRows');
        $sizedCutting = $plan !== null && $massCutting !== null && $massCutting->sizeRows->isNotEmpty();
        if ($sizedCutting) {
            foreach ($massCutting->sizeRows as $row) {
                $newQty = (int) ($plan['sizes'][$row->size] ?? 0);
                if ($newQty < (int) $row->output_qty) {
                    return back()->withErrors([
                        'sizes' => 'Target ukuran '.$row->size.' tidak boleh lebih kecil dari yang sudah dipotong ('.$row->output_qty.' pcs).',
                    ])->withInput();
                }
            }
        }

        DB::transaction(function () use ($production, $data, $plan, $massCutting, $sizedCutting) {
            if ($sizedCutting) {
                $this->resizeMassStages($production, $plan['sizes']);
            } elseif ($massCutting !== null) {
                // Sisa cutting massal mengikuti target baru.
                $massCutting->forceFill([
                    'input_qty' => (int) $data['planned_qty'] - (int) $massCutting->output_qty,
                ])->save();
            }

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

    /**
     * Target per ukuran berubah: sisa cutting tiap ukuran = target baru − yang
     * sudah dipotong. Ukuran baru mendapat baris di semua tahap massal; ukuran
     * yang dihapus (belum pernah dipotong) dibuang dari semua tahap massal.
     *
     * @param  array<string, int>  $sizes
     */
    private function resizeMassStages(Production $production, array $sizes): void
    {
        $stages = $production->stages()->where('phase', 'mass')
            ->whereIn('stage', ProductionStage::QTY_STAGES)->with('sizeRows')->get();

        foreach ($stages as $stage) {
            $stage->sizeRows()->whereNotIn('size', array_keys($sizes))->delete();

            foreach ($sizes as $size => $qty) {
                $row = $stage->sizeRows()->firstOrNew(['size' => $size], ['input_qty' => 0, 'output_qty' => 0]);
                if ($stage->stage === 'cutting') {
                    $row->input_qty = $qty - (int) $row->output_qty;
                }
                $row->save();
            }

            $stage->load('sizeRows');
            $stage->syncTotalsFromSizes();
            $stage->save();
        }
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
        $production->load(['stages.sizeRows', 'product.materials']);
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
        $sizedStage = $qtyEditable && $stage->sizeRows->isNotEmpty();
        $sizeQty = [];

        if ($sizedStage) {
            // Output diisi per ukuran; totalnya dihitung dari rincian.
            $data = $request->validate([
                'size_qty' => ['nullable', 'array'],
                'size_qty.*' => ['nullable', 'integer', 'min:0'],
                'production_machine_id' => ['nullable', 'exists:production_machines,id'],
            ]);

            if ($request->has('size_qty')) {
                foreach ($production->stageSizeLimits($stage) as $size => $limit) {
                    $row = $stage->sizeRows->firstWhere('size', $size);
                    $qty = (int) ($data['size_qty'][$size] ?? $row->output_qty);

                    if ($qty > $limit['max']) {
                        return back()->withErrors([
                            'size_qty' => 'Output ukuran '.$size.' maksimal '.$limit['max'].' pcs (jumlah yang diterima tahap ini).',
                        ])->withInput();
                    }
                    if ($qty < $limit['min']) {
                        return back()->withErrors([
                            'size_qty' => 'Output ukuran '.$size.' minimal '.$limit['min'].' pcs karena sisanya sudah dikerjakan tahap berikutnya.',
                        ])->withInput();
                    }
                    $sizeQty[$size] = $qty;
                }
            }
        } elseif ($qtyEditable) {
            // Input tahap massal tidak diketik: input = sisa yang belum dikerjakan,
            // otomatis berkurang saat output bertambah dan diteruskan ke tahap berikutnya.
            $maxOutput = $production->stageMaxOutput($stage);
            $minOutput = $production->stageMinOutput($stage);

            $data = $request->validate([
                'output_qty' => ['nullable', 'integer', 'min:'.$minOutput, 'max:'.$maxOutput],
                'production_machine_id' => ['nullable', 'exists:production_machines,id'],
            ], [
                'output_qty.max' => 'Output maksimal '.$maxOutput.' pcs (jumlah yang diterima tahap ini).',
                'output_qty.min' => 'Output minimal '.$minOutput.' pcs karena sisanya sudah dikerjakan tahap berikutnya.',
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

        // Tahap tanpa mesin (Desain) mengabaikan pilihan mesin yang terkirim.
        if (! $stage->usesMachine()) {
            $data['production_machine_id'] = null;
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
        if ($isMassQcFinish && ! $sizedStage && $production->sizes()->exists()) {
            $sizeOutput = $this->resolveSizeOutput($request, $production, (int) ($data['output_qty'] ?? $stage->output_qty));
            if (is_string($sizeOutput)) {
                return back()->withErrors(['size_output' => $sizeOutput])->withInput();
            }
        }

        DB::transaction(function () use ($production, $stage, $action, $data, $qtyEditable, $sizedStage, $sizeQty, &$sizeOutput) {
            if ($sizedStage) {
                $next = $production->nextQtyStage($stage);

                foreach ($sizeQty as $size => $qty) {
                    $row = $stage->sizeRows->firstWhere('size', $size);
                    $delta = $qty - (int) $row->output_qty;
                    if ($delta === 0) {
                        continue;
                    }

                    $row->input_qty = (int) $row->input_qty - $delta;
                    $row->output_qty = $qty;
                    $row->save();

                    // Output ukuran ini menjadi sisa ukuran yang sama di tahap berikutnya.
                    if ($next !== null) {
                        $nextRow = $next->sizeRows->firstWhere('size', $size)
                            ?? $next->sizeRows()->create(['size' => $size, 'input_qty' => 0, 'output_qty' => 0]);
                        $nextRow->input_qty = (int) $nextRow->input_qty + $delta;
                        $nextRow->save();
                    }
                }

                $stage->load('sizeRows');
                $stage->syncTotalsFromSizes();
                if ($next !== null) {
                    $next->load('sizeRows');
                    $next->syncTotalsFromSizes();
                    $next->save();
                }

                // Hasil QC per ukuran langsung menjadi stok per ukuran.
                $sizeOutput = $stage->sizeRows->pluck('output_qty', 'size')->map(fn ($q) => (int) $q)->all();
            } elseif ($qtyEditable && isset($data['output_qty'])) {
                $delta = (int) $data['output_qty'] - (int) $stage->output_qty;

                if ($delta !== 0) {
                    $stage->input_qty = (int) $stage->input_qty - $delta;
                    $stage->output_qty = (int) $data['output_qty'];

                    // Output tahap ini menjadi input (sisa) tahap berikutnya.
                    $next = $production->nextQtyStage($stage);
                    if ($next !== null) {
                        $next->input_qty = (int) $next->input_qty + $delta;
                        $next->save();
                    }
                }
            }

            $stage->production_machine_id = $stage->usesMachine()
                ? ($data['production_machine_id'] ?? $stage->production_machine_id)
                : null;

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

    /**
     * Produk ini sudah punya sampel dari batch lain: lewati Persiapan dan fase
     * sampel batch ini dan langsung buka produksi massal. Tahap-tahap itu dihapus
     * (desain, pola, dan kain sampel sudah ada di batch sampelnya) dan urutan
     * tahap dirapatkan lagi.
     */
    public function reuseSample(Production $production)
    {
        $production->load('stages');

        if ($production->status === 'completed') {
            return back()->with('error', 'Batch sudah selesai.');
        }

        if (! $production->canReuseSample()) {
            return back()->with('error', 'Fase sampel batch ini sudah berjalan, jadi tidak bisa memakai sampel lain.');
        }

        $source = $production->existingSampleBatch();
        if ($source === null) {
            return back()->with('error', 'Produk ini belum punya sampel. Buat sampel dulu di Tahap Sampel.');
        }

        $this->applyReusedSample($production, $source);

        return back()->with('success', 'Memakai sampel dari '.$source->code.'. Produksi massal bisa langsung dimulai.');
    }

    /**
     * Tombol Cek Sampel: langsung bertindak sesuai kondisi sampel.
     * - Sampel batch ini sudah ada/disetujui → ke Produksi Massal.
     * - Produk punya sampel dari batch lain dan fase sampel belum dimulai →
     *   pakai sampel itu (lewati fase sampel) lalu ke Produksi Massal.
     * - Selain itu (belum ada sampel, atau sampel batch ini sedang dibuat) → ke Tahap Sampel.
     */
    public function checkSample(Production $production)
    {
        $production->load('stages');
        $show = route('admin.productions.show', $production);

        if ($production->status === 'completed') {
            return redirect($show)->with('error', 'Batch sudah selesai.');
        }

        if (! $production->hasSamplePhase() || $production->sampleApproved()) {
            return redirect($show.'#produksi-massal')
                ->with('success', 'Sampel sudah ada. Lanjutkan produksi massal.');
        }

        if (! $production->canReuseSample()) {
            return redirect($show.'#tahap-sampel')
                ->with('success', 'Sampel batch ini sedang dibuat. Lanjutkan di Tahap Sampel.');
        }

        $source = $production->existingSampleBatch();
        if ($source === null) {
            return redirect($show.'#tahap-sampel')
                ->with('success', 'Produk ini belum punya sampel. Silakan buat sampel di Tahap Sampel.');
        }

        $this->applyReusedSample($production, $source);

        return redirect($show.'#produksi-massal')
            ->with('success', 'Produk ini sudah punya sampel dari '.$source->code.'. Persiapan dan tahap sampel dilewati — langsung produksi massal.');
    }

    /** Hapus Persiapan dan tahap sampel batch ini, rapatkan urutan, dan tandai sampelnya dari batch lain. */
    private function applyReusedSample(Production $production, Production $source): void
    {
        DB::transaction(function () use ($production, $source) {
            $production->stages()->whereIn('phase', ['common', 'sample'])->delete();

            $production->stages()->orderBy('sort_order')->get()
                ->each(fn (ProductionStage $s, int $i) => $s->forceFill(['sort_order' => $i + 1])->save());

            // Sampelnya dari batch lain, jadi batch ini tidak punya ukuran sampel sendiri.
            $production->forceFill([
                'sample_approved_at' => now(),
                'sample_source_production_id' => $source->id,
                'sample_size' => null,
            ])->save();
        });
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
