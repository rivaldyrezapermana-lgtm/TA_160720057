<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index() { return view('admin.materials.index'); }

    public function data(Request $request)
    {
        $units = ['meter','roll','pcs','kg'];
        $names = ['Kain Katun Premium','Kain Rayon','Benang Hitam','Benang Putih','Kancing Bulat','Resleting 30cm','Label Brand','Plastik Packing'];
        $rows = collect($names)->map(function($n,$i) use ($units) {
            $stock = rand(0, 120); $min = rand(20, 50);
            return [
                'id'=>$i+1,'code'=>'MAT-'.str_pad($i+1,4,'0',STR_PAD_LEFT),
                'name'=>$n,'unit'=>$units[$i % 4],
                'stock'=>$stock,'min_stock'=>$min,
                'unit_cost'=>number_format(rand(2,80)*1000, 0, ',', '.'),
                'status'=>$stock <= $min ? 'low' : 'ok',
            ];
        });
        return response()->json(['data'=>$rows]);
    }

    public function create() { return view('admin.materials.create'); }
    public function store(Request $request) { return redirect()->route('admin.materials.index')->with('success','Bahan baku ditambahkan'); }
    public function show($id) { return redirect()->route('admin.materials.edit', $id); }
    public function edit($id) {
        $material = (object)['id'=>$id,'code'=>'MAT-0001','name'=>'Kain Katun Premium','unit'=>'meter','stock'=>12,'min_stock'=>30,'unit_cost'=>45000];
        return view('admin.materials.edit', compact('material'));
    }
    public function update(Request $request, $id) { return redirect()->route('admin.materials.index')->with('success','Bahan baku diperbarui'); }
    public function destroy($id) { return redirect()->route('admin.materials.index')->with('success','Bahan baku dihapus'); }
}
