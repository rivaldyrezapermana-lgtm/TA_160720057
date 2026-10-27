<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function landing()
    {
        $featured = Product::with('category', 'sizes')
            ->where('is_active', true)
            ->orderByRaw('stock <= 0')   // in-stock first, sold-out last
            ->latest()
            ->take(4)
            ->get();

        $categories = Category::withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('customer.shop.landing', compact('featured', 'categories'));
    }

    public function index(Request $request)
    {
        $products = Product::with('category', 'sizes')->where('is_active', true);

        if ($q = $request->input('q')) {
            $products->where('name', 'like', '%'.$q.'%');
        }

        if ($category = $request->input('category')) {
            $products->whereHas('category', fn ($c) => $c->where('name', $category));
        }

        if ($size = $request->input('size')) {
            $products->whereHas('sizes', fn ($s) => $s->where('size', $size)->where('stock', '>', 0));
        }

        $products = $products
            ->orderByRaw('stock <= 0')   // in-stock first, sold-out last
            ->orderBy('name')
            ->get();

        $categories = Category::orderBy('name')->pluck('name');
        $sizes = $this->sizeOptions();

        return view('customer.shop.index', compact('products', 'categories', 'sizes'));
    }

    /** Distinct sizes across active products, in S → XXL order (unknown sizes last). */
    private function sizeOptions(): array
    {
        $order = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

        return ProductSize::whereHas('product', fn ($p) => $p->where('is_active', true))
            ->distinct()
            ->pluck('size')
            ->sortBy(function ($size) use ($order) {
                $rank = array_search(strtoupper($size), $order, true);

                return [$rank === false ? PHP_INT_MAX : $rank, $size];
            })
            ->values()
            ->all();
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->load('category', 'sizes');

        return view('customer.shop.show', compact('product'));
    }
}
