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
        $cat = Category::create(['name' => 'Gamis']);
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

    public function test_input_cannot_exceed_planned_target(): void
    {
        [$prod] = $this->batch();
        $design = $prod->stages->firstWhere('stage', 'design');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), [
                'action' => 'save', 'input_qty' => 150, 'output_qty' => 0,
            ])
            ->assertSessionHasErrors('input_qty');

        $this->assertEquals(100, $design->fresh()->input_qty); // unchanged

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 0,
            ])
            ->assertSessionHas('success');

        $this->assertEquals(100, $design->fresh()->input_qty);
    }

    public function test_input_cannot_exceed_previous_stage_output(): void
    {
        [$prod] = $this->batch();
        $prod->stages()->where('stage', 'design')->update(['output_qty' => 60, 'status' => 'in_progress']);
        $sample = $prod->stages()->where('stage', 'sample')->first();

        // 70 > design's output of 60 → rejected.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $sample]), [
                'action' => 'save', 'input_qty' => 70, 'output_qty' => 0,
            ])
            ->assertSessionHasErrors('input_qty');

        // Exactly at the limit → accepted.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $sample]), [
                'action' => 'save', 'input_qty' => 60, 'output_qty' => 0,
            ])
            ->assertSessionHas('success');

        $this->assertEquals(60, $sample->fresh()->input_qty);
    }

    public function test_output_cannot_drop_below_next_stage_input(): void
    {
        [$prod] = $this->batch();
        $prod->stages()->where('stage', 'design')->update(['output_qty' => 60, 'status' => 'in_progress']);
        $prod->stages()->where('stage', 'sample')->update(['input_qty' => 50, 'status' => 'in_progress']);
        $design = $prod->stages()->where('stage', 'design')->first();

        // Sample already took in 50 → design's output can't be edited down to 40.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 40,
            ])
            ->assertSessionHasErrors('output_qty');

        $this->assertEquals(60, $design->fresh()->output_qty); // unchanged
    }

    public function test_completed_stage_can_still_be_edited_until_batch_completes(): void
    {
        [$prod] = $this->batch();
        $prod->stages()->where('stage', 'design')->update(['input_qty' => 100, 'output_qty' => 90, 'status' => 'completed']);
        $design = $prod->stages()->where('stage', 'design')->first();

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 85,
            ])
            ->assertSessionHas('success');

        $this->assertEquals(85, $design->fresh()->output_qty);
        $this->assertEquals('completed', $design->fresh()->status); // save doesn't reopen it
    }

    public function test_stages_locked_after_batch_completed(): void
    {
        [$prod] = $this->batch();
        $prod->stages()->update(['output_qty' => 100, 'input_qty' => 100, 'status' => 'in_progress']);
        $packing = $prod->stages()->where('stage', 'packing')->first();

        $this->actingAs($this->admin())->patch(route('admin.productions.stage', [$prod, $packing]), [
            'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
        ]);
        $this->assertEquals('completed', $prod->fresh()->status);

        $design = $prod->stages()->where('stage', 'design')->first();
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $design]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 50,
            ])
            ->assertSessionHas('error');

        $this->assertEquals(100, $design->fresh()->output_qty); // unchanged
    }

    public function test_completed_batch_cannot_be_edited(): void
    {
        [$prod] = $this->batch();
        $prod->forceFill(['status' => 'completed'])->save();

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'planned_qty' => 100, 'start_date' => now()->toDateString(), 'status' => 'in_progress',
            ])
            ->assertSessionHas('error');

        $this->assertEquals('completed', $prod->fresh()->status);
    }

    public function test_planned_qty_cannot_drop_below_recorded_stage_input(): void
    {
        [$prod] = $this->batch(); // design stage already has input_qty = 100

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'planned_qty' => 50, 'start_date' => now()->toDateString(), 'status' => 'in_progress',
            ])
            ->assertSessionHasErrors('planned_qty');

        $this->assertEquals(100, $prod->fresh()->planned_qty);
    }
}
