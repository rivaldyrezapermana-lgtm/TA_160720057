<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private function admin(): User
    {
        return $this->user ??= User::create([
            'name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    /** A batch of 100 with a recipe of 3 Kain per unit and 20 starting product stock. */
    private function batch(): array
    {
        $cat = Category::create(['name' => 'Gamis', 'slug' => 'gamis']);
        $kain = Material::create(['name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 500, 'min_stock' => 0, 'unit_cost' => 25000]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 20, 'is_active' => true,
        ]);
        $product->materials()->create(['material_id' => $kain->id, 'qty_required' => 3]);

        $prod = Production::create([
            'product_id' => $product->id, 'user_id' => $this->admin()->id, 'code' => 'PRD-T-1',
            'planned_qty' => 100, 'actual_qty' => 0, 'start_date' => now()->toDateString(), 'status' => 'planned',
        ]);
        foreach (ProductionStage::STAGES as $s) {
            $prod->stages()->create(['stage' => $s, 'status' => 'pending', 'input_qty' => $s === 'design' ? 100 : 0, 'output_qty' => 0]);
        }

        return [$prod->fresh('stages'), $product, $kain];
    }

    public function test_output_cannot_exceed_input(): void
    {
        [$prod] = $this->batch();
        $design = $prod->stages->firstWhere('stage', 'design');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 150,
            ])
            ->assertSessionHasErrors('output_qty');
    }

    public function test_next_stage_cannot_start_before_gate(): void
    {
        [$prod] = $this->batch();
        $sample = $prod->stages->firstWhere('stage', 'sample');

        // design output still 0 → sample start rejected
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $sample]), ['action' => 'start'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('production_stages', ['id' => $sample->id, 'status' => 'pending']);
    }

    public function test_completing_packing_credits_stock_and_consumes_materials_once(): void
    {
        [$prod, $product, $kain] = $this->batch();
        // Drive every stage output to 100 directly, then finish packing.
        $prod->stages()->update(['output_qty' => 100, 'input_qty' => 100, 'status' => 'in_progress']);
        $packing = $prod->stages()->where('stage', 'packing')->first();

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $packing]), [
                'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
            ])
            ->assertRedirect();

        $this->assertEquals('completed', $prod->fresh()->status);
        $this->assertEquals(100, $prod->fresh()->actual_qty);
        $this->assertEquals(120, $product->fresh()->stock);        // 20 + 100
        $this->assertEquals(200, $kain->fresh()->stock);           // 500 - 3*100
        $this->assertDatabaseHas('production_materials', ['production_id' => $prod->id, 'material_id' => $kain->id, 'qty_used' => 300]);

        // Idempotency: finishing again must not double-credit.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $packing->fresh()]), [
                'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
            ]);

        $this->assertEquals(120, $product->fresh()->stock);        // still 120
        $this->assertEquals(200, $kain->fresh()->stock);           // still 200
        $this->assertEquals(1, $prod->productionMaterials()->count());
    }
}
