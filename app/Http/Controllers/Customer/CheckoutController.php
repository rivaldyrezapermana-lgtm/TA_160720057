<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    private const SHIPPING_FLAT = 25000;

    private const BANK_INFO = 'BCA 1234567890 a/n Toko Labasa';

    /** Single source of truth for the bank details shown in checkout + success pages. */
    public static function bankInfo(): string
    {
        return self::BANK_INFO;
    }

    public function __construct(private CartService $cart) {}

    public function index()
    {
        $items = $this->cart->items();
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang kosong.');
        }

        $total = $this->cart->total();
        $shipping = self::SHIPPING_FLAT;

        return view('customer.cart.checkout', compact('items', 'total', 'shipping'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'recipient' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'payment_method' => ['required', 'in:transfer'],
        ]);

        $items = $this->cart->items();
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang kosong.');
        }

        $shipping = self::SHIPPING_FLAT;
        $itemsTotal = (float) $items->sum('subtotal');
        $grandTotal = $itemsTotal + $shipping;

        $order = DB::transaction(function () use ($data, $items, $grandTotal) {
            $order = Order::create([
                'user_id' => auth()->id(),
                'code' => 'TMP',
                'total' => $grandTotal,
                'status' => 'pending',
                'shipping_address' => $data['recipient']."\n".$data['phone']."\n".$data['shipping_address'],
            ]);

            $order->forceFill(['code' => 'ORD-'.now()->year.'-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT)])->save();

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'size' => $item->size === '-' ? null : $item->size,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ]);
            }

            Payment::create([
                'order_id' => $order->id,
                'method' => $data['payment_method'],
                'amount' => $grandTotal,
                'status' => 'pending',
            ]);

            return $order;
        });

        $this->cart->clear();

        return redirect()->route('checkout.success', $order->id)
            ->with('success', 'Pesanan berhasil dibuat.');
    }

    public function success(Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $order->bank = self::BANK_INFO;

        return view('customer.cart.success', compact('order'));
    }

    public function uploadProof(Request $request, Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        if ($order->payment?->status === 'verified') {
            return redirect()->route('customer.orders.show', $order->id)
                ->with('error', 'Pembayaran sudah diverifikasi, tidak bisa diubah.');
        }

        $request->validate([
            'proof' => ['required', 'image', 'max:2048'],
        ]);

        $path = $request->file('proof')->store('proofs', 'public');

        $payment = $order->payment ?? Payment::create([
            'order_id' => $order->id,
            'method' => 'transfer',
            'amount' => $order->total,
            'status' => 'pending',
        ]);

        $payment->forceFill([
            'proof_image' => $path,
            'status' => 'pending',
            'note' => null,
        ])->save();

        return redirect()->route('customer.orders.show', $order->id)
            ->with('success', 'Bukti pembayaran terupload. Menunggu verifikasi admin.');
    }
}
