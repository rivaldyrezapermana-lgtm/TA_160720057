<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FuzzyMamdaniService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(private FuzzyMamdaniService $fuzzy) {}

    public function index()
    {
        $products = collect([
            (object)['id'=>1,'name'=>'Gamis Anaya Navy','last_recommendation'=>4893,'updated'=>'02 May 2026'],
            (object)['id'=>2,'name'=>'Koko Modern Sage','last_recommendation'=>3120,'updated'=>'30 Apr 2026'],
            (object)['id'=>3,'name'=>'Tunik Basic Cream','last_recommendation'=>1875,'updated'=>'28 Apr 2026'],
        ]);
        return view('admin.recommendations.index', compact('products'));
    }

    public function create()
    {
        $products = collect([
            (object)['id'=>1,'name'=>'Gamis Anaya Navy'],
            (object)['id'=>2,'name'=>'Koko Modern Sage'],
            (object)['id'=>3,'name'=>'Tunik Basic Cream'],
        ]);

        // Sample history (taken from proposal example, Bab II)
        $history = [
            ['month'=>'Jan 2025','demand'=>1792,'stock_end'=>1535,'produced'=>4023],
            ['month'=>'Feb 2025','demand'=>9868,'stock_end'=>3761,'produced'=>8580],
            ['month'=>'Mar 2025','demand'=>6809,'stock_end'=>2473,'produced'=>5316],
            ['month'=>'Apr 2025','demand'=>2647,'stock_end'=>980, 'produced'=>2410],
            ['month'=>'May 2025','demand'=>486, 'stock_end'=>743, 'produced'=>1774],
            ['month'=>'Jun 2025','demand'=>5132,'stock_end'=>2021,'produced'=>6228],
            ['month'=>'Jul 2025','demand'=>8752,'stock_end'=>3117,'produced'=>8148],
            ['month'=>'Aug 2025','demand'=>6767,'stock_end'=>2513,'produced'=>6741],
            ['month'=>'Sep 2025','demand'=>8379,'stock_end'=>2487,'produced'=>6661],
            ['month'=>'Oct 2025','demand'=>1017,'stock_end'=>769, 'produced'=>1335],
            ['month'=>'Nov 2025','demand'=>6271,'stock_end'=>2178,'produced'=>1254],
            ['month'=>'Dec 2025','demand'=>6473,'stock_end'=>2135,'produced'=>7135],
        ];

        return view('admin.recommendations.create', compact('products','history'));
    }

    public function calculate(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required'],
            'demand'     => ['required','numeric','min:0'],
            'stock'      => ['required','numeric','min:0'],
        ]);

        // TODO: load real history for this product
        $history = [
            ['demand'=>1792,'stock_end'=>1535,'produced'=>4023],
            ['demand'=>9868,'stock_end'=>3761,'produced'=>8580],
            ['demand'=>6809,'stock_end'=>2473,'produced'=>5316],
            ['demand'=>2647,'stock_end'=>980, 'produced'=>2410],
            ['demand'=>486, 'stock_end'=>743, 'produced'=>1774],
            ['demand'=>5132,'stock_end'=>2021,'produced'=>6228],
            ['demand'=>8752,'stock_end'=>3117,'produced'=>8148],
            ['demand'=>6767,'stock_end'=>2513,'produced'=>6741],
            ['demand'=>8379,'stock_end'=>2487,'produced'=>6661],
            ['demand'=>1017,'stock_end'=>769, 'produced'=>1335],
            ['demand'=>6271,'stock_end'=>2178,'produced'=>1254],
            ['demand'=>6473,'stock_end'=>2135,'produced'=>7135],
        ];

        $result = $this->fuzzy->calculate(
            (float) $data['demand'],
            (float) $data['stock'],
            $history
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }
        return back()->with('fuzzyResult', $result)->withInput();
    }

    public function history($product)
    {
        $product = (object)['id'=>$product,'name'=>'Gamis Anaya Navy'];
        $history = collect([
            ['month'=>'Jan 2026','demand'=>1792,'stock_end'=>1535,'produced'=>4023,'recommendation'=>4023],
            ['month'=>'Feb 2026','demand'=>9868,'stock_end'=>3761,'produced'=>8580,'recommendation'=>8580],
            ['month'=>'Mar 2026','demand'=>6809,'stock_end'=>2473,'produced'=>5316,'recommendation'=>5400],
        ]);
        return view('admin.recommendations.history', compact('product','history'));
    }
}
