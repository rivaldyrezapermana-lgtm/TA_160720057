<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSkuTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Uji', 'email' => 'admin.uji@labasa.test',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    /** Minimal valid product payload — note: no 'sku' key. */
    private function payload(Category $cat, array $extra = []): array
    {
        return array_merge([
            'name' => 'Gamis Baru', 'category_id' => $cat->id, 'price' => 150000, 'is_active' => '1',
        ], $extra);
    }

    public function test_store_generates_first_sku(): void
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload($cat))
            ->assertRedirectToRoute('admin.products.index');

        $this->assertDatabaseHas('products', ['name' => 'Gamis Baru', 'sku' => 'SKU-0001']);
    }

    public function test_store_continues_from_highest_existing_sku(): void
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);
        Product::create(['category_id' => $cat->id, 'name' => 'Lama', 'sku' => 'SKU-0007', 'price' => 1000, 'stock' => 0]);
        // Non-matching SKU formats are ignored by the generator.
        Product::create(['category_id' => $cat->id, 'name' => 'Custom', 'sku' => 'GAM-999', 'price' => 1000, 'stock' => 0]);

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload($cat));

        $this->assertDatabaseHas('products', ['name' => 'Gamis Baru', 'sku' => 'SKU-0008']);
    }

    public function test_consecutive_stores_get_distinct_skus(): void
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload($cat, ['name' => 'Produk A']));
        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload($cat, ['name' => 'Produk B']));

        $this->assertDatabaseHas('products', ['name' => 'Produk A', 'sku' => 'SKU-0001']);
        $this->assertDatabaseHas('products', ['name' => 'Produk B', 'sku' => 'SKU-0002']);
    }

    public function test_update_cannot_change_sku(): void
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'Ada', 'sku' => 'SKU-0001', 'price' => 1000, 'stock' => 0]);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), [
                'name' => 'Ada', 'category_id' => $cat->id, 'price' => 2000, 'sku' => 'HACK-99',
            ])
            ->assertRedirect();

        $this->assertEquals('SKU-0001', $product->fresh()->sku);
    }
}
