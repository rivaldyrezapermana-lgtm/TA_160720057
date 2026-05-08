<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index() { return view('admin.purchases.index'); }

    public function data(Request $request)
    {
        $rows = collect(range(1, 12))->map(fn($i) => [
            'id'=>$i,'code'=>'PO-2026-'.str_pad($i,4,'0',STR_PAD_LEFT),
            'supplier'=>['CV Tekstil Jaya','Toko Benang Mulia','PT Kancing Sentosa'][$i % 3],
            'date'=>now()->subDays($i*3)->format('d M Y'),
            'total'=>'Rp '.number_format(rand(800,5000)*1000, 0, ',', '.'),
            'status'=>['pending','received','cancelled'][$i % 3],
        ]);
        return response()->json(['data'=>$rows]);
    }

    public function create()
    {
        $suppliers = collect([
            (object)['id'=>1,'name'=>'CV Tekstil Jaya'],
            (object)['id'=>2,'name'=>'Toko Benang Mulia'],
            (object)['id'=>3,'name'=>'PT Kancing Sentosa'],
        ]);
        $materials = collect([
            (object)['id'=>1,'name'=>'Kain Katun Premium','unit'=>'meter','unit_cost'=>45000],
            (object)['id'=>2,'name'=>'Benang Hitam','unit'=>'roll','unit_cost'=>15000],
            (object)['id'=>3,'name'=>'Kancing Bulat','unit'=>'pcs','unit_cost'=>500],
        ]);
        return view('admin.purchases.create', compact('suppliers','materials'));
    }

    public function store(Request $request) { return redirect()->route('admin.purchases.index')->with('success','Purchase order dibuat'); }

    public function show($id)
    {
        $purchase = (object)[
            'id'=>$id,'code'=>'PO-2026-0007','supplier'=>'CV Tekstil Jaya',
            'date'=>'24 Apr 2026','status'=>'pending','total'=>'Rp 4.500.000',
            'items'=>[
                ['material'=>'Kain Katun Premium','qty'=>50,'unit'=>'meter','unit_cost'=>45000,'subtotal'=>2250000],
                ['material'=>'Benang Hitam','qty'=>20,'unit'=>'roll','unit_cost'=>15000,'subtotal'=>300000],
                ['material'=>'Kancing Bulat','qty'=>3900,'unit'=>'pcs','unit_cost'=>500,'subtotal'=>1950000],
            ],
        ];
        return view('admin.purchases.show', compact('purchase'));
    }

    public function edit($id) { return $this->show($id); }
    public function update(Request $request, $id) { return redirect()->route('admin.purchases.index')->with('success','PO diperbarui'); }
    public function destroy($id) { return redirect()->route('admin.purchases.index')->with('success','PO dihapus'); }
}
