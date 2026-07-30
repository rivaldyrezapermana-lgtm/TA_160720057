<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockLockTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Uji', 'email' => 'admin.uji@labasa.test',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_product_update_ignores_submitted_stock(): void
    {
        $cat = Category::create(['name' => 'Gamis']);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 40, 'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), [
                'name' => 'Gamis A', 'sku' => 'GA-1', 'category_id' => $cat->id,
                'price' => 120000, 'stock' => 9999, 'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertEquals(40, $product->fresh()->stock); // unchanged
        $this->assertEquals(120000, (int) $product->fresh()->price); // other fields still update
    }

    public function test_product_update_below_hpp_flashes_warning(): void
    {
        $cat = Category::create(['name' => 'Gamis']);
        $kain = Material::create(['name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 0, 'min_stock' => 0, 'unit_cost' => 25000]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 10, 'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), [
                'name' => 'Gamis A', 'sku' => 'GA-1', 'category_id' => $cat->id,
                'price' => 50000, 'is_active' => 1,
                'materials' => [$kain->id => ['use' => 1, 'qty_required' => 3]], // HPP = 75000 > 50000
            ])
            ->assertSessionHas('warning');

        $this->assertEquals(75000, (int) $product->fresh()->hpp);
    }

    public function test_material_update_ignores_submitted_stock(): void
    {
        $material = Material::create(['name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 30, 'min_stock' => 5, 'unit_cost' => 25000]);

        $this->actingAs($this->admin())
            ->put(route('admin.materials.update', $material), [
                'name' => 'Kain', 'code' => 'K1', 'unit' => 'm',
                'stock' => 9999, 'min_stock' => 8, 'unit_cost' => 26000,
            ])
            ->assertRedirect();

        $fresh = $material->fresh();
        $this->assertEquals(30, $fresh->stock);      // unchanged
        $this->assertEquals(8, $fresh->min_stock);   // other fields still update
    }
}
