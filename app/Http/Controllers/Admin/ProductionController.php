<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function index() { return view('admin.productions.index'); }

    public function data(Request $request)
    {
        $statuses = ['planned','in_progress','qc','completed','cancelled'];
        $products = ['Gamis Anaya Navy','Koko Modern Sage','Tunik Basic Cream','Hijab Pashmina'];
        $rows = collect(range(1, 18))->map(fn($i) => [
            'id'=>$i,'code'=>'PRD-2026-'.str_pad($i+20,4,'0',STR_PAD_LEFT),
            'product'=>$products[$i % 4],
            'planned'=>rand(50,300),'actual'=>rand(0,300),
            'start'=>now()->subDays($i*2)->format('d M'),
            'end'=>now()->subDays($i*2 - 7)->format('d M'),
            'status'=>$statuses[$i % 5],
        ]);
        return response()->json(['data'=>$rows]);
    }

    public function create()
    {
        $products = collect([
            (object)['id'=>1,'name'=>'Gamis Anaya Navy'],
            (object)['id'=>2,'name'=>'Koko Modern Sage'],
            (object)['id'=>3,'name'=>'Tunik Basic Cream'],
        ]);
        $materials = collect([
            (object)['id'=>1,'name'=>'Kain Katun Premium','unit'=>'meter'],
            (object)['id'=>2,'name'=>'Benang Hitam','unit'=>'roll'],
            (object)['id'=>3,'name'=>'Kancing Bulat','unit'=>'pcs'],
        ]);
        return view('admin.productions.create', compact('products','materials'));
    }

    public function store(Request $request) { return redirect()->route('admin.productions.index')->with('success','Produksi dibuat'); }

    public function show($id)
    {
        $production = (object)[
            'id'=>$id,'code'=>'PRD-2026-0042','product'=>'Gamis Anaya Navy',
            'planned'=>200,'actual'=>124,'status'=>'in_progress',
            'start'=>'01 May 2026','end'=>'15 May 2026',
            'notes'=>'Batch produksi periode Mei minggu pertama.',
            'materials'=>[
                ['name'=>'Kain Katun Premium','used'=>180,'unit'=>'meter'],
                ['name'=>'Benang Hitam','used'=>12,'unit'=>'roll'],
                ['name'=>'Kancing Bulat','used'=>1200,'unit'=>'pcs'],
            ],
            'stages'=>[
                ['stage'=>'design','status'=>'completed','started_at'=>'01 May 09:00','finished_at'=>'01 May 12:00'],
                ['stage'=>'sample','status'=>'completed','started_at'=>'01 May 13:00','finished_at'=>'02 May 16:00'],
                ['stage'=>'cutting','status'=>'completed','started_at'=>'03 May 08:00','finished_at'=>'04 May 17:00'],
                ['stage'=>'sewing','status'=>'in_progress','started_at'=>'05 May 08:00','finished_at'=>null],
                ['stage'=>'qc','status'=>'pending','started_at'=>null,'finished_at'=>null],
                ['stage'=>'packing','status'=>'pending','started_at'=>null,'finished_at'=>null],
            ],
        ];
        return view('admin.productions.show', compact('production'));
    }

    public function edit($id) { return $this->show($id); }
    public function update(Request $request, $id) { return redirect()->route('admin.productions.show', $id)->with('success','Produksi diperbarui'); }
    public function destroy($id) { return redirect()->route('admin.productions.index')->with('success','Produksi dihapus'); }

    public function updateStage(Request $request, $production, $stage)
    {
        return response()->json(['ok'=>true,'message'=>"Stage {$stage} updated"]);
    }
}
