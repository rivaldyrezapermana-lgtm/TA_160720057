<?php

namespace Tests\Feature\Customer;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    /** Create a product (with its category) for the storefront. */
    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'gamis'], ['name' => 'Gamis']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Produk Uji',
            'sku' => 'SKU-'.uniqid(),
            'price' => 100000,
            'stock' => 10,
            'is_active' => true,
        ], $attributes));
    }

    public function test_landing_page_loads_with_real_products(): void
    {
        $this->product(['name' => 'Gamis Unggulan']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Gamis Unggulan');
    }

    public function test_shop_lists_active_products(): void
    {
        $this->product(['name' => 'Gamis Tampil']);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Gamis Tampil');
    }

    public function test_shop_hides_inactive_products(): void
    {
        $this->product(['name' => 'Gamis Tersembunyi', 'is_active' => false]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertDontSee('Gamis Tersembunyi');
    }

    public function test_out_of_stock_products_are_listed_last(): void
    {
        $this->product(['name' => 'AAA Habis', 'stock' => 0]);
        $this->product(['name' => 'ZZZ Ada Stok', 'stock' => 25]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSeeInOrder(['ZZZ Ada Stok', 'AAA Habis']);
    }

    public function test_out_of_stock_product_shows_overlay(): void
    {
        $this->product(['name' => 'Gamis Habis', 'stock' => 0]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Stok Habis');
    }

    public function test_product_detail_shows_uploaded_image(): void
    {
        $product = $this->product(['image' => 'products/contoh.jpg']);

        $this->get(route('shop.show', $product))
            ->assertOk()
            ->assertSee('storage/products/contoh.jpg');
    }

    public function test_product_detail_renders_sizes(): void
    {
        $product = $this->product();
        ProductSize::create([
            'product_id' => $product->id,
            'size' => 'M', 'chest_cm' => 96, 'length_cm' => 137, 'sleeve_cm' => 57, 'stock' => 5,
        ]);

        $this->get(route('shop.show', $product))
            ->assertOk()
            ->assertSee('96');
    }

    public function test_inactive_product_detail_returns_404(): void
    {
        $product = $this->product(['is_active' => false]);

        $this->get(route('shop.show', $product))
            ->assertNotFound();
    }
}
