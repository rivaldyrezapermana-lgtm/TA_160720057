<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\MachineCategory;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionMachine;
use App\Models\ProductionStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionStageFlowTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** Batch dengan baris tahap berfase, persis seperti yang dibuat controller. */
    private function batch(int $planned = 100): Production
    {
        $n = ++$this->seq;

        $cat = Category::create(['name' => "Gamis {$n}"]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => "Gamis A {$n}", 'sku' => "GA-{$n}",
            'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
        $user = User::create([
            'name' => 'Op', 'email' => "op{$n}@labasa.test", 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
        $prod = Production::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'code' => "PRD-T-{$n}",
            'planned_qty' => $planned, 'actual_qty' => 0, 'start_date' => now()->toDateString(), 'status' => 'planned',
        ]);

        $rows = [['common', 'design', 0], ['common', 'pola', 0]];

        if ($planned > 1) {
            $rows[] = ['sample', 'cutting', 1];
            $rows[] = ['sample', 'sewing', 1];
            $rows[] = ['sample', 'qc_packing', 1];
        }

        $rows[] = ['mass', 'cutting', $planned];
        $rows[] = ['mass', 'sewing', 0];
        $rows[] = ['mass', 'qc_packing', 0];

        $order = 0;
        foreach ($rows as [$phase, $stage, $input]) {
            $prod->stages()->create([
                'phase' => $phase, 'sort_order' => ++$order, 'stage' => $stage,
                'status' => 'pending', 'input_qty' => $input, 'output_qty' => 0,
            ]);
        }

        return $prod->fresh('stages');
    }

    private function stage(Production $p, string $phase, string $stage): ProductionStage
    {
        return $p->stages->first(fn (ProductionStage $s) => $s->phase === $phase && $s->stage === $stage);
    }

    private function complete(Production $p, string $phase, string $stage, int $output = 0): void
    {
        $p->stages()->where('phase', $phase)->where('stage', $stage)
            ->update(['status' => 'completed', 'output_qty' => $output]);
    }

    public function test_machine_belongs_to_a_category_mapped_to_a_stage(): void
    {
        $cat = MachineCategory::create(['name' => 'Mesin Potong', 'code' => 'CAT-CUT', 'stage' => 'cutting']);
        $machine = ProductionMachine::create([
            'name' => 'Cutter A', 'code' => 'M-CUT-A', 'status' => 'active', 'machine_category_id' => $cat->id,
        ]);

        $this->assertSame('cutting', $machine->category->stage);
        $this->assertTrue($cat->machines->contains($machine));
    }

    public function test_batch_over_one_pcs_has_eight_phased_stages(): void
    {
        $prod = $this->batch(50);

        $this->assertSame(
            ['common:design', 'common:pola', 'sample:cutting', 'sample:sewing', 'sample:qc_packing',
                'mass:cutting', 'mass:sewing', 'mass:qc_packing'],
            $prod->stages->sortBy('sort_order')->map(fn ($s) => $s->phase.':'.$s->stage)->values()->all()
        );
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $prod->stages->pluck('sort_order')->sort()->values()->all());
        $this->assertTrue($prod->hasSamplePhase());
    }

    public function test_single_pcs_batch_has_no_sample_phase(): void
    {
        $prod = $this->batch(1);

        $this->assertSame(
            ['common:design', 'common:pola', 'mass:cutting', 'mass:sewing', 'mass:qc_packing'],
            $prod->stages->sortBy('sort_order')->map(fn ($s) => $s->phase.':'.$s->stage)->values()->all()
        );
        $this->assertFalse($prod->hasSamplePhase());
        $this->assertSame(0, $prod->sampleUnits());
    }

    public function test_design_is_always_unlocked(): void
    {
        $prod = $this->batch();

        $this->assertTrue($prod->stageUnlocked($this->stage($prod, 'common', 'design')));
    }

    public function test_pola_is_locked_until_design_is_completed(): void
    {
        $prod = $this->batch();

        $this->assertFalse($prod->stageUnlocked($this->stage($prod, 'common', 'pola')));

        $this->complete($prod, 'common', 'design');
        $prod = $prod->fresh('stages');

        $this->assertTrue($prod->stageUnlocked($this->stage($prod, 'common', 'pola')));
    }

    public function test_sample_cutting_is_locked_until_pola_is_completed(): void
    {
        $prod = $this->batch();

        $this->assertFalse($prod->stageUnlocked($this->stage($prod, 'sample', 'cutting')));

        $this->complete($prod, 'common', 'design');
        $this->complete($prod, 'common', 'pola');
        $prod = $prod->fresh('stages');

        $this->assertTrue($prod->stageUnlocked($this->stage($prod, 'sample', 'cutting')));
    }

    public function test_mass_cutting_is_locked_until_the_sample_is_approved(): void
    {
        $prod = $this->batch(100);
        $this->complete($prod, 'sample', 'qc_packing', 1);
        $prod = $prod->fresh('stages');

        // Tahap QC sampel selesai, tapi belum disetujui.
        $this->assertFalse($prod->stageUnlocked($this->stage($prod, 'mass', 'cutting')));

        $prod->forceFill(['sample_approved_at' => now()])->save();
        $prod = $prod->fresh('stages');

        $this->assertTrue($prod->stageUnlocked($this->stage($prod, 'mass', 'cutting')));
    }

    public function test_mass_cutting_unlocks_after_pola_for_a_single_pcs_batch(): void
    {
        $prod = $this->batch(1);

        $this->assertFalse($prod->stageUnlocked($this->stage($prod, 'mass', 'cutting')));

        $this->complete($prod, 'common', 'pola');
        $prod = $prod->fresh('stages');

        $this->assertTrue($prod->stageUnlocked($this->stage($prod, 'mass', 'cutting')));
    }

    public function test_gate_qty_is_half_of_planned_rounded_up(): void
    {
        $this->assertSame(50, $this->batch(100)->gateQty());
        $this->assertSame(51, $this->batch(101)->gateQty());
    }

    public function test_mass_stages_after_the_first_use_the_fifty_percent_gate(): void
    {
        $prod = $this->batch(100);
        $prod->forceFill(['sample_approved_at' => now()])->save();

        $sewing = fn (Production $p) => $p->stageUnlocked($this->stage($p, 'mass', 'sewing'));

        $this->assertFalse($sewing($prod->fresh('stages')));

        $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')->update(['output_qty' => 49]);
        $this->assertFalse($sewing($prod->fresh('stages')));

        $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')->update(['output_qty' => 50]);
        $this->assertTrue($sewing($prod->fresh('stages')));
    }

    public function test_mass_stage_max_input_is_capped_by_planned_and_previous_output(): void
    {
        $prod = $this->batch(100);

        $this->assertSame(100, $prod->stageMaxInput($this->stage($prod, 'mass', 'cutting')));

        $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')->update(['output_qty' => 60]);
        $prod = $prod->fresh('stages');

        $this->assertSame(60, $prod->stageMaxInput($this->stage($prod, 'mass', 'sewing')));
    }

    public function test_sample_stage_input_is_capped_at_one(): void
    {
        $prod = $this->batch(100);

        $this->assertSame(1, $prod->stageMaxInput($this->stage($prod, 'sample', 'cutting')));
        $this->assertSame(1, $prod->stageMaxInput($this->stage($prod, 'sample', 'qc_packing')));
    }

    public function test_stages_without_qty_report_zero_bounds(): void
    {
        $prod = $this->batch(100);

        $this->assertFalse($this->stage($prod, 'common', 'design')->carriesQty());
        $this->assertFalse($this->stage($prod, 'common', 'pola')->carriesQty());
        $this->assertSame(0, $prod->stageMaxInput($this->stage($prod, 'common', 'pola')));
        $this->assertSame(0, $prod->stageMinOutput($this->stage($prod, 'common', 'design')));
    }

    public function test_mass_stage_min_output_follows_the_next_stage_input(): void
    {
        $prod = $this->batch(100);
        $prod->stages()->where('phase', 'mass')->where('stage', 'sewing')->update(['input_qty' => 50]);
        $prod = $prod->fresh('stages');

        $this->assertSame(50, $prod->stageMinOutput($this->stage($prod, 'mass', 'cutting')));
        $this->assertSame(0, $prod->stageMinOutput($this->stage($prod, 'mass', 'qc_packing')));
    }

    public function test_sample_units_counts_the_sample_plus_every_revision(): void
    {
        $prod = $this->batch(100);
        $this->assertSame(1, $prod->sampleUnits());

        $prod->forceFill(['sample_revision_count' => 2])->save();
        $this->assertSame(3, $prod->fresh()->sampleUnits());
    }

    public function test_qc_packing_label_depends_on_the_phase(): void
    {
        $prod = $this->batch(100);

        $this->assertSame('QC Sampel', $this->stage($prod, 'sample', 'qc_packing')->label());
        $this->assertSame('QC & Packing', $this->stage($prod, 'mass', 'qc_packing')->label());
        $this->assertSame('Pembuatan Pola', $this->stage($prod, 'common', 'pola')->label());
    }
}
