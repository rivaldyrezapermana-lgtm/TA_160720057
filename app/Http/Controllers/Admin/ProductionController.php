<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionMachine;
use App\Models\ProductionStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductionController extends Controller
{
    public function index()
    {
        return view('admin.productions.index');
    }

    public function data(Request $request)
    {
        $rows = Production::with('product')
            ->latest()
            ->get()
            ->map(fn (Production $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'product' => $p->product?->name ?? '—',
                'planned' => $p->planned_qty,
                'actual' => $p->actual_qty,
                'start' => optional($p->start_date)->translatedFormat('d M'),
                'end' => optional($p->end_date)->translatedFormat('d M') ?? '-',
                'status' => $p->status,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function create()
    {
        $products = Product::orderBy('name')->get(['id', 'name']);
        $machines = ProductionMachine::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('admin.productions.create', compact('products', 'machines'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'production_machine_id' => ['nullable', 'exists:production_machines,id'],
            'planned_qty' => ['required', 'integer', 'min:1'],
            'code' => ['nullable', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $production = DB::transaction(function () use ($data) {
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
            ]);

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
            'stages' => fn ($q) => $q->orderBy('id'),
            'stages.machine',
            'productionMaterials.material',
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

        // Estimated material usage from the recipe × planned qty (shown before completion).
        $bahan = $production->product->materials->map(fn ($line) => [
            'name' => $line->material?->name ?? '—',
            'unit' => $line->material?->unit ?? '',
            'per_unit' => (int) $line->qty_required,
            'est' => (int) $line->qty_required * (int) $production->planned_qty,
        ]);

        return view('admin.productions.show', compact('production', 'machinesByStage', 'bahan'));
    }

    public function edit(Production $production)
    {
        $production->load(['machine']);
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
            'planned_qty' => ['required', 'integer', 'min:1'],
            'actual_qty' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(Production::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        $minPlanned = (int) $production->stages()->max('input_qty');
        if ((int) $data['planned_qty'] < $minPlanned) {
            return back()->withErrors([
                'planned_qty' => 'Target produksi tidak boleh lebih kecil dari input tahap yang sudah berjalan ('.$minPlanned.' pcs).',
            ])->withInput();
        }

        $production->fill([
            'production_machine_id' => $data['production_machine_id'] ?? null,
            'planned_qty' => $data['planned_qty'],
            'actual_qty' => $data['actual_qty'] ?? $production->actual_qty,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ])->save();

        return redirect()->route('admin.productions.show', $production->id)
            ->with('success', 'Batch produksi diperbarui.');
    }

    public function destroy(Production $production)
    {
        if (! in_array($production->status, ['planned', 'cancelled'], true)) {
            return back()->with('error', 'Batch yang sudah berjalan tidak bisa dihapus. Tandai sebagai cancelled.');
        }

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

        DB::transaction(function () use ($production, $stage, $action, $data, $qtyEditable) {
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
                $this->completeBatch($production, $stage);
            }
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Tahap diperbarui.');
    }

    /** Alasan sebuah tahap masih terkunci, dalam kalimat yang bisa dibaca operator. */
    private function lockReason(Production $production, ProductionStage $stage): string
    {
        if ($stage->phase === 'mass' && $production->hasSamplePhase() && ! $production->sampleApproved()) {
            return 'Sampel belum disetujui. Selesaikan dan setujui sampel sebelum memulai produksi massal.';
        }

        if ($stage->phase === 'mass') {
            return 'Tahap sebelumnya belum mencapai 50%. Belum bisa dimulai.';
        }

        return 'Tahap sebelumnya belum selesai. Belum bisa dimulai.';
    }

    /**
     * Finalize a batch: credit product stock and consume recipe materials.
     * Sampel memakan bahan tapi tidak masuk stok jual.
     * Guarded by status so it can only run once.
     */
    private function completeBatch(Production $production, ProductionStage $qcPacking): void
    {
        $actual = (int) $qcPacking->output_qty;

        $production->forceFill([
            'status' => 'completed',
            'actual_qty' => $actual,
            'end_date' => $production->end_date ?? now()->toDateString(),
        ])->save();

        Product::where('id', $production->product_id)->increment('stock', $actual);

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
