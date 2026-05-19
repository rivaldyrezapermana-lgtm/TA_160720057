<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function landing()
    {
        $featured = Product::with('category')
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
        $products = Product::with('category')->where('is_active', true);

        if ($q = $request->input('q')) {
            $products->where('name', 'like', '%'.$q.'%');
        }

        if ($category = $request->input('category')) {
            $products->whereHas('category', fn ($c) => $c->where('name', $category));
        }

        $products = $products
            ->orderByRaw('stock <= 0')   // in-stock first, sold-out last
            ->orderBy('name')
            ->get();

        $categories = Category::orderBy('name')->pluck('name');

        return view('customer.shop.index', compact('products', 'categories'));
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->load('category', 'sizes');

        return view('customer.shop.show', compact('product'));
    }
}
