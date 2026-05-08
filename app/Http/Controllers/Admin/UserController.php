<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = collect([
            (object)['id'=>1,'name'=>'Pemilik Toko','email'=>'admin@labasa.test','role'=>'admin','phone'=>'081200000001'],
            (object)['id'=>2,'name'=>'Karyawan Produksi','email'=>'karyawan@labasa.test','role'=>'karyawan','phone'=>'081200000002'],
            (object)['id'=>3,'name'=>'Siti Nurhaliza','email'=>'pembeli@labasa.test','role'=>'pembeli','phone'=>'081200000003'],
            (object)['id'=>4,'name'=>'Ahmad Fauzi','email'=>'ahmad@example.com','role'=>'pembeli','phone'=>'081200000004'],
        ]);
        return view('admin.users.index', compact('users'));
    }
    public function create() { return view('admin.users.create'); }
    public function store(Request $request) { return redirect()->route('admin.users.index')->with('success','User ditambahkan'); }
    public function show($id) { return redirect()->route('admin.users.edit', $id); }
    public function edit($id) {
        $user = (object)['id'=>$id,'name'=>'Karyawan Produksi','email'=>'karyawan@labasa.test','role'=>'karyawan','phone'=>'081200000002','address'=>'Surabaya'];
        return view('admin.users.edit', compact('user'));
    }
    public function update(Request $request, $id) { return redirect()->route('admin.users.index')->with('success','User diperbarui'); }
    public function destroy($id) { return redirect()->route('admin.users.index')->with('success','User dihapus'); }
}
