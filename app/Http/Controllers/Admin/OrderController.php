<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index()
    {
        return view('admin.orders.index');
    }

    /** AJAX endpoint for DataTables. */
    public function data(Request $request)
    {
        $rows = Order::with('user')
            ->latest()
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'code' => $o->code,
                'customer' => $o->user?->name ?? '—',
                'date' => optional($o->created_at)->translatedFormat('d M Y H:i'),
                'total' => 'Rp '.number_format((float) $o->total, 0, ',', '.'),
                'status' => $o->status,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function show(Order $order)
    {
        $order->load(['user', 'items.product', 'payment']);

        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
        ]);

        $order->status = $data['status'];
        $order->save();

        return back()->with('success', 'Status pesanan diperbarui.');
    }

    /**
     * Mark the payment as verified, flip the order to "paid", and decrement
     * the stock for each ordered product. Wrapped in a transaction so partial
     * failures don't leave inventory in a bad state.
     */
    public function verifyPayment(Request $request, Order $order)
    {
        $order->load(['items', 'payment']);

        if (! $order->payment) {
            return back()->with('error', 'Pesanan ini belum memiliki data pembayaran.');
        }

        if ($order->payment->status === 'verified') {
            return back()->with('error', 'Pembayaran sudah diverifikasi sebelumnya.');
        }

        DB::transaction(function () use ($order) {
            $order->payment->forceFill([
                'status' => 'verified',
                'paid_at' => now(),
            ])->save();

            $order->status = 'paid';
            $order->save();

            foreach ($order->items as $item) {
                Product::where('id', $item->product_id)
                    ->decrement('stock', $item->qty);
            }
        });

        return back()->with('success', 'Pembayaran diverifikasi, stok produk dipotong.');
    }
}
