<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;

class PurchaseController extends Controller
{
    private const NO_ITEMS_MESSAGE = 'Pilih minimal satu bahan beserta jumlahnya.';

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
        $materials = Material::orderBy('name')->get(['id', 'name', 'unit']);

        return view('admin.purchases.create', compact('suppliers', 'materials'));
    }

    public function store(Request $request)
    {
        $submitted = $request->input('items');
        $submitted = is_array($submitted) ? $submitted : [];
        $items = collect($submitted)->filter(
            fn ($row) => is_array($row) && (filled($row['material_id'] ?? null) || filled($row['qty'] ?? null))
        );

        $data = $this->storeValidator($request, $items, array_keys($submitted))->validate();

        if ($items->isEmpty()) {
            return back()->withInput()->with('error', self::NO_ITEMS_MESSAGE);
        }

        $purchase = DB::transaction(function () use ($data, $items) {
            $code = $data['code'] ?? null;

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'code' => $code ?: 'TMP',
                'purchase_date' => $data['purchase_date'],
                'total' => 0,
                'status' => 'pending',
            ]);

            if (empty($code)) {
                $purchase->forceFill(['code' => 'PO-'.now()->year.'-'.str_pad((string) $purchase->id, 4, '0', STR_PAD_LEFT)])->save();
            }

            $total = 0;

            foreach ($items as $row) {
                $qty = (int) $row['qty'];
                $unitCost = round((float) $row['unit_cost'], 2);
                $subtotal = round($qty * $unitCost, 2);

                $purchase->items()->create([
                    'material_id' => $row['material_id'],
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
            ->with('success', 'Pesanan Pembelian berhasil dibuat.');
    }

    /**
     * Only filled rows get item rules; row keys are kept so errors map back to their form row.
     *
     * @param  array<int, int|string>  $submittedKeys  Keys of every submitted row, in form order.
     */
    private function storeValidator(Request $request, Collection $items, array $submittedKeys): ValidationValidator
    {
        $rules = [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date'],
            'code' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array'],
        ];
        $attributes = [];
        $positions = array_flip($submittedKeys);

        foreach ($items->keys() as $i) {
            $row = $positions[$i] + 1;

            $rules["items.$i.material_id"] = ['bail', 'required', 'integer', 'exists:materials,id'];
            $rules["items.$i.qty"] = ['bail', 'required', 'integer', 'min:1', 'max:2147483647'];
            $rules["items.$i.unit_cost"] = ['bail', 'required', 'numeric', 'min:0', 'max:9999999999.99'];

            $attributes["items.$i.material_id"] = "Bahan pada baris $row";
            $attributes["items.$i.qty"] = "Jumlah pada baris $row";
            $attributes["items.$i.unit_cost"] = "Harga per unit pada baris $row";
        }

        $messages = [
            'items.required' => self::NO_ITEMS_MESSAGE,
            'items.*.material_id.required' => ':attribute wajib dipilih.',
            'items.*.material_id.integer' => ':attribute tidak valid.',
            'items.*.material_id.exists' => ':attribute tidak valid.',
            'items.*.qty.required' => ':attribute wajib diisi.',
            'items.*.qty.integer' => ':attribute harus berupa bilangan bulat.',
            'items.*.qty.min' => ':attribute minimal :min.',
            'items.*.qty.max' => ':attribute tidak boleh lebih dari :max.',
            'items.*.unit_cost.required' => ':attribute wajib diisi.',
            'items.*.unit_cost.numeric' => ':attribute harus berupa angka.',
            'items.*.unit_cost.min' => ':attribute tidak boleh negatif.',
            'items.*.unit_cost.max' => ':attribute tidak boleh lebih dari :max.',
        ];

        return Validator::make($request->all(), $rules, $messages, $attributes)
            ->after(function (ValidationValidator $validator) use ($items) {
                $materialIds = $items->pluck('material_id')
                    ->filter(fn ($id) => is_scalar($id) && filled($id))
                    ->map(fn ($id) => (string) $id);

                if ($materialIds->count() !== $materialIds->unique()->count()) {
                    $validator->errors()->add('items', 'Bahan yang sama tidak boleh dipilih lebih dari satu kali.');
                }
            });
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

        return back()->with('success', 'Pesanan Pembelian diperbarui.');
    }

    public function destroy(Purchase $purchase)
    {
        if ($purchase->status === 'received') {
            return back()->with('error', 'Pesanan Pembelian yang sudah diterima tidak dapat dihapus.');
        }

        $purchase->delete();

        return redirect()->route('admin.purchases.index')->with('success', 'Pesanan Pembelian dihapus.');
    }

    /**
     * Mark a PO as received: stock goes up and each material's price becomes
     * the moving average, which flows into product HPP.
     */
    public function receive(Purchase $purchase)
    {
        if ($purchase->status === 'received') {
            return back()->with('error', 'Pesanan Pembelian sudah diterima sebelumnya.');
        }

        if ($purchase->status === 'cancelled') {
            return back()->with('error', 'Pesanan Pembelian sudah dibatalkan.');
        }

        DB::transaction(function () use ($purchase) {
            $purchase->load('items');
            foreach ($purchase->items as $item) {
                Material::whereKey($item->material_id)->lockForUpdate()->first()
                    ?->receivePurchase((int) $item->qty, (float) $item->unit_cost);
            }
            $purchase->status = 'received';
            $purchase->save();
        });

        return back()->with('success', 'Pesanan Pembelian ditandai diterima. Stok dan harga rata-rata bahan diperbarui.');
    }

    public function cancel(Purchase $purchase)
    {
        if ($purchase->status === 'received') {
            return back()->with('error', 'Pesanan Pembelian yang sudah diterima tidak dapat dibatalkan.');
        }

        $purchase->status = 'cancelled';
        $purchase->save();

        return back()->with('success', 'Pesanan Pembelian dibatalkan.');
    }
}
