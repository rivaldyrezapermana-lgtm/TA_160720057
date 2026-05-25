<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index()
    {
        $items = $this->cart->items();
        $total = $this->cart->total();

        return view('customer.cart.index', compact('items', 'total'));
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'size' => ['nullable', 'string', 'max:10'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        if (! $product->is_active || $product->stock <= 0) {
            return back()->with('error', 'Produk tidak tersedia.');
        }

        $this->cart->add(
            (int) $data['product_id'],
            $data['size'] ?? null,
            (int) ($data['qty'] ?? 1),
        );

        return redirect()->route('cart.index')->with('success', 'Produk ditambahkan ke keranjang.');
    }

    public function update(Request $request, string $item)
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0']]);

        $this->cart->update($item, (int) $data['qty']);

        return back()->with('success', 'Keranjang diperbarui.');
    }

    public function remove(string $item)
    {
        $this->cart->remove($item);

        return back()->with('success', 'Item dihapus dari keranjang.');
    }
}
