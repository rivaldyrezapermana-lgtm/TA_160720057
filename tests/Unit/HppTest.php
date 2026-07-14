<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HppTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);

        return Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
    }

    public function test_compute_hpp_sums_recipe_cost(): void
    {
        $product = $this->product();
        $kain = Material::create(['name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 0, 'min_stock' => 0, 'unit_cost' => 25000]);
        $benang = Material::create(['name' => 'Benang', 'code' => 'B1', 'unit' => 'rol', 'stock' => 0, 'min_stock' => 0, 'unit_cost' => 5000]);
        $product->materials()->create(['material_id' => $kain->id, 'qty_required' => 3]);
        $product->materials()->create(['material_id' => $benang->id, 'qty_required' => 2]);

        // 3*25000 + 2*5000 = 85000
        $this->assertEquals(85000.0, $product->fresh()->computeHpp());
    }

    public function test_compute_hpp_is_zero_without_recipe(): void
    {
        $this->assertEquals(0.0, $this->product()->computeHpp());
    }
}
