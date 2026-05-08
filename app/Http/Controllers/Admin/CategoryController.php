<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        // TODO: replace with Category::paginate()
        $categories = collect([
            (object)['id'=>1,'name'=>'Gamis','slug'=>'gamis','description'=>'Gamis muslim wanita','products_count'=>32],
            (object)['id'=>2,'name'=>'Koko','slug'=>'koko','description'=>'Baju koko pria','products_count'=>21],
            (object)['id'=>3,'name'=>'Tunik','slug'=>'tunik','description'=>'Tunik casual','products_count'=>18],
            (object)['id'=>4,'name'=>'Hijab','slug'=>'hijab','description'=>'Pashmina & segi empat','products_count'=>27],
        ]);

        return view('admin.categories.index', compact('categories'));
    }

    public function create()  { return view('admin.categories.create'); }
    public function store(Request $request) { return redirect()->route('admin.categories.index')->with('success','Kategori berhasil ditambahkan'); }
    public function edit($id) { $category = (object)['id'=>$id,'name'=>'Gamis','slug'=>'gamis','description'=>'Gamis muslim wanita']; return view('admin.categories.edit', compact('category')); }
    public function update(Request $request, $id) { return redirect()->route('admin.categories.index')->with('success','Kategori berhasil diperbarui'); }
    public function destroy($id) { return redirect()->route('admin.categories.index')->with('success','Kategori berhasil dihapus'); }
    public function show($id) { return redirect()->route('admin.categories.edit', $id); }
}
