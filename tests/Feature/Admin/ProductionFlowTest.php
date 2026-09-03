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

    private ?User $user = null;

    private function admin(): User
    {
        return $this->user ??= User::create([
            'name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    /**
     * Batch dengan resep 3 m kain per pcs dan stok produk awal 20.
     * Tahap dibuat lewat controller supaya strukturnya sama dengan produksi nyata.
     *
     * @return array{0: Production, 1: Product, 2: Material}
     */
    private function batch(int $planned = 100): array
    {
        $cat = Category::create(['name' => 'Gamis']);
        $kain = Material::create([
            'name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 500, 'min_stock' => 0, 'unit_cost' => 25000,
        ]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 20, 'is_active' => true,
        ]);
        $product->materials()->create(['material_id' => $kain->id, 'qty_required' => 3]);

        $this->actingAs($this->admin())->post(route('admin.productions.store'), [
            'product_id' => $product->id,
            'planned_qty' => $planned,
            'start_date' => now()->toDateString(),
        ]);

        return [Production::latest('id')->first()->fresh('stages'), $product, $kain];
    }

    private function stage(Production $p, string $phase, string $stage): ProductionStage
    {
        return $p->stages()->where('phase', $phase)->where('stage', $stage)->firstOrFail();
    }

    /** Loloskan fase persiapan dan sampel, lalu setujui sampelnya. */
    private function approveSample(Production $p): void
    {
        $p->stages()->where('phase', '!=', 'mass')->update([
            'status' => 'completed', 'output_qty' => 1,
        ]);
        $p->stages()->where('phase', 'common')->update(['output_qty' => 0]);
        $p->forceFill(['sample_approved_at' => now()])->save();
    }

    public function test_store_creates_eight_phased_stages_for_a_mass_batch(): void
    {
        [$prod] = $this->batch(50);

        $this->assertSame(
            ['common:design', 'common:pola', 'sample:cutting', 'sample:sewing', 'sample:qc_packing',
                'mass:cutting', 'mass:sewing', 'mass:qc_packing'],
            $prod->stages->sortBy('sort_order')->map(fn ($s) => $s->phase.':'.$s->stage)->values()->all()
        );
        $this->assertSame(50, $this->stage($prod, 'mass', 'cutting')->input_qty);
        $this->assertSame(1, $this->stage($prod, 'sample', 'cutting')->input_qty);
    }

    public function test_store_skips_the_sample_phase_for_a_single_pcs_batch(): void
    {
        [$prod] = $this->batch(1);

        $this->assertSame(5, $prod->stages->count());
        $this->assertSame(0, $prod->stages()->where('phase', 'sample')->count());
        $this->assertSame(1, $this->stage($prod, 'mass', 'cutting')->input_qty);
    }

    public function test_mass_cutting_cannot_start_before_the_sample_is_approved(): void
    {
        [$prod] = $this->batch();
        $prod->stages()->where('phase', '!=', 'mass')->update(['status' => 'completed', 'output_qty' => 1]);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'start'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $cutting->fresh()->status);
    }

    public function test_mass_cutting_starts_once_the_sample_is_approved(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'start'])
            ->assertSessionHas('success');

        $this->assertSame('in_progress', $cutting->fresh()->status);
        $this->assertSame('in_progress', $prod->fresh()->status);
    }

    public function test_single_pcs_batch_reports_the_real_reason_mass_cutting_is_locked(): void
    {
        [$prod] = $this->batch(1);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        // Tidak ada fase sampel dan tidak ada gate 50% di sini — yang kurang adalah pola.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'start'])
            ->assertSessionHas('error', 'Tahap sebelumnya belum selesai. Belum bisa dimulai.');
    }

    public function test_pola_cannot_start_before_design_is_finished(): void
    {
        [$prod] = $this->batch();
        $pola = $this->stage($prod, 'common', 'pola');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $pola]), ['action' => 'start'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $pola->fresh()->status);
    }

    public function test_finishing_a_sample_stage_records_one_pcs(): void
    {
        [$prod] = $this->batch();
        $prod->stages()->where('phase', 'common')->update(['status' => 'completed']);
        $cutting = $this->stage($prod, 'sample', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'start']);
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'finish']);

        $this->assertSame('completed', $cutting->fresh()->status);
        $this->assertSame(1, $cutting->fresh()->output_qty);
    }

    public function test_output_cannot_exceed_input(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 150,
            ])
            ->assertSessionHasErrors('output_qty');
    }

    public function test_input_cannot_exceed_planned_target(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'input_qty' => 150, 'output_qty' => 0,
            ])
            ->assertSessionHasErrors('input_qty');

        $this->assertSame(100, $cutting->fresh()->input_qty);
    }

    public function test_input_cannot_exceed_previous_stage_output(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')
            ->update(['output_qty' => 60, 'status' => 'in_progress']);
        $sewing = $this->stage($prod, 'mass', 'sewing');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $sewing]), [
                'action' => 'save', 'input_qty' => 70, 'output_qty' => 0,
            ])
            ->assertSessionHasErrors('input_qty');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $sewing]), [
                'action' => 'save', 'input_qty' => 60, 'output_qty' => 0,
            ])
            ->assertSessionHas('success');

        $this->assertSame(60, $sewing->fresh()->input_qty);
    }

    public function test_output_cannot_drop_below_next_stage_input(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')
            ->update(['output_qty' => 60, 'status' => 'in_progress']);
        $prod->stages()->where('phase', 'mass')->where('stage', 'sewing')
            ->update(['input_qty' => 50, 'status' => 'in_progress']);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 40,
            ])
            ->assertSessionHasErrors('output_qty');

        $this->assertSame(60, $cutting->fresh()->output_qty);
    }

    public function test_starting_mass_qc_packing_moves_the_batch_to_qc_status(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $prod->stages()->where('phase', 'mass')->update(['input_qty' => 100, 'output_qty' => 100, 'status' => 'in_progress']);
        $qc = $this->stage($prod, 'mass', 'qc_packing');
        $qc->update(['status' => 'pending']);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'start', 'input_qty' => 100, 'output_qty' => 0,
            ]);

        $this->assertSame('qc', $prod->fresh()->status);
    }

    public function test_finishing_mass_qc_packing_credits_stock_and_consumes_materials_once(): void
    {
        [$prod, $product, $kain] = $this->batch();
        $this->approveSample($prod);
        $prod->stages()->where('phase', 'mass')->update(['input_qty' => 100, 'output_qty' => 100, 'status' => 'in_progress']);
        $qc = $this->stage($prod, 'mass', 'qc_packing');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
            ])
            ->assertRedirect();

        $this->assertSame('completed', $prod->fresh()->status);
        $this->assertSame(100, $prod->fresh()->actual_qty);
        $this->assertSame(120, $product->fresh()->stock);          // 20 + 100, sampel tidak masuk stok
        $this->assertSame(197, $kain->fresh()->stock);             // 500 - 3*(100 + 1 sampel)
        $this->assertDatabaseHas('production_materials', [
            'production_id' => $prod->id, 'material_id' => $kain->id, 'qty_used' => 303,
        ]);

        // Idempoten: menyelesaikan lagi tidak boleh menambah dua kali.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc->fresh()]), [
                'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
            ]);

        $this->assertSame(120, $product->fresh()->stock);
        $this->assertSame(197, $kain->fresh()->stock);
        $this->assertSame(1, $prod->productionMaterials()->count());
    }

    public function test_single_pcs_batch_consumes_no_extra_sample_material(): void
    {
        [$prod, $product, $kain] = $this->batch(1);
        $prod->stages()->where('phase', 'common')->update(['status' => 'completed']);
        $prod->stages()->where('phase', 'mass')->update(['input_qty' => 1, 'output_qty' => 1, 'status' => 'in_progress']);
        $qc = $this->stage($prod, 'mass', 'qc_packing');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'finish', 'input_qty' => 1, 'output_qty' => 1,
            ]);

        $this->assertSame(21, $product->fresh()->stock);   // 20 + 1
        $this->assertSame(497, $kain->fresh()->stock);     // 500 - 3*1
    }

    public function test_completed_stage_can_still_be_edited_until_batch_completes(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')
            ->update(['input_qty' => 100, 'output_qty' => 90, 'status' => 'completed']);
        $cutting = $this->stage($prod, 'mass', 'cutting');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 85,
            ])
            ->assertSessionHas('success');

        $this->assertSame(85, $cutting->fresh()->output_qty);
        $this->assertSame('completed', $cutting->fresh()->status);
    }

    public function test_stages_locked_after_batch_completed(): void
    {
        [$prod] = $this->batch();
        $this->approveSample($prod);
        $prod->stages()->where('phase', 'mass')->update(['input_qty' => 100, 'output_qty' => 100, 'status' => 'in_progress']);
        $qc = $this->stage($prod, 'mass', 'qc_packing');

        $this->actingAs($this->admin())->patch(route('admin.productions.stage', [$prod, $qc]), [
            'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
        ]);
        $this->assertSame('completed', $prod->fresh()->status);

        $cutting = $this->stage($prod, 'mass', 'cutting');
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'input_qty' => 100, 'output_qty' => 50,
            ])
            ->assertSessionHas('error');

        $this->assertSame(100, $cutting->fresh()->output_qty);
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

        $this->assertSame('completed', $prod->fresh()->status);
    }

    public function test_planned_qty_cannot_drop_below_recorded_stage_input(): void
    {
        [$prod] = $this->batch(); // mass cutting sudah punya input_qty = 100

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'planned_qty' => 50, 'start_date' => now()->toDateString(), 'status' => 'in_progress',
            ])
            ->assertSessionHasErrors('planned_qty');

        $this->assertSame(100, $prod->fresh()->planned_qty);
    }
}
