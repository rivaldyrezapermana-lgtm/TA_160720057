<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $orders = collect([
            (object)['id'=>1,'code'=>'ORD-2026-1209','date'=>'05 May 2026','total'=>450000,'status'=>'paid'],
            (object)['id'=>2,'code'=>'ORD-2026-1188','date'=>'28 Apr 2026','total'=>225000,'status'=>'completed'],
            (object)['id'=>3,'code'=>'ORD-2026-1170','date'=>'15 Apr 2026','total'=>670000,'status'=>'completed'],
        ]);
        return view('customer.orders.index', compact('orders'));
    }

    public function show($order)
    {
        $order = (object)[
            'id'=>$order,'code'=>'ORD-2026-1209','date'=>'05 May 2026, 14:32',
            'status'=>'paid','total'=>450000,
            'shipping_address'=>'Jl. Pahlawan No. 123, Surabaya',
            'payment'=>['method'=>'transfer','status'=>'verified','proof'=>true],
            'items'=>[
                ['product'=>'Gamis Anaya Navy','size'=>'M','qty'=>1,'price'=>225000,'subtotal'=>225000],
                ['product'=>'Hijab Pashmina Plain','size'=>'-','qty'=>4,'price'=>50000,'subtotal'=>200000],
            ],
            'timeline'=>[
                ['label'=>'Pesanan Dibuat','time'=>'05 May 14:32','done'=>true],
                ['label'=>'Pembayaran Diverifikasi','time'=>'05 May 15:00','done'=>true],
                ['label'=>'Sedang Diproses','time'=>'06 May 09:00','done'=>true],
                ['label'=>'Dikirim','time'=>'-','done'=>false],
                ['label'=>'Selesai','time'=>'-','done'=>false],
            ],
        ];
        return view('customer.orders.show', compact('order'));
    }
}
