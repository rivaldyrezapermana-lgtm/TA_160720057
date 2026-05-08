<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = collect([
            (object)['id'=>1,'name'=>'CV Tekstil Jaya','contact_person'=>'Pak Budi','phone'=>'081234567890','email'=>'tekstiljaya@example.com','address'=>'Surabaya'],
            (object)['id'=>2,'name'=>'Toko Benang Mulia','contact_person'=>'Bu Ratna','phone'=>'081300000000','email'=>'benangmulia@example.com','address'=>'Sidoarjo'],
            (object)['id'=>3,'name'=>'PT Kancing Sentosa','contact_person'=>'Pak Hadi','phone'=>'081311112222','email'=>'kancing@example.com','address'=>'Malang'],
        ]);
        return view('admin.suppliers.index', compact('suppliers'));
    }
    public function create() { return view('admin.suppliers.create'); }
    public function store(Request $request) { return redirect()->route('admin.suppliers.index')->with('success','Supplier ditambahkan'); }
    public function show($id) { return redirect()->route('admin.suppliers.edit', $id); }
    public function edit($id) {
        $supplier = (object)['id'=>$id,'name'=>'CV Tekstil Jaya','contact_person'=>'Pak Budi','phone'=>'081234567890','email'=>'tekstiljaya@example.com','address'=>'Surabaya'];
        return view('admin.suppliers.edit', compact('supplier'));
    }
    public function update(Request $request, $id) { return redirect()->route('admin.suppliers.index')->with('success','Supplier diperbarui'); }
    public function destroy($id) { return redirect()->route('admin.suppliers.index')->with('success','Supplier dihapus'); }
}
