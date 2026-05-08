<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index() { return view('admin.orders.index'); }

    public function data(Request $request)
    {
        $statuses = ['pending','paid','processing','shipped','completed','cancelled'];
        $rows = collect(range(1, 22))->map(fn($i) => [
            'id'=>$i,'code'=>'ORD-2026-'.str_pad(1200+$i,4,'0',STR_PAD_LEFT),
            'customer'=>['Siti Nurhaliza','Ahmad Fauzi','Diana Putri','Rahmat Hidayat','Lina Susanti'][$i % 5],
            'date'=>now()->subDays($i)->format('d M Y H:i'),
            'total'=>'Rp '.number_format(rand(150,800)*1000, 0, ',', '.'),
            'status'=>$statuses[$i % 6],
        ]);
        return response()->json(['data'=>$rows]);
    }

    public function show($id)
    {
        $order = (object)[
            'id'=>$id,'code'=>'ORD-2026-1209','customer'=>'Siti Nurhaliza',
            'phone'=>'081234567890','email'=>'siti@example.com',
            'date'=>'05 May 2026, 14:32',
            'shipping_address'=>'Jl. Pahlawan No. 123, Surabaya',
            'status'=>'paid','total'=>425000,
            'payment'=>['method'=>'transfer','status'=>'verified','proof'=>'uploaded','paid_at'=>'05 May 2026, 15:00'],
            'items'=>[
                ['product'=>'Gamis Anaya Navy','size'=>'M','qty'=>1,'price'=>225000,'subtotal'=>225000],
                ['product'=>'Hijab Pashmina Plain','size'=>'-','qty'=>4,'price'=>50000,'subtotal'=>200000],
            ],
        ];
        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, $order)
    {
        return back()->with('success','Status pesanan diperbarui');
    }

    public function verifyPayment(Request $request, $order)
    {
        return back()->with('success','Pembayaran diverifikasi');
    }
}
