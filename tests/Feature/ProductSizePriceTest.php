<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Material;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSizePriceTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create([
            'category_id' => Category::create(['name' => 'Gamis'])->id,
            'name' => 'Gamis A', 'sku' => 'GA-1', 'price' => 100000, 'stock' => 15, 'is_active' => true,
        ]);
        ProductSize::create(['product_id' => $this->product->id, 'size' => 'S', 'stock' => 5]);
        ProductSize::create(['product_id' => $this->product->id, 'size' => 'M', 'stock' => 5, 'price' => 115000]);
        ProductSize::create(['product_id' => $this->product->id, 'size' => 'L', 'stock' => 5, 'price' => 130000]);
    }

    private function user(string $role): User
    {
        return User::create(['name' => $role, 'email' => $role.'@labasa.test', 'password' => 'password', 'role' => $role]);
    }

    private function formPayload(array $sizes, array $extra = []): array
    {
        return $extra + [
            'name' => 'Gamis A',
            'category_id' => $this->product->category_id,
            'price' => 100000,
            'is_active' => 1,
            'sizes' => $sizes,
        ];
    }

    public function test_checkout_charges_the_price_of_each_chosen_size(): void
    {
        $customer = $this->user(User::ROLE_PEMBELI);

        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $this->product->id, 'size' => 'L', 'qty' => 2]);
        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $this->product->id, 'size' => 'S', 'qty' => 1]);
        $this->actingAs($customer)->post(route('checkout.store'), [
            'recipient' => 'Beli', 'phone' => '0812', 'shipping_address' => 'Jl. Uji', 'payment_method' => 'transfer',
        ])->assertRedirect();

        $this->assertEquals(130000, (float) OrderItem::where('size', 'L')->value('price'));
        $this->assertEquals(260000, (float) OrderItem::where('size', 'L')->value('subtotal'));
        $this->assertEquals(100000, (float) OrderItem::where('size', 'S')->value('price'));
        // 260.000 + 100.000 + ongkos kirim 25.000
        $this->assertEquals(385000, (float) OrderItem::first()->order->total);
    }

    public function test_cart_refuses_a_size_the_product_does_not_have(): void
    {
        $customer = $this->user(User::ROLE_PEMBELI);

        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $this->product->id, 'size' => 'ZZ', 'qty' => 1])
            ->assertSessionHas('error');
        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $this->product->id, 'qty' => 1])
            ->assertSessionHas('error');

        $this->assertSame([], session('cart', []));
    }

    public function test_store_saves_a_price_per_size_and_blank_follows_the_main_price(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))->post(route('admin.products.store'), $this->formPayload([
            'S' => ['size' => 'S', 'chest_cm' => '90', 'price' => ''],
            'L' => ['size' => 'L', 'chest_cm' => '100', 'price' => '125000'],
        ], ['name' => 'Gamis Baru']))->assertRedirect(route('admin.products.index'));

        $created = Product::where('name', 'Gamis Baru')->firstOrFail();
        $this->assertEquals(100000.0, $created->priceForSize('S'));
        $this->assertEquals(125000.0, $created->priceForSize('L'));
    }

    public function test_a_row_with_only_a_price_creates_the_variant(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))->put(route('admin.products.update', $this->product), $this->formPayload([
            'XL' => ['size' => 'XL', 'price' => '140000'],
        ]))->assertSessionHasNoErrors();

        $this->assertEquals(140000.0, $this->product->fresh()->priceForSize('XL'));
    }

    public function test_update_changes_and_clears_a_size_price_without_touching_stock(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))->put(route('admin.products.update', $this->product), $this->formPayload([
            'M' => ['size' => 'M', 'price' => ''],
            'L' => ['size' => 'L', 'price' => '135000'],
        ]))->assertSessionHasNoErrors();

        $product = $this->product->fresh();
        $this->assertEquals(100000.0, $product->priceForSize('M'));
        $this->assertEquals(135000.0, $product->priceForSize('L'));
        $this->assertSame(5, $product->sizes->firstWhere('size', 'L')->stock);
    }

    public function test_negative_size_price_is_rejected(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))->put(route('admin.products.update', $this->product), $this->formPayload([
            'L' => ['size' => 'L', 'price' => '-1'],
        ]))->assertSessionHasErrors('sizes.L.price');

        $this->assertEquals(130000.0, $this->product->fresh()->priceForSize('L'));
    }

    public function test_hpp_warning_compares_each_size_with_its_own_price(): void
    {
        $kain = Material::create(['name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 100, 'min_stock' => 0, 'unit_cost' => 50000]);

        // HPP 2 m × 50.000 = 100.000 untuk semua ukuran. Harga utama 90.000 rugi,
        // tetapi L punya harga sendiri 130.000. M diturunkan ke 80.000.
        $this->actingAs($this->user(User::ROLE_ADMIN))->put(route('admin.products.update', $this->product), $this->formPayload([
            'S' => ['size' => 'S', 'price' => ''],
            'M' => ['size' => 'M', 'price' => '80000'],
            'L' => ['size' => 'L', 'price' => '130000'],
        ], [
            'price' => 90000,
            'materials' => [['material_id' => $kain->id, 'qty' => ['S' => '2', 'M' => '2', 'L' => '2']]],
        ]))->assertSessionHas(
            'warning',
            'Harga jual di bawah HPP pada ukuran S (harga Rp 90.000, HPP Rp 100.000), M (harga Rp 80.000, HPP Rp 100.000). Periksa kembali margin.'
        );
    }

    public function test_admin_show_page_computes_margin_from_the_size_price(): void
    {
        $kain = Material::create(['name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 100, 'min_stock' => 0, 'unit_cost' => 50000]);
        $this->product->materials()->create(['material_id' => $kain->id, 'size' => 'L', 'qty_required' => 2]);

        // L: 130.000 − HPP 100.000
        $this->actingAs($this->user(User::ROLE_ADMIN))->get(route('admin.products.show', $this->product))
            ->assertSee('Rp 30.000')
            ->assertSee('Rp 100.000 – Rp 130.000');
    }

    public function test_admin_list_shows_the_price_range(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))->getJson(route('admin.datatables.products'))
            ->assertJsonPath('data.0.price', 'Rp 100.000 – Rp 130.000');
    }

    public function test_edit_form_shows_the_saved_size_price(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))->get(route('admin.products.edit', $this->product))
            ->assertSee('name="sizes[L][price]"', false)
            ->assertSee('value="130000.00"', false);
    }

    public function test_shop_cards_show_the_range_when_size_prices_differ(): void
    {
        $this->get(route('shop.index'))->assertSee('Rp 100.000 – Rp 130.000');
        $this->get(route('home'))->assertSee('Rp 100.000 – Rp 130.000');
    }

    public function test_shop_cards_show_one_price_when_every_size_costs_the_same(): void
    {
        $this->product->sizes()->update(['price' => null]);

        $this->get(route('shop.index'))
            ->assertSee('Rp 100.000')
            ->assertDontSee('Rp 100.000 – ');
    }

    public function test_product_page_starts_on_the_first_size_in_stock_with_its_price(): void
    {
        $this->product->sizes()->where('size', 'S')->update(['stock' => 0]);

        $response = $this->get(route('shop.show', $this->product));

        $response->assertSee('data-price="115000"', false);
        $response->assertSee('<p id="product-price" class="font-display text-3xl font-semibold mt-4">Rp 115.000</p>', false);
        $this->assertMatchesRegularExpression('/value="M"[^>]*checked/', $response->getContent());
    }
}
