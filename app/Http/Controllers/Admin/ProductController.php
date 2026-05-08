<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index() { return view('admin.products.index'); }

    /** AJAX endpoint for DataTables */
    public function data(Request $request)
    {
        // TODO: replace with server-side query
        $rows = collect(range(1, 24))->map(fn($i) => [
            'id'       => $i,
            'sku'      => 'SKU-'.str_pad($i, 4, '0', STR_PAD_LEFT),
            'name'     => 'Produk Demo '.$i,
            'category' => ['Gamis','Koko','Tunik','Hijab'][$i % 4],
            'price'    => number_format(rand(80,400)*1000, 0, ',', '.'),
            'stock'    => rand(0, 200),
            'status'   => $i % 5 === 0 ? 'inactive' : 'active',
        ]);

        return response()->json(['data' => $rows]);
    }

    public function create() {
        $categories = collect([
            (object)['id'=>1,'name'=>'Gamis'],(object)['id'=>2,'name'=>'Koko'],
            (object)['id'=>3,'name'=>'Tunik'],(object)['id'=>4,'name'=>'Hijab'],
        ]);
        return view('admin.products.create', compact('categories'));
    }

    public function store(Request $request) { return redirect()->route('admin.products.index')->with('success','Produk berhasil ditambahkan'); }

    public function show($id) {
        $product = (object)[
            'id'=>$id,'name'=>'Gamis Anaya Navy','sku'=>'GAM-NVY-001',
            'category'=>'Gamis','price'=>225000,'stock'=>48,'is_active'=>true,
            'description'=>'Gamis berbahan katun premium, jahitan rapi, cocok untuk daily wear.',
            'image'=>null,
            'sizes'=>[
                ['size'=>'S','chest'=>92,'length'=>135,'sleeve'=>56,'stock'=>12],
                ['size'=>'M','chest'=>96,'length'=>137,'sleeve'=>57,'stock'=>18],
                ['size'=>'L','chest'=>100,'length'=>139,'sleeve'=>58,'stock'=>14],
                ['size'=>'XL','chest'=>104,'length'=>141,'sleeve'=>59,'stock'=>4],
            ],
        ];
        return view('admin.products.show', compact('product'));
    }

    public function edit($id) {
        $product = (object)[
            'id'=>$id,'category_id'=>1,'name'=>'Gamis Anaya Navy','sku'=>'GAM-NVY-001',
            'price'=>225000,'stock'=>48,'is_active'=>true,'description'=>'Gamis berbahan katun premium.',
        ];
        $categories = collect([
            (object)['id'=>1,'name'=>'Gamis'],(object)['id'=>2,'name'=>'Koko'],
            (object)['id'=>3,'name'=>'Tunik'],(object)['id'=>4,'name'=>'Hijab'],
        ]);
        return view('admin.products.edit', compact('product','categories'));
    }

    public function update(Request $request, $id) { return redirect()->route('admin.products.index')->with('success','Produk berhasil diperbarui'); }
    public function destroy($id) { return redirect()->route('admin.products.index')->with('success','Produk berhasil dihapus'); }
}
