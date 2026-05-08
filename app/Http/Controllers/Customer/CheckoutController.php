<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function index()
    {
        $items = collect([
            (object)['product'=>'Gamis Anaya Navy','size'=>'M','price'=>225000,'qty'=>1,'subtotal'=>225000],
            (object)['product'=>'Hijab Pashmina Plain','size'=>'-','price'=>50000,'qty'=>4,'subtotal'=>200000],
        ]);
        $total = $items->sum('subtotal');
        $shipping = 25000;
        return view('customer.cart.checkout', compact('items','total','shipping'));
    }

    public function store(Request $request)
    {
        // TODO: create real Order + OrderItems
        return redirect()->route('checkout.success', 1)->with('success','Pesanan berhasil dibuat');
    }

    public function success($order)
    {
        $order = (object)[
            'id'=>$order,'code'=>'ORD-2026-1209',
            'total'=>450000,'bank'=>'BCA 1234567890 a/n Toko Labasa',
        ];
        return view('customer.cart.success', compact('order'));
    }

    public function uploadProof(Request $request, $order)
    {
        // TODO: store uploaded proof to storage/app/public/proofs
        return redirect()->route('customer.orders.show', $order)->with('success','Bukti pembayaran terupload');
    }
}
