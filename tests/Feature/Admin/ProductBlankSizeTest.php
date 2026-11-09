<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Material;
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
            'S' => ['size' => 'S', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => ''],
            'L' => ['size' => 'L', 'chest_cm' => '96', 'length_cm' => '', 'sleeve_cm' => ''],
        ]))->assertRedirect(route('admin.products.index'));

        $sizes = Product::firstOrFail()->sizes()->pluck('size')->sort()->values()->all();

        $this->assertSame(['L'], $sizes);
    }

    public function test_a_posted_stock_value_does_not_make_a_row_used(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->payload([
            'M' => ['size' => 'M', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => '', 'stock' => '5'],
        ]))->assertRedirect(route('admin.products.index'));

        $this->assertSame(0, Product::firstOrFail()->sizes()->count());
    }

    public function test_a_size_used_only_by_the_recipe_gets_a_variant_at_stock_zero(): void
    {
        $kain = Material::create(['name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 0, 'min_stock' => 0, 'unit_cost' => 1000]);

        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->payload([
            'S' => ['size' => 'S', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => ''],
            'M' => ['size' => 'M', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => ''],
        ]) + ['materials' => [['material_id' => $kain->id, 'qty' => ['S' => '', 'M' => '1.5']]]])
            ->assertRedirect(route('admin.products.index'));

        $product = Product::firstOrFail();
        $this->assertSame(['M' => 0], $product->sizes()->pluck('stock', 'size')->all());
    }

    public function test_existing_variant_is_kept_when_its_row_is_blank_on_update(): void
    {
        $admin = $this->admin();
        $payload = $this->payload([
            'M' => ['size' => 'M', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => ''],
            'L' => ['size' => 'L', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => ''],
        ]);
        $product = Product::create([
            'category_id' => $payload['category_id'],
            'name' => 'Gamis Uji', 'sku' => 'SKU-UJI', 'price' => 1000, 'stock' => 1, 'is_active' => true,
        ]);
        ProductSize::create(['product_id' => $product->id, 'size' => 'M', 'stock' => 3]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), $payload)
            ->assertRedirect(route('admin.products.index'));

        $this->assertSame(['M' => 3], $product->sizes()->pluck('stock', 'size')->all());
    }
}
