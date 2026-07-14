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
                'code' => $data['code'] ?: 'TMP',
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

            foreach (ProductionStage::STAGES as $i => $stage) {
                $production->stages()->create([
                    'stage' => $stage,
                    'status' => 'pending',
                    'input_qty' => $i === 0 ? (int) $data['planned_qty'] : 0,
                    'output_qty' => 0,
                ]);
            }

            return $production;
        });

        return redirect()->route('admin.productions.show', $production->id)
            ->with('success', 'Batch produksi dibuat.');
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
        $data = $request->validate([
            'production_machine_id' => ['nullable', 'exists:production_machines,id'],
            'planned_qty' => ['required', 'integer', 'min:1'],
            'actual_qty' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(Production::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

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
     * Update a stage's input/output quantities and machine, honoring the 50%
     * handoff gate. Finishing the final (packing) stage completes the batch:
     * product stock is credited and recipe materials are consumed — exactly once.
     */
    public function updateStage(Request $request, Production $production, ProductionStage $stage)
    {
        abort_unless($stage->production_id === $production->id, 404);
        $production->load(['stages', 'product.materials']);
        $stage = $production->stages->firstWhere('id', $stage->id);

        $action = $request->input('action', 'save');

        $data = $request->validate([
            'input_qty' => ['nullable', 'integer', 'min:0'],
            'output_qty' => ['nullable', 'integer', 'min:0', 'lte:input_qty'],
            'production_machine_id' => ['nullable', 'exists:production_machines,id'],
        ], [
            'output_qty.lte' => 'Output tidak boleh melebihi input.',
        ]);

        // Machine must belong to the category mapped to this stage.
        if (! empty($data['production_machine_id'])) {
            $machine = ProductionMachine::with('category')->find($data['production_machine_id']);
            if (! $machine || $machine->category?->stage !== $stage->stage) {
                return back()->with('error', 'Mesin yang dipilih tidak sesuai dengan tahap ini.');
            }
        }

        // Gate check when starting a non-first stage.
        if ($action === 'start' && ! $production->stageUnlocked($stage)) {
            return back()->with('error', 'Tahap sebelumnya belum mencapai 50%. Belum bisa dimulai.');
        }

        DB::transaction(function () use ($production, $stage, $action, $data) {
            $stage->fill([
                'input_qty' => $data['input_qty'] ?? $stage->input_qty,
                'output_qty' => $data['output_qty'] ?? $stage->output_qty,
                'production_machine_id' => $data['production_machine_id'] ?? $stage->production_machine_id,
            ]);

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
            }

            $stage->save();

            if ($stage->stage === 'qc' && $action === 'finish' && ! in_array($production->status, ['completed'], true)) {
                $production->forceFill(['status' => 'qc'])->save();
            }

            if ($stage->stage === 'packing' && $action === 'finish' && $production->status !== 'completed') {
                $this->completeBatch($production, $stage);
            }
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Tahap diperbarui.');
    }

    /**
     * Finalize a batch: credit product stock and consume recipe materials.
     * Guarded by status so it can only run once.
     */
    private function completeBatch(Production $production, ProductionStage $packing): void
    {
        $actual = (int) $packing->output_qty;

        $production->forceFill([
            'status' => 'completed',
            'actual_qty' => $actual,
            'end_date' => $production->end_date ?? now()->toDateString(),
        ])->save();

        Product::where('id', $production->product_id)->increment('stock', $actual);

        foreach ($production->product->materials as $line) {
            $used = (int) $line->qty_required * $actual;
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
