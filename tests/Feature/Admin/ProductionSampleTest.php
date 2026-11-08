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

class ProductionSampleTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    private function admin(): User
    {
        return $this->user ??= User::create([
            'name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function batch(int $planned = 100): Production
    {
        $cat = Category::create(['name' => 'Gamis']);
        $kain = Material::create([
            'name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 500, 'min_stock' => 0, 'unit_cost' => 25000,
        ]);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
        $product->materials()->create(['material_id' => $kain->id, 'qty_required' => 3]);

        $this->actingAs($this->admin())->post(route('admin.productions.store'), [
            'product_id' => $product->id,
            'planned_qty' => $planned,
            'start_date' => now()->toDateString(),
        ]);

        return Production::latest('id')->first()->fresh('stages');
    }

    private function sampleQc(Production $p): ProductionStage
    {
        return $p->stages()->where('phase', 'sample')->where('stage', 'qc_packing')->firstOrFail();
    }

    /** Loloskan persiapan dan fase sampel sampai QC sampel selesai. */
    private function finishSampleWork(Production $p): void
    {
        $p->stages()->where('phase', 'common')->update(['status' => 'completed']);
        $p->stages()->where('phase', 'sample')->update([
            'status' => 'completed', 'input_qty' => 1, 'output_qty' => 1,
        ]);
    }

    public function test_approving_the_sample_opens_the_mass_phase(): void
    {
        $prod = $this->batch();
        $this->finishSampleWork($prod);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                'action' => 'approve_sample',
            ])
            ->assertSessionHas('success');

        $this->assertNotNull($prod->fresh()->sample_approved_at);

        $cutting = $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')->firstOrFail();
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'start'])
            ->assertSessionHas('success');
    }

    public function test_sample_cannot_be_approved_before_its_qc_stage_is_finished(): void
    {
        $prod = $this->batch();
        $prod->stages()->where('phase', 'common')->update(['status' => 'completed']);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                'action' => 'approve_sample',
            ])
            ->assertSessionHas('error');

        $this->assertNull($prod->fresh()->sample_approved_at);
    }

    public function test_approval_is_rejected_on_a_stage_that_is_not_sample_qc(): void
    {
        $prod = $this->batch();
        $this->finishSampleWork($prod);
        $massCutting = $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $massCutting]), ['action' => 'approve_sample'])
            ->assertSessionHas('error');

        $this->assertNull($prod->fresh()->sample_approved_at);
    }

    public function test_approval_is_rejected_on_the_mass_phase_qc_packing_stage(): void
    {
        $prod = $this->batch();
        $this->finishSampleWork($prod);
        $massQc = $prod->stages()->where('phase', 'mass')->where('stage', 'qc_packing')->firstOrFail();

        // Nama tahapnya sama dengan QC sampel — yang membedakan hanya fasenya.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $massQc]), ['action' => 'approve_sample'])
            ->assertSessionHas('error');

        $this->assertNull($prod->fresh()->sample_approved_at);
    }

    public function test_revision_requires_a_note(): void
    {
        $prod = $this->batch();
        $this->finishSampleWork($prod);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                'action' => 'revise_sample', 'notes' => '',
            ])
            ->assertSessionHasErrors('notes');

        $this->assertSame(0, $prod->fresh()->sample_revision_count);
    }

    public function test_revision_reopens_the_sample_phase_and_records_history(): void
    {
        $prod = $this->batch();
        $this->finishSampleWork($prod);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                'action' => 'revise_sample', 'notes' => 'Panjang lengan kurang 2cm',
            ])
            ->assertSessionHas('success');

        $prod = $prod->fresh('stages');

        $this->assertSame(1, $prod->sample_revision_count);
        $this->assertNull($prod->sample_approved_at);
        $this->assertSame(3, $prod->stages()->where('phase', 'sample')->where('status', 'pending')->count());
        $this->assertSame(0, (int) $prod->stages()->where('phase', 'sample')->max('output_qty'));
        $this->assertDatabaseHas('production_sample_revisions', [
            'production_id' => $prod->id, 'revision_no' => 1, 'notes' => 'Panjang lengan kurang 2cm',
        ]);

        // Fase massal terkunci lagi.
        $cutting = $prod->stages()->where('phase', 'mass')->where('stage', 'cutting')->firstOrFail();
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), ['action' => 'start'])
            ->assertSessionHas('error');
    }

    public function test_second_revision_increments_the_revision_number(): void
    {
        $prod = $this->batch();

        foreach (['Panjang lengan kurang 2cm', 'Resleting seret'] as $note) {
            $this->finishSampleWork($prod);
            $this->actingAs($this->admin())
                ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                    'action' => 'revise_sample', 'notes' => $note,
                ]);
        }

        $this->assertSame(2, $prod->fresh()->sample_revision_count);
        $this->assertSame([2, 1], $prod->fresh()->sampleRevisions->pluck('revision_no')->all());
    }

    public function test_revisions_add_their_own_material_consumption(): void
    {
        $prod = $this->batch();
        $kain = Material::where('code', 'K1')->firstOrFail();
        $product = Product::where('sku', 'GA-1')->firstOrFail();

        // Dua kali revisi sebelum sampel akhirnya disetujui.
        foreach (['Revisi satu', 'Revisi dua'] as $note) {
            $this->finishSampleWork($prod);
            $this->actingAs($this->admin())
                ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                    'action' => 'revise_sample', 'notes' => $note,
                ]);
        }

        $this->finishSampleWork($prod);
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), ['action' => 'approve_sample']);

        $prod = $prod->fresh('stages');
        $prod->stages()->where('phase', 'mass')->update(['input_qty' => 100, 'output_qty' => 100, 'status' => 'in_progress']);
        $massQc = $prod->stages()->where('phase', 'mass')->where('stage', 'qc_packing')->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $massQc]), [
                'action' => 'finish', 'input_qty' => 100, 'output_qty' => 100,
            ]);

        // 100 massal + 1 sampel + 2 revisi = 103 pcs × 3 m = 309 m.
        $this->assertSame(100, $product->fresh()->stock);
        $this->assertSame(191, $kain->fresh()->stock);   // 500 - 309
        $this->assertDatabaseHas('production_materials', [
            'production_id' => $prod->id, 'material_id' => $kain->id, 'qty_used' => 309,
        ]);
    }

    public function test_sample_stage_rejects_a_quantity_other_than_one(): void
    {
        $prod = $this->batch();
        $prod->stages()->where('phase', 'common')->update(['status' => 'completed']);
        $cutting = $prod->stages()->where('phase', 'sample')->where('stage', 'cutting')->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'input_qty' => 5,
            ])
            ->assertSessionHasErrors('input_qty');
    }

    public function test_batch_page_groups_stages_by_phase_and_lists_revisions(): void
    {
        $prod = $this->batch();
        $this->finishSampleWork($prod);
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $this->sampleQc($prod)]), [
                'action' => 'revise_sample', 'notes' => 'Panjang lengan kurang 2cm',
            ]);

        $this->actingAs($this->admin())
            ->get(route('admin.productions.show', $prod))
            ->assertOk()
            ->assertSee('Tahap Sampel')
            ->assertSee('Produksi Massal')
            ->assertSee('Pembuatan Pola')
            ->assertSee('QC Sampel')
            ->assertSee('QC &amp; Packing', false)
            ->assertSee('Panjang lengan kurang 2cm')
            ->assertSee('Revisi ke-1');
    }

    public function test_single_pcs_batch_page_explains_there_is_no_sample_phase(): void
    {
        $prod = $this->batch(1);

        $this->actingAs($this->admin())
            ->get(route('admin.productions.show', $prod))
            ->assertOk()
            ->assertSee('produk ini sekaligus sampelnya')
            ->assertDontSee('Tahap Sampel');
    }

    /** Batch kedua untuk produk yang sama dengan $first. */
    private function secondBatch(Production $first, int $planned = 50): Production
    {
        $this->actingAs($this->admin())->post(route('admin.productions.store'), [
            'product_id' => $first->product_id,
            'planned_qty' => $planned,
            'start_date' => now()->toDateString(),
        ]);

        return Production::latest('id')->first()->fresh('stages');
    }

    public function test_batch_reuses_an_approved_sample_and_skips_the_sample_phase(): void
    {
        $first = $this->batch();
        $second = $this->secondBatch($first);
        $first->forceFill(['sample_approved_at' => now()])->save();

        $this->actingAs($this->admin())
            ->post(route('admin.productions.reuse-sample', $second))
            ->assertSessionHas('success');

        $second = $second->fresh('stages');
        $this->assertSame($first->id, $second->sample_source_production_id);
        $this->assertNotNull($second->sample_approved_at);
        $this->assertSame(0, $second->sampleUnits());   // tidak ada kain untuk sampel
        // Desain dan pola ikut sampelnya, jadi Persiapan juga tidak ada.
        $this->assertSame(
            ['mass:cutting', 'mass:sewing', 'mass:qc_packing'],
            $second->stages->sortBy('sort_order')->map(fn ($s) => $s->phase.':'.$s->stage)->values()->all()
        );
        $this->assertSame([1, 2, 3], $second->stages->sortBy('sort_order')->pluck('sort_order')->values()->all());
    }

    public function test_reusing_a_sample_drops_preparation_even_after_design_was_finished(): void
    {
        $first = $this->batch();
        $second = $this->secondBatch($first);
        $first->forceFill(['sample_approved_at' => now()])->save();
        $second->stages()->where('phase', 'common')->update(['status' => 'completed']);

        $this->actingAs($this->admin())
            ->post(route('admin.productions.reuse-sample', $second))
            ->assertSessionHas('success');

        $this->assertSame(0, $second->stages()->where('phase', 'common')->count());
    }

    public function test_reused_sample_opens_mass_cutting_right_away(): void
    {
        $first = $this->batch();
        $second = $this->secondBatch($first);
        $first->forceFill(['sample_approved_at' => now()])->save();
        $this->actingAs($this->admin())->post(route('admin.productions.reuse-sample', $second));
        $cutting = $second->stages()->where('phase', 'mass')->where('stage', 'cutting')->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$second, $cutting]), ['action' => 'start'])
            ->assertSessionHas('success');
    }

    public function test_reused_sample_batch_page_has_no_preparation_card(): void
    {
        $first = $this->batch();
        $first->forceFill(['sample_approved_at' => now()])->save();
        $second = $this->secondBatch($first);

        $this->actingAs($this->admin())
            ->get(route('admin.productions.show', $second))
            ->assertOk()
            ->assertSee('Produksi Massal')
            ->assertSee('Desain dan pola mengikuti sampel '.$first->code)
            ->assertDontSee('Persiapan')
            ->assertDontSee('Pembuatan Pola');
    }

    public function test_cannot_reuse_when_the_product_has_no_sample_yet(): void
    {
        $prod = $this->batch();

        $this->actingAs($this->admin())
            ->post(route('admin.productions.reuse-sample', $prod))
            ->assertSessionHas('error', 'Produk ini belum memiliki sampel. Buat sampel terlebih dahulu di Tahap Sampel.');

        $this->assertSame(3, $prod->stages()->where('phase', 'sample')->count());
        $this->assertNull($prod->fresh()->sample_approved_at);
    }

    public function test_cannot_reuse_once_this_batch_started_its_own_sample(): void
    {
        $first = $this->batch();
        $second = $this->secondBatch($first);
        $first->forceFill(['status' => 'completed'])->save();
        $second->stages()->where('phase', 'sample')->where('stage', 'cutting')->update(['status' => 'in_progress']);

        $this->actingAs($this->admin())
            ->post(route('admin.productions.reuse-sample', $second))
            ->assertSessionHas('error');

        $this->assertSame(3, $second->stages()->where('phase', 'sample')->count());
    }

    public function test_check_sample_modal_offers_reuse_or_points_to_the_sample_phase(): void
    {
        $first = $this->batch();

        $this->actingAs($this->admin())->get(route('admin.productions.show', $first))
            ->assertOk()
            ->assertSee('Produk ini belum memiliki sampel')
            ->assertDontSee('Gunakan Sampel Ini');

        $second = $this->secondBatch($first);
        $first->forceFill(['sample_approved_at' => now()])->save();

        $this->actingAs($this->admin())->get(route('admin.productions.show', $second))
            ->assertOk()
            ->assertSee('Produk ini sudah memiliki sampel dari batch')
            ->assertSee('Gunakan Sampel Ini, Langsung Produksi Massal');
    }

    public function test_check_sample_goes_to_the_sample_phase_when_the_product_has_none(): void
    {
        $prod = $this->batch();

        $this->actingAs($this->admin())
            ->post(route('admin.productions.check-sample', $prod))
            ->assertRedirect(route('admin.productions.show', $prod).'#tahap-sampel')
            ->assertSessionHas('success', 'Produk ini belum memiliki sampel. Silakan buat sampel di Tahap Sampel.');

        $this->assertSame(3, $prod->stages()->where('phase', 'sample')->count());
        $this->assertNull($prod->fresh()->sample_approved_at);
    }

    public function test_check_sample_reuses_an_existing_sample_and_goes_to_mass_production(): void
    {
        $first = $this->batch();
        $second = $this->secondBatch($first);
        $first->forceFill(['sample_approved_at' => now()])->save();

        $this->actingAs($this->admin())
            ->post(route('admin.productions.check-sample', $second))
            ->assertRedirect(route('admin.productions.show', $second).'#produksi-massal')
            ->assertSessionHas('success');

        $second = $second->fresh('stages');
        $this->assertSame($first->id, $second->sample_source_production_id);
        $this->assertSame(0, $second->stages->where('phase', 'sample')->count());
    }

    public function test_check_sample_goes_to_mass_production_when_already_approved(): void
    {
        $prod = $this->batch();
        $prod->forceFill(['sample_approved_at' => now()])->save();

        $this->actingAs($this->admin())
            ->post(route('admin.productions.check-sample', $prod))
            ->assertRedirect(route('admin.productions.show', $prod).'#produksi-massal');
    }

    public function test_check_sample_continues_this_batch_sample_once_it_has_started(): void
    {
        $first = $this->batch();
        $second = $this->secondBatch($first);
        $first->forceFill(['status' => 'completed'])->save();
        $second->stages()->where('phase', 'sample')->where('stage', 'cutting')->update(['status' => 'in_progress']);

        $this->actingAs($this->admin())
            ->post(route('admin.productions.check-sample', $second))
            ->assertRedirect(route('admin.productions.show', $second).'#tahap-sampel');

        $this->assertSame(3, $second->stages()->where('phase', 'sample')->count());
    }

    public function test_new_batch_goes_to_mass_production_when_the_product_already_has_a_sample(): void
    {
        $first = $this->batch();
        $first->forceFill(['sample_approved_at' => now()])->save();

        $this->actingAs($this->admin())->post(route('admin.productions.store'), [
            'product_id' => $first->product_id, 'planned_qty' => 50, 'start_date' => now()->toDateString(),
        ])->assertRedirectContains('#produksi-massal');

        $second = Production::latest('id')->first()->fresh('stages');
        $this->assertSame($first->id, $second->sample_source_production_id);
        $this->assertNotNull($second->sample_approved_at);
        $this->assertSame(
            ['mass:cutting', 'mass:sewing', 'mass:qc_packing'],
            $second->stages->sortBy('sort_order')->map(fn ($s) => $s->phase.':'.$s->stage)->values()->all()
        );
        $this->assertSame([1, 2, 3], $second->stages->sortBy('sort_order')->pluck('sort_order')->values()->all());
    }

    public function test_new_batch_starts_at_the_sample_phase_when_the_product_has_no_sample(): void
    {
        $cat = Category::create(['name' => 'Koko']);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Koko A', 'sku' => 'KA-1', 'price' => 1, 'stock' => 0, 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->post(route('admin.productions.store'), [
            'product_id' => $product->id, 'planned_qty' => 20, 'start_date' => now()->toDateString(),
        ])->assertRedirectContains('#tahap-sampel');

        $prod = Production::latest('id')->first();
        $this->assertSame(3, $prod->stages()->where('phase', 'sample')->count());
        $this->assertNull($prod->sample_approved_at);
    }

    public function test_sample_status_endpoint_reports_the_existing_sample(): void
    {
        $first = $this->batch();

        $this->actingAs($this->admin())
            ->getJson(route('admin.productions.sample-status', ['product_id' => $first->product_id]))
            ->assertOk()->assertJson(['has_sample' => false]);

        $first->forceFill(['sample_approved_at' => now()])->save();

        $this->actingAs($this->admin())
            ->getJson(route('admin.productions.sample-status', ['product_id' => $first->product_id]))
            ->assertOk()->assertJson(['has_sample' => true, 'code' => $first->code]);
    }
}
