<?php

namespace Tests\Unit;

use App\Models\MachineCategory;
use App\Models\ProductionMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionStageFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_machine_belongs_to_a_category_mapped_to_a_stage(): void
    {
        $cat = MachineCategory::create(['name' => 'Mesin Potong', 'code' => 'CAT-CUT', 'stage' => 'cutting']);
        $machine = ProductionMachine::create(['name' => 'Cutter A', 'code' => 'M-CUT-A', 'status' => 'active', 'machine_category_id' => $cat->id]);

        $this->assertSame('cutting', $machine->category->stage);
        $this->assertTrue($cat->machines->contains($machine));
    }

    private int $batchSeq = 0;

    private function batch(int $planned = 100): \App\Models\Production
    {
        $n = ++$this->batchSeq;
        $cat = \App\Models\Category::create(['name' => "Gamis {$n}", 'slug' => "gamis-{$n}"]);
        $product = \App\Models\Product::create([
            'category_id' => $cat->id, 'name' => "Gamis A {$n}", 'sku' => "GA-{$n}",
            'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
        $user = \App\Models\User::create([
            'name' => 'Op', 'email' => "op{$n}@labasa.test", 'password' => 'password', 'role' => \App\Models\User::ROLE_ADMIN,
        ]);
        $prod = \App\Models\Production::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'code' => "PRD-T-{$n}",
            'planned_qty' => $planned, 'actual_qty' => 0, 'start_date' => now()->toDateString(), 'status' => 'planned',
        ]);
        foreach (\App\Models\ProductionStage::STAGES as $s) {
            $prod->stages()->create(['stage' => $s, 'status' => 'pending', 'input_qty' => 0, 'output_qty' => 0]);
        }
        $prod->stages()->where('stage', 'design')->update(['input_qty' => $planned]);

        return $prod->fresh('stages');
    }

    public function test_gate_qty_is_half_of_planned_rounded_up(): void
    {
        $this->assertSame(50, $this->batch(100)->gateQty());
        $this->assertSame(51, $this->batch(101)->gateQty());
    }

    public function test_first_stage_is_always_unlocked(): void
    {
        $prod = $this->batch(100);
        $design = $prod->stages->firstWhere('stage', 'design');
        $this->assertTrue($prod->stageUnlocked($design));
    }

    public function test_next_stage_locked_until_previous_reaches_gate(): void
    {
        $prod = $this->batch(100);
        $sample = $prod->stages->firstWhere('stage', 'sample');

        $this->assertFalse($prod->stageUnlocked($sample)); // design output 0

        $prod->stages()->where('stage', 'design')->update(['output_qty' => 49]);
        $this->assertFalse($prod->fresh('stages')->stageUnlocked($sample));

        $prod->stages()->where('stage', 'design')->update(['output_qty' => 50]);
        $this->assertTrue($prod->fresh('stages')->stageUnlocked($sample));
    }
}
