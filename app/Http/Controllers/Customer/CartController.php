<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $items = collect([
            (object)['id'=>1,'product'=>'Gamis Anaya Navy','size'=>'M','price'=>225000,'qty'=>1,'subtotal'=>225000],
            (object)['id'=>2,'product'=>'Hijab Pashmina Plain','size'=>'-','price'=>50000,'qty'=>4,'subtotal'=>200000],
        ]);
        $total = $items->sum('subtotal');
        return view('customer.cart.index', compact('items','total'));
    }

    public function add(Request $request) { return back()->with('success','Produk ditambahkan ke keranjang'); }
    public function update(Request $request, $item) { return back()->with('success','Keranjang diperbarui'); }
    public function remove($item) { return back()->with('success','Item dihapus'); }
}
