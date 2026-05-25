<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index()
    {
        return view('admin.purchases.index');
    }

    public function data(Request $request)
    {
        $rows = Purchase::with('supplier')
            ->latest('purchase_date')
            ->get()
            ->map(fn (Purchase $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'supplier' => $p->supplier?->name ?? '—',
                'date' => optional($p->purchase_date)->translatedFormat('d M Y'),
                'total' => 'Rp '.number_format((float) $p->total, 0, ',', '.'),
                'status' => $p->status,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $materials = Material::orderBy('name')->get(['id', 'name', 'unit', 'unit_cost']);

        return view('admin.purchases.create', compact('suppliers', 'materials'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date'],
            'code' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array'],
        ]);

        $items = collect($request->input('items', []))
            ->filter(fn ($row) => ! empty($row['selected']) && (int) ($row['qty'] ?? 0) > 0);

        if ($items->isEmpty()) {
            return back()->withInput()->with('error', 'Pilih minimal satu bahan dengan qty.');
        }

        $purchase = DB::transaction(function () use ($data, $items) {
            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'code' => $data['code'] ?: 'TMP',
                'purchase_date' => $data['purchase_date'],
                'total' => 0,
                'status' => 'pending',
            ]);

            if (empty($data['code'])) {
                $purchase->forceFill(['code' => 'PO-'.now()->year.'-'.str_pad((string) $purchase->id, 4, '0', STR_PAD_LEFT)])->save();
            }

            $total = 0;

            foreach ($items as $materialId => $row) {
                $material = Material::find($materialId);
                if (! $material) {
                    continue;
                }

                $qty = (int) $row['qty'];
                $unitCost = (float) $material->unit_cost;
                $subtotal = $qty * $unitCost;

                $purchase->items()->create([
                    'material_id' => $materialId,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                ]);

                $total += $subtotal;
            }

            $purchase->forceFill(['total' => $total])->save();

            return $purchase;
        });

        return redirect()->route('admin.purchases.show', $purchase->id)
            ->with('success', 'PO berhasil dibuat.');
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'items.material']);

        return view('admin.purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase)
    {
        return redirect()->route('admin.purchases.show', $purchase->id);
    }

    public function update(Request $request, Purchase $purchase)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,received,cancelled'],
        ]);

        $purchase->status = $data['status'];
        $purchase->save();

        return back()->with('success', 'PO diperbarui.');
    }

    public function destroy(Purchase $purchase)
    {
        if ($purchase->status === 'received') {
            return back()->with('error', 'PO yang sudah diterima tidak bisa dihapus.');
        }

        $purchase->delete();

        return redirect()->route('admin.purchases.index')->with('success', 'PO dihapus.');
    }

    /**
     * Mark a PO as received and credit each item's qty to material stock.
     */
    public function receive(Purchase $purchase)
    {
        if ($purchase->status === 'received') {
            return back()->with('error', 'PO sudah diterima sebelumnya.');
        }

        if ($purchase->status === 'cancelled') {
            return back()->with('error', 'PO sudah dibatalkan.');
        }

        DB::transaction(function () use ($purchase) {
            $purchase->load('items');
            foreach ($purchase->items as $item) {
                Material::where('id', $item->material_id)->increment('stock', $item->qty);
            }
            $purchase->status = 'received';
            $purchase->save();
        });

        return back()->with('success', 'PO ditandai diterima, stok bahan diperbarui.');
    }

    public function cancel(Purchase $purchase)
    {
        if ($purchase->status === 'received') {
            return back()->with('error', 'PO yang sudah diterima tidak bisa dibatalkan.');
        }

        $purchase->status = 'cancelled';
        $purchase->save();

        return back()->with('success', 'PO dibatalkan.');
    }
}
