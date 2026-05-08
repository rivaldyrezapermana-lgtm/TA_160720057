<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function landing()
    {
        $featured = $this->fakeProducts()->take(4);
        $categories = collect([
            (object)['name'=>'Gamis','slug'=>'gamis','count'=>32],
            (object)['name'=>'Koko','slug'=>'koko','count'=>21],
            (object)['name'=>'Tunik','slug'=>'tunik','count'=>18],
            (object)['name'=>'Hijab','slug'=>'hijab','count'=>27],
        ]);
        return view('customer.shop.landing', compact('featured','categories'));
    }

    public function index(Request $request)
    {
        $products = $this->fakeProducts();

        if ($q = $request->q) {
            $products = $products->filter(fn($p) => str_contains(strtolower($p->name), strtolower($q)));
        }
        if ($cat = $request->category) {
            $products = $products->filter(fn($p) => $p->category === $cat);
        }

        $categories = ['Gamis','Koko','Tunik','Hijab'];
        return view('customer.shop.index', compact('products','categories'));
    }

    public function show($product)
    {
        $p = (object)[
            'id'=>$product,
            'name'=>'Gamis Anaya Navy',
            'category'=>'Gamis',
            'price'=>225000,
            'stock'=>48,
            'description'=>'Gamis berbahan katun premium, jahitan rapi, cocok untuk daily wear maupun acara formal. Tersedia dalam 4 ukuran.',
            'sizes'=>[
                ['size'=>'S','chest'=>92,'length'=>135,'sleeve'=>56,'stock'=>12],
                ['size'=>'M','chest'=>96,'length'=>137,'sleeve'=>57,'stock'=>18],
                ['size'=>'L','chest'=>100,'length'=>139,'sleeve'=>58,'stock'=>14],
                ['size'=>'XL','chest'=>104,'length'=>141,'sleeve'=>59,'stock'=>4],
            ],
        ];
        return view('customer.shop.show', ['product'=>$p]);
    }

    private function fakeProducts()
    {
        $names = [
            ['Gamis Anaya Navy','Gamis',225000,48],
            ['Koko Modern Sage','Koko',180000,32],
            ['Tunik Basic Cream','Tunik',145000,21],
            ['Hijab Pashmina Plain','Hijab',50000,86],
            ['Gamis Syari Rose','Gamis',320000,15],
            ['Koko Lengan Pendek Putih','Koko',155000,40],
            ['Tunik Bordir Hitam','Tunik',195000,12],
            ['Hijab Segi Empat Motif','Hijab',65000,55],
        ];
        return collect($names)->map(fn($n,$i) => (object)[
            'id'=>$i+1,'name'=>$n[0],'category'=>$n[1],'price'=>$n[2],'stock'=>$n[3],
            'image'=>null,
        ]);
    }
}
