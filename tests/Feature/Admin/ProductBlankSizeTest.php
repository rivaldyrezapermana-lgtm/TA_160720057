<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Saving a product must not turn every master size into a stock-0 variant. */
class ProductBlankSizeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Uji', 'email' => 'admin.uji@labasa.test',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function payload(array $sizes): array
    {
        return [
            'name' => 'Gamis Uji',
            'category_id' => Category::firstOrCreate(['name' => 'Gamis'])->id,
            'price' => 1000,
            'sizes' => $sizes,
        ];
    }

    public function test_blank_rows_do_not_create_variants_on_store(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->payload([
            'S' => ['size' => 'S', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => '', 'stock' => ''],
            'M' => ['size' => 'M', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => '', 'stock' => '5'],
            'L' => ['size' => 'L', 'chest_cm' => '96', 'length_cm' => '', 'sleeve_cm' => '', 'stock' => ''],
            'XL' => ['size' => 'XL', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => '', 'stock' => '0'],
        ]))->assertRedirect(route('admin.products.index'));

        $sizes = Product::firstOrFail()->sizes()->pluck('size')->sort()->values()->all();

        $this->assertSame(['L', 'M', 'XL'], $sizes);
    }

    public function test_existing_variant_is_kept_when_its_row_is_blank_on_update(): void
    {
        $admin = $this->admin();
        $payload = $this->payload([
            'M' => ['size' => 'M', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => '', 'stock' => ''],
            'L' => ['size' => 'L', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => '', 'stock' => ''],
        ]);
        $product = Product::create([
            'category_id' => $payload['category_id'],
            'name' => 'Gamis Uji', 'sku' => 'SKU-UJI', 'price' => 1000, 'stock' => 1, 'is_active' => true,
        ]);
        ProductSize::create(['product_id' => $product->id, 'size' => 'M', 'stock' => 3]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), $payload)
            ->assertRedirect(route('admin.products.index'));

        $this->assertSame(['M'], $product->sizes()->pluck('size')->all());
    }
}
