<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Product;
use App\Models\Production;
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
        $materials = Material::orderBy('name')->get(['id', 'name', 'unit', 'stock']);

        return view('admin.productions.create', compact('products', 'materials'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'planned_qty' => ['required', 'integer', 'min:1'],
            'code' => ['nullable', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
            'materials' => ['nullable', 'array'],
        ]);

        $production = DB::transaction(function () use ($request, $data) {
            $production = Production::create([
                'product_id' => $data['product_id'],
                'user_id' => auth()->id(),
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

            foreach ($request->input('materials', []) as $materialId => $row) {
                if (empty($row['use']) || empty($row['qty'])) {
                    continue;
                }

                $qty = (int) $row['qty'];
                if ($qty <= 0) {
                    continue;
                }

                $production->materials()->create([
                    'material_id' => $materialId,
                    'qty_used' => $qty,
                ]);

                Material::where('id', $materialId)->decrement('stock', $qty);
            }

            foreach (ProductionStage::STAGES as $stage) {
                $production->stages()->create([
                    'stage' => $stage,
                    'status' => 'pending',
                ]);
            }

            return $production;
        });

        return redirect()->route('admin.productions.show', $production->id)
            ->with('success', 'Batch produksi dibuat.');
    }

    public function show(Production $production)
    {
        $production->load(['product', 'materials.material', 'stages' => fn ($q) => $q->orderBy('id')]);

        return view('admin.productions.show', compact('production'));
    }

    public function edit(Production $production)
    {
        $production->load(['materials.material']);
        $products = Product::orderBy('name')->get(['id', 'name']);

        return view('admin.productions.edit', compact('production', 'products'));
    }

    public function update(Request $request, Production $production)
    {
        $data = $request->validate([
            'planned_qty' => ['required', 'integer', 'min:1'],
            'actual_qty' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(Production::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        $production->fill([
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
     * Toggle a stage between pending → in_progress → completed.
     * When the final stage (packing) hits completed, mark the production
     * as completed and credit the actual_qty back to product stock.
     */
    public function updateStage(Request $request, Production $production, ProductionStage $stage)
    {
        abort_unless($stage->production_id === $production->id, 404);

        $action = $request->input('action', 'next');

        DB::transaction(function () use ($production, $stage, $action) {
            if ($action === 'start' && $stage->status === 'pending') {
                $stage->forceFill(['status' => 'in_progress', 'started_at' => now()])->save();
                if ($production->status === 'planned') {
                    $production->status = 'in_progress';
                    $production->save();
                }
            } elseif ($action === 'finish' && $stage->status === 'in_progress') {
                $stage->forceFill(['status' => 'completed', 'finished_at' => now()])->save();

                if ($stage->stage === 'qc' && $production->status !== 'qc') {
                    $production->status = 'qc';
                    $production->save();
                }

                if ($stage->stage === 'packing') {
                    $actual = (int) ($production->actual_qty > 0 ? $production->actual_qty : $production->planned_qty);
                    $production->forceFill([
                        'status' => 'completed',
                        'actual_qty' => $actual,
                        'end_date' => $production->end_date ?? now()->toDateString(),
                    ])->save();

                    Product::where('id', $production->product_id)->increment('stock', $actual);
                }
            }
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Tahap diperbarui.');
    }
}
