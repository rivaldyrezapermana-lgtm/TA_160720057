<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('customer.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $order->load(['items.product', 'payment']);
        $timeline = $this->buildTimeline($order);

        return view('customer.orders.show', compact('order', 'timeline'));
    }

    /** Derive a step-by-step status timeline from the order's current state. */
    private function buildTimeline(Order $order): array
    {
        $status = $order->status;
        $paidAt = $order->payment?->paid_at;
        $updatedAt = $order->updated_at;

        $progress = match ($status) {
            'pending' => 1,
            'paid' => 2,
            'processing' => 3,
            'shipped' => 4,
            'completed' => 5,
            'cancelled' => 0,
            default => 1,
        };

        return [
            [
                'label' => 'Pesanan Dibuat',
                'time' => optional($order->created_at)->format('d M H:i') ?? '-',
                'done' => $progress >= 1,
            ],
            [
                'label' => 'Pembayaran Diverifikasi',
                'time' => $paidAt ? $paidAt->format('d M H:i') : '-',
                'done' => $progress >= 2,
            ],
            [
                'label' => 'Sedang Diproses',
                'time' => $progress >= 3 ? optional($updatedAt)->format('d M H:i') : '-',
                'done' => $progress >= 3,
            ],
            [
                'label' => 'Dikirim',
                'time' => $progress >= 4 ? optional($updatedAt)->format('d M H:i') : '-',
                'done' => $progress >= 4,
            ],
            [
                'label' => 'Selesai',
                'time' => $progress >= 5 ? optional($updatedAt)->format('d M H:i') : '-',
                'done' => $progress >= 5,
            ],
        ];
    }
}
