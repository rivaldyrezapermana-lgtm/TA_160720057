<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CartService
{
    private const SESSION_KEY = 'cart';

    /** Raw cart array from session. */
    public function raw(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    /** Add a product variant to the cart, or bump its qty if already present. */
    public function add(int $productId, ?string $size, int $qty): void
    {
        $qty = max(1, $qty);
        $cart = $this->raw();
        $key = $this->key($productId, $size);

        if (isset($cart[$key])) {
            $cart[$key]['qty'] += $qty;
        } else {
            $cart[$key] = [
                'product_id' => $productId,
                'size' => $size,
                'qty' => $qty,
            ];
        }

        Session::put(self::SESSION_KEY, $cart);
    }

    /** Set qty for a specific cart key. Removes if qty <= 0. */
    public function update(string $key, int $qty): void
    {
        $cart = $this->raw();
        if (! isset($cart[$key])) {
            return;
        }

        if ($qty <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['qty'] = $qty;
        }

        Session::put(self::SESSION_KEY, $cart);
    }

    public function remove(string $key): void
    {
        $cart = $this->raw();
        unset($cart[$key]);
        Session::put(self::SESSION_KEY, $cart);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /** Number of distinct line items (not total qty). */
    public function count(): int
    {
        return count($this->raw());
    }

    /**
     * Enrich cart with current product data. Skips and prunes entries whose
     * product no longer exists or is inactive.
     */
    public function items(): Collection
    {
        $cart = $this->raw();
        if (empty($cart)) {
            return collect();
        }

        $products = Product::with('sizes')
            ->whereIn('id', collect($cart)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $items = collect();
        $changed = false;

        foreach ($cart as $key => $row) {
            $product = $products->get($row['product_id']);
            if (! $product || ! $product->is_active) {
                unset($cart[$key]);
                $changed = true;

                continue;
            }

            $price = (float) $product->price;
            $items->push((object) [
                'id' => $key,
                'product_id' => $product->id,
                'product' => $product->name,
                'product_model' => $product,
                'size' => $row['size'] ?? '-',
                'qty' => (int) $row['qty'],
                'price' => $price,
                'subtotal' => $price * (int) $row['qty'],
            ]);
        }

        if ($changed) {
            Session::put(self::SESSION_KEY, $cart);
        }

        return $items;
    }

    public function total(): float
    {
        return (float) $this->items()->sum('subtotal');
    }

    private function key(int $productId, ?string $size): string
    {
        return $productId.'-'.($size ?: 'none');
    }
}
