<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionMachine;
use App\Models\ProductionStage;
use App\Models\ProductSize;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $machines = ProductionMachine::where('status', 'active')->orderBy('kode_mesin')->get(['id', 'name', 'kode_mesin']);

        // Ukuran yang bisa dicentang per produk (master + ukuran milik produk) dan
        // varian yang sudah ada; ?product_id=&sizes[M]= datang dari halaman rekomendasi.
        $master = Size::ordered()->pluck('name');
        $productSizes = $products->mapWithKeys(fn (Product $p) => [
            $p->id => $this->sizeOptions($master, $p),
        ]);
        $productVariants = $products->mapWithKeys(fn (Product $p) => [
            $p->id => $p->sizes->pluck('size')->values(),
        ]);
        $selectedProduct = old('product_id', $request->query('product_id'));
        $prefillSizes = old('sizes', $request->query('sizes', []));

        // Produk yang sudah punya sampel tidak butuh pilihan ukuran sampel
        // (kriterianya sama dengan Production::sampleBatchFor).
        $productsWithSample = Production::query()
            ->where(fn ($q) => $q->whereNotNull('sample_approved_at')->orWhere('status', 'completed'))
            ->distinct()->pluck('product_id')->map(fn ($id) => (int) $id)->values();

        return view('admin.productions.create', compact('products', 'machines', 'productSizes', 'productVariants', 'selectedProduct', 'prefillSizes', 'productsWithSample'));
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

        $production = DB::transaction(function () use ($product, $data, $plan, $source) {
            $this->ensureProductSizes($product, $plan['sizes']);

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
                'Batch produksi dibuat. Menggunakan sampel dari '.$source->code.', sehingga langsung masuk produksi massal.');
        }

        return redirect($show.'#tahap-sampel')->with('success',
            'Batch produksi dibuat. Produk ini belum memiliki sampel, sehingga produksi dimulai dari Tahap Sampel.');
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
                ->map(fn ($s) => asset('storage/'.$s->path))->values(),
        ]);
    }

    /**
     * Ukuran yang boleh diproduksi: master sesuai urutannya, lalu ukuran milik
     * produk dan milik batch yang tidak ada di master.
     *
     * @param  Collection<int, string>  $master
     * @param  iterable<string>  $batchSizes
     * @return list<string>
     */
    private function sizeOptions(Collection $master, Product $product, iterable $batchSizes = []): array
    {
        return $master
            ->merge($product->sizes->sortBy('id')->pluck('size'))
            ->merge($batchSizes)
            ->map(fn ($size) => (string) $size)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ukuran master yang belum jadi varian produk dibuatkan variannya (stok 0),
     * supaya hasil produksinya punya tempat saat batch selesai.
     *
     * @param  array<string, int>  $sizes
     */
    private function ensureProductSizes(Product $product, array $sizes): void
    {
        foreach (array_keys($sizes) as $size) {
            ProductSize::firstOrCreate(['product_id' => $product->id, 'size' => (string) $size], ['stock' => 0]);
        }
    }

    /**
     * Target batch dari input form. Ukuran yang diproduksi hanya yang dikirim dengan
     * jumlah di atas 0, dan targetnya adalah jumlah rinciannya; ukuran yang tidak
     * dikenal ditolak. Produk tanpa varian boleh memakai planned_qty tanpa rincian.
     * Ukuran sampel default ke ukuran dengan target terbanyak, dan dikosongkan
     * kalau batch memakai sampel yang sudah ada.
     *
     * @param  list<string>  $batchSizes  ukuran batch yang sedang diedit
     * @return array{planned: int, sizes: array<string, int>, sample_size: ?string, error: ?array<string, string>}
     */
    private function resolvePlan(Product $product, array $data, bool $needsSampleSize = true, array $batchSizes = []): array
    {
        $plan = ['planned' => 0, 'sizes' => [], 'sample_size' => null, 'error' => null];

        $options = $this->sizeOptions(Size::ordered()->pluck('name'), $product, $batchSizes);
        $input = array_filter($data['sizes'] ?? [], fn ($qty) => (int) $qty > 0);

        foreach (array_keys($input) as $size) {
            if (! in_array((string) $size, $options, true)) {
                $plan['error'] = ['sizes' => 'Ukuran '.$size.' tidak terdaftar di master ukuran maupun di produk ini.'];

                return $plan;
            }
        }

        if ($input === [] && $product->sizes->isEmpty() && $batchSizes === []) {
            if (empty($data['planned_qty'])) {
                // Ada ukuran yang dicentang tapi tanpa jumlah: kolom Target Qty sedang tersembunyi.
                $plan['error'] = empty($data['sizes'])
                    ? ['planned_qty' => 'Target Jumlah wajib diisi.']
                    : ['sizes' => 'Isi jumlah minimal untuk satu ukuran.'];
            }
            $plan['planned'] = (int) ($data['planned_qty'] ?? 0);

            return $plan;
        }

        foreach ($options as $size) {
            if (isset($input[$size])) {
                $plan['sizes'][$size] = (int) $input[$size];
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
            'stages.machines',
            'stages.sizeRows',
            'productionMaterials.material',
            'sampleRevisions.user',
            'sizes',
            'designSketches',
            'logs.user',
            'logs.stage',
        ]);

        // Machines available per stage: those whose category maps to the stage.
        $machinesByStage = [];
        foreach (ProductionStage::MACHINE_STAGES as $s) {
            $machinesByStage[$s] = ProductionMachine::where('status', 'active')
                ->whereHas('category', fn ($q) => $q->where('stage', $s))
                ->orderBy('kode_mesin')
                ->get(['id', 'name', 'kode_mesin'])
                ->mapWithKeys(fn (ProductionMachine $m) => [$m->id => $m->display_name])
                ->toArray();
        }

        // Perkiraan pemakaian bahan: resep tiap ukuran × target ukuran itu, plus sampel.
        $plannedUnits = $production->sizes->isNotEmpty()
            ? $production->sizes->pluck('planned_qty', 'size')->map(fn ($qty) => (int) $qty)->all()
            : ['' => (int) $production->planned_qty];
        $materialNames = $production->product->materials->pluck('material', 'material_id');

        $bahan = collect($production->materialUsage($plannedUnits))->map(fn (float $qty, int $materialId) => [
            'name' => $materialNames[$materialId]?->name ?? '—',
            'unit' => $materialNames[$materialId]?->unit ?? '',
            'est' => $qty,
        ])->values();

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
            ->orderBy('kode_mesin')->get(['id', 'name', 'kode_mesin'])
            ->mapWithKeys(fn (ProductionMachine $m) => [$m->id => $m->display_name]);

        $sizeOptions = $this->sizeOptions(
            Size::ordered()->pluck('name'),
            $production->product,
            $production->sizes->pluck('size')
        );

        // Ukuran yang sudah dipotong tidak bisa dihapus dari batch.
        $cutBySize = $production->stages()->where('phase', 'mass')->where('stage', 'cutting')->first()
            ?->sizeRows->pluck('output_qty', 'size')->map(fn ($qty) => (int) $qty)->all() ?? [];

        return view('admin.productions.edit', compact('production', 'products', 'machines', 'sizeOptions', 'cutBySize'));
    }

    public function update(Request $request, Production $production)
    {
        if ($production->status === 'completed') {
            return back()->with('error', 'Batch sudah selesai. Data batch tidak dapat diubah lagi.');
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
            $plan = $this->resolvePlan($production->product, $data, $production->makesOwnSample(), $production->sizes->pluck('size')->all());
            if ($plan['error'] !== null) {
                return back()->withErrors($plan['error'])->withInput();
            }
            $data['planned_qty'] = $plan['planned'];
        } elseif (empty($data['planned_qty'])) {
            return back()->withErrors(['planned_qty' => 'Target Jumlah wajib diisi.'])->withInput();
        }

        // Fase sampel ditentukan saat batch dibuat, jadi target tidak boleh melintasi
        // batas 1 pcs ↔ produksi massal.
        if (((int) $production->planned_qty === 1) !== ((int) $data['planned_qty'] === 1)) {
            return back()->withErrors([
                'planned_qty' => 'Target tidak dapat diubah dari 1 pcs ke produksi massal (atau sebaliknya). Buat batch baru.',
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
            if ($plan !== null) {
                $this->ensureProductSizes($production->product, $plan['sizes']);
            }

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

    /**
     * Tombol Pause: hanya mencatat posisi produksi saat ini. Status batch dan
     * tahap tidak berubah. Keterangan kosong diisi otomatis dengan posisi tahap.
     */
    public function pause(Request $request, Production $production)
    {
        $data = $request->validate([
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        if (in_array($production->status, ['completed', 'cancelled'], true)) {
            return back()->with('error', 'Batch sudah selesai atau dibatalkan. Catatan pause tidak dapat ditambahkan.');
        }

        $production->load('stages');

        $production->logs()->create([
            'waktu' => now(),
            'keterangan' => filled($data['keterangan'] ?? null) ? $data['keterangan'] : $production->positionSummary(),
            'user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Catatan pause berhasil disimpan.');
    }

    public function destroy(Production $production)
    {
        if (! in_array($production->status, ['planned', 'cancelled'], true)) {
            return back()->with('error', 'Batch yang sudah berjalan tidak dapat dihapus. Tandai sebagai Dibatalkan.');
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
     * tepat satu kali. Setiap pcs yang tercatat di tahap massal masuk ke log harian.
     */
    public function updateStage(Request $request, Production $production, ProductionStage $stage)
    {
        abort_unless($stage->production_id === $production->id, 404);
        $production->load(['stages.sizeRows', 'product.materials']);
        $stage = $production->stages->firstWhere('id', $stage->id);

        if ($production->status === 'completed') {
            return back()->with('error', 'Batch sudah selesai. Tahapan tidak dapat diubah lagi.');
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
            // Per ukuran: angka yang diketik adalah pcs yang baru dikerjakan —
            // mengurangi sisa ukuran itu, bukan mengganti outputnya.
            $data = $request->validate([
                'size_qty' => ['nullable', 'array'],
                'size_qty.*' => ['nullable', 'integer', 'min:0'],
            ]);

            foreach ($stage->sizeRows as $row) {
                $qty = (int) ($data['size_qty'][$row->size] ?? 0);

                if ($qty > (int) $row->input_qty) {
                    return back()->withErrors([
                        'size_qty' => 'Ukuran '.$row->size.' maksimal '.$row->input_qty.' pcs (sisa tahap ini).',
                    ])->withInput();
                }
                $sizeQty[$row->size] = $qty;
            }
        } elseif ($qtyEditable) {
            // Input tahap massal tidak diketik: input = sisa yang belum dikerjakan.
            // Angka yang diketik adalah pcs yang baru dikerjakan — sisa berkurang
            // sebanyak itu, output bertambah, dan pcs-nya diteruskan ke tahap berikutnya.
            $remaining = (int) $stage->input_qty;

            $data = $request->validate([
                'qty' => ['nullable', 'integer', 'min:0', 'max:'.$remaining],
            ], [
                'qty.max' => 'Maksimal '.$remaining.' pcs (sisa tahap ini).',
            ]);
        } elseif ($stage->phase === 'sample') {
            $data = $request->validate([
                'input_qty' => ['nullable', 'integer', 'in:1'],
                'output_qty' => ['nullable', 'integer', 'in:1'],
            ], [
                'input_qty.in' => 'Tahap sampel selalu 1 pcs.',
                'output_qty.in' => 'Tahap sampel selalu 1 pcs.',
            ]);
        } else {
            $data = [];
        }

        $request->validate([
            'production_machine_ids' => ['nullable', 'array'],
            'production_machine_ids.*' => ['nullable', 'integer', 'exists:production_machines,id'],
        ]);

        // Null berarti pilihan mesin tidak terkirim, jadi mesin tahap dibiarkan.
        // Form selalu mengirim satu nilai kosong, jadi daftar kosong berarti tanpa mesin.
        // Tahap tanpa mesin (Desain) mengabaikan pilihan yang terkirim.
        $machineIds = null;
        if ($stage->usesMachine() && $request->has('production_machine_ids')) {
            $machineIds = collect($request->input('production_machine_ids', []))
                ->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

            // Mesin baru harus sesuai kategori tahap. Mesin yang sudah terpasang
            // tetap boleh, walaupun kategorinya berubah setelah dipilih.
            $added = array_diff($machineIds, $stage->machines()->pluck('production_machines.id')->all());
            $mismatch = ProductionMachine::with('category')->whereKey($added)->get()
                ->contains(fn (ProductionMachine $m) => $m->category?->stage !== $stage->stage);
            if ($mismatch) {
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
            $sizeOutput = $this->resolveSizeOutput($request, $production, (int) $stage->output_qty + (int) ($data['qty'] ?? 0));
            if (is_string($sizeOutput)) {
                return back()->withErrors(['size_output' => $sizeOutput])->withInput();
            }
        }

        DB::transaction(function () use ($production, $stage, $action, $data, $machineIds, $qtyEditable, $sizedStage, $sizeQty, &$sizeOutput) {
            $remainingBefore = (int) ($sizedStage ? $stage->sizeRows->sum('input_qty') : $stage->input_qty);

            if ($sizedStage) {
                foreach ($sizeQty as $size => $qty) {
                    $this->addSizeOutput($production, $stage, $size, $qty);
                }

                // Total tahap ini dan tahap penerimanya mengikuti rincian ukurannya.
                $stage->load('sizeRows');
                $stage->syncTotalsFromSizes();
                $next = $production->nextQtyStage($stage);
                if ($next !== null) {
                    $next->load('sizeRows');
                    $next->syncTotalsFromSizes();
                    $next->save();
                }

                // Hasil QC per ukuran langsung menjadi stok per ukuran.
                $sizeOutput = $stage->sizeRows->pluck('output_qty', 'size')->map(fn ($q) => (int) $q)->all();
            } elseif ($qtyEditable) {
                $this->addStageOutput($production, $stage, (int) ($data['qty'] ?? 0));
            }

            if ($machineIds !== null) {
                $stage->machines()->sync($machineIds);
            }

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

            $recorded = $sizedStage ? array_sum($sizeQty) : ($qtyEditable ? (int) ($data['qty'] ?? 0) : 0);
            if ($recorded > 0) {
                $production->logs()->create([
                    'production_stage_id' => $stage->id,
                    'waktu' => now(),
                    'user_id' => auth()->id(),
                    'input_qty' => $remainingBefore,
                    'output_qty' => $recorded,
                    'sisa_qty' => (int) $stage->input_qty,
                    'keterangan' => $sizedStage ? $this->sizeBreakdown($sizeQty) : null,
                ]);
            }

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
     * Rincian ukuran untuk keterangan log, mis. "M 30, L 20".
     *
     * @param  array<string, int>  $sizeQty
     */
    private function sizeBreakdown(array $sizeQty): string
    {
        return collect($sizeQty)
            ->filter(fn (int $qty) => $qty > 0)
            ->map(fn (int $qty, $size) => $size.' '.$qty)
            ->implode(', ');
    }

    /**
     * Catat pcs yang baru dikerjakan tahap massal: sisa berkurang sebanyak
     * $qty, output bertambah sebanyak $qty, dan pcs itu masuk ke sisa tahap
     * berikutnya. Contoh: sisa 700, output 300, dikerjakan 200 → sisa 500, output 500.
     */
    private function addStageOutput(Production $production, ProductionStage $stage, int $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        $stage->input_qty = (int) $stage->input_qty - $qty;
        $stage->output_qty = (int) $stage->output_qty + $qty;

        $next = $production->nextQtyStage($stage);
        if ($next !== null) {
            $next->input_qty = (int) $next->input_qty + $qty;
            $next->save();
        }
    }

    /** Sama dengan addStageOutput(), untuk satu ukuran: pcs-nya masuk ke ukuran yang sama di tahap berikutnya. */
    private function addSizeOutput(Production $production, ProductionStage $stage, string $size, int $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        $row = $stage->sizeRows->firstWhere('size', $size);
        $row->input_qty = (int) $row->input_qty - $qty;
        $row->output_qty = (int) $row->output_qty + $qty;
        $row->save();

        $next = $production->nextQtyStage($stage);
        if ($next === null) {
            return;
        }

        $nextRow = $next->sizeRows->firstWhere('size', $size)
            ?? $next->sizeRows()->make(['size' => $size, 'input_qty' => 0, 'output_qty' => 0]);
        $nextRow->input_qty = (int) $nextRow->input_qty + $qty;
        $nextRow->save();
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
            return 'Jumlah hasil per ukuran ('.array_sum($result).' pcs) harus sama dengan hasil QC & Packing ('.$output.' pcs).';
        }

        return $result;
    }

    /** Alasan sebuah tahap masih terkunci, dalam kalimat yang bisa dibaca operator. */
    private function lockReason(Production $production, ProductionStage $stage): string
    {
        return match ($production->lockCause($stage)) {
            'sample_not_approved' => 'Sampel belum disetujui. Selesaikan dan setujui sampel sebelum memulai produksi massal.',
            'gate_50' => 'Tahap sebelumnya belum mencapai 50%. Belum dapat dimulai.',
            default => 'Tahap sebelumnya belum selesai. Belum dapat dimulai.',
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
            return back()->with('error', 'Fase sampel batch ini sudah berjalan, sehingga tidak dapat menggunakan sampel lain.');
        }

        $source = $production->existingSampleBatch();
        if ($source === null) {
            return back()->with('error', 'Produk ini belum memiliki sampel. Buat sampel terlebih dahulu di Tahap Sampel.');
        }

        $this->applyReusedSample($production, $source);

        return back()->with('success', 'Menggunakan sampel dari '.$source->code.'. Produksi massal dapat langsung dimulai.');
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
                ->with('success', 'Produk ini belum memiliki sampel. Silakan buat sampel di Tahap Sampel.');
        }

        $this->applyReusedSample($production, $source);

        return redirect($show.'#produksi-massal')
            ->with('success', 'Produk ini sudah memiliki sampel dari '.$source->code.'. Persiapan dan tahap sampel dilewati, sehingga langsung masuk produksi massal.');
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
            return back()->with('error', 'Selesaikan tahap QC Sampel terlebih dahulu sebelum menyetujui sampel.');
        }

        $production->forceFill(['sample_approved_at' => now()])->save();

        return back()->with('success', 'Sampel disetujui. Produksi massal dapat dimulai.');
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

        // Pemakaian bahan mengikuti hasil per ukuran, karena resep tiap ukuran berbeda.
        $units = array_filter($sizeOutput);
        if ($units === []) {
            $sizes = $production->sizes()->pluck('size');
            $units = [($sizes->count() === 1 ? (string) $sizes->first() : '') => $actual];
        }

        foreach ($production->materialUsage($units) as $materialId => $used) {
            if ($used <= 0) {
                continue;
            }

            $production->productionMaterials()->create([
                'material_id' => $materialId,
                'qty_used' => $used,
            ]);

            Material::where('id', $materialId)->decrement('stock', $used);
        }
    }
}
