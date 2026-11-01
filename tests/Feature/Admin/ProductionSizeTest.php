<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionStage;
use App\Models\ProductionStageSize;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSizeTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    private function admin(): User
    {
        return $this->user ??= User::create([
            'name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    /** Produk dengan ukuran S/M/L, masing-masing stok 2. */
    private function product(array $sizes = ['S', 'M', 'L']): Product
    {
        $product = Product::create([
            'category_id' => Category::create(['name' => 'Gamis'])->id,
            'name' => 'Gamis A', 'sku' => 'GA-1', 'price' => 100000, 'stock' => 6, 'is_active' => true,
        ]);
        foreach ($sizes as $size) {
            ProductSize::create(['product_id' => $product->id, 'size' => $size, 'stock' => 2]);
        }

        return $product;
    }

    private function store(Product $product, array $extra)
    {
        return $this->actingAs($this->admin())->post(route('admin.productions.store'), array_merge([
            'product_id' => $product->id,
            'start_date' => now()->toDateString(),
        ], $extra));
    }

    private function stage(Production $p, string $phase, string $stage): ProductionStage
    {
        return $p->stages()->where('phase', $phase)->where('stage', $stage)->firstOrFail();
    }

    /**
     * Batch lama (tanpa rincian per ukuran di tahap): lompati persiapan, sampel,
     * dan dua tahap massal pertama; QC massal sedang berjalan.
     */
    private function readyForMassQc(Production $p, int $qty): ProductionStage
    {
        ProductionStageSize::whereIn('production_stage_id', $p->stages()->pluck('id'))->delete();
        $p->stages()->where('phase', '!=', 'mass')->update(['status' => 'completed', 'output_qty' => 1]);
        $p->forceFill(['sample_approved_at' => now()])->save();
        $p->stages()->where('phase', 'mass')->update(['input_qty' => $qty, 'output_qty' => $qty, 'status' => 'in_progress']);

        return $this->stage($p, 'mass', 'qc_packing');
    }

    /** Batch berukuran: cutting & sewing selesai penuh tiap ukuran; QC massal berjalan. */
    private function readyForSizedQc(Production $p, array $sizes): ProductionStage
    {
        $p->stages()->where('phase', '!=', 'mass')->update(['status' => 'completed', 'output_qty' => 1]);
        $p->forceFill(['sample_approved_at' => now()])->save();

        foreach ($p->stages()->where('phase', 'mass')->with('sizeRows')->get() as $stage) {
            $isQc = $stage->stage === 'qc_packing';
            foreach ($stage->sizeRows as $row) {
                $row->update(['input_qty' => $isQc ? $sizes[$row->size] : 0, 'output_qty' => $isQc ? 0 : $sizes[$row->size]]);
            }
            $stage->load('sizeRows');
            $stage->syncTotalsFromSizes();
            $stage->status = 'in_progress';
            $stage->save();
        }

        return $this->stage($p, 'mass', 'qc_packing');
    }

    public function test_store_creates_size_rows_and_sums_the_target(): void
    {
        $product = $this->product();

        $this->store($product, ['planned_qty' => 999, 'sizes' => ['S' => 10, 'M' => 30, 'L' => 0]])
            ->assertSessionHasNoErrors();

        $prod = Production::with('sizes')->latest('id')->first();
        $this->assertSame(40, (int) $prod->planned_qty);
        $this->assertSame(['S' => 10, 'M' => 30], $prod->sizes->pluck('planned_qty', 'size')->all());
        $this->assertSame('M', $prod->sample_size);   // ukuran dengan target terbanyak
        $this->assertSame(40, $this->stage($prod, 'mass', 'cutting')->input_qty);
    }

    public function test_store_accepts_a_chosen_sample_size(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30], 'sample_size' => 'S'])
            ->assertSessionHasNoErrors();

        $this->assertSame('S', Production::latest('id')->first()->sample_size);
    }

    public function test_store_rejects_a_sample_size_that_is_not_produced(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30], 'sample_size' => 'L'])
            ->assertSessionHasErrors('sample_size');

        $this->assertSame(0, Production::count());
    }

    public function test_store_requires_at_least_one_size_for_a_sized_product(): void
    {
        $this->store($this->product(), ['planned_qty' => 50, 'sizes' => ['S' => 0]])
            ->assertSessionHasErrors('sizes');

        $this->assertSame(0, Production::count());
    }

    public function test_single_piece_sized_batch_has_no_sample_phase(): void
    {
        $this->store($this->product(), ['sizes' => ['L' => 1]])->assertSessionHasNoErrors();

        $prod = Production::latest('id')->first();
        $this->assertSame(1, (int) $prod->planned_qty);
        $this->assertNull($prod->sample_size);
        $this->assertSame(0, $prod->stages()->where('phase', 'sample')->count());
    }

    public function test_product_without_sizes_still_uses_planned_qty(): void
    {
        $this->store($this->product([]), ['planned_qty' => 25])->assertSessionHasNoErrors();

        $prod = Production::with('sizes')->latest('id')->first();
        $this->assertSame(25, (int) $prod->planned_qty);
        $this->assertTrue($prod->sizes->isEmpty());
    }

    public function test_finishing_mass_qc_requires_a_matching_size_breakdown(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();
        $qc = $this->readyForMassQc($prod, 38);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'finish', 'input_qty' => 38, 'output_qty' => 38,
                'size_output' => ['S' => 10, 'M' => 30],
            ])
            ->assertSessionHasErrors('size_output');

        $this->assertNotSame('completed', $prod->fresh()->status);
    }

    public function test_finishing_mass_qc_rejects_more_than_a_sizes_target(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();
        $qc = $this->readyForMassQc($prod, 38);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'finish', 'input_qty' => 38, 'output_qty' => 38,
                'size_output' => ['S' => 12, 'M' => 26],
            ])
            ->assertSessionHasErrors('size_output');
    }

    public function test_finishing_mass_qc_credits_each_size_stock(): void
    {
        $product = $this->product();
        $this->store($product, ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();
        $qc = $this->readyForSizedQc($prod, ['S' => 10, 'M' => 30]);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'finish', 'size_qty' => ['S' => 9, 'M' => 29],
            ])
            ->assertSessionHasNoErrors();

        $prod->refresh();
        $this->assertSame('completed', $prod->status);
        $this->assertSame(['S' => 9, 'M' => 29], $prod->sizes->pluck('actual_qty', 'size')->all());
        $this->assertSame(
            ['S' => 11, 'M' => 31, 'L' => 2],
            $product->sizes()->orderBy('id')->pluck('stock', 'size')->all()
        );
        $this->assertSame(44, $product->fresh()->stock);   // 6 + 38
    }

    public function test_single_size_batch_assigns_output_automatically(): void
    {
        $product = $this->product();
        $this->store($product, ['sizes' => ['M' => 20]]);
        $prod = Production::latest('id')->first();
        $qc = $this->readyForSizedQc($prod, ['M' => 20]);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'finish', 'size_qty' => ['M' => 18],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(18, $prod->sizes()->first()->actual_qty);
        $this->assertSame(20, $product->sizes()->where('size', 'M')->value('stock'));
    }

    public function test_update_replaces_the_size_breakdown(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'sizes' => ['M' => 20, 'L' => 25],
                'sample_size' => 'L',
                'start_date' => now()->toDateString(),
                'status' => 'planned',
            ])
            ->assertSessionHasNoErrors();

        $prod->refresh();
        $this->assertSame(45, (int) $prod->planned_qty);
        $this->assertSame(['M' => 20, 'L' => 25], $prod->sizes->pluck('planned_qty', 'size')->all());
        $this->assertSame('L', $prod->sample_size);
    }

    public function test_update_cannot_cross_the_single_piece_boundary_via_sizes(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'sizes' => ['M' => 1],
                'start_date' => now()->toDateString(),
                'status' => 'planned',
            ])
            ->assertSessionHasErrors('planned_qty');

        $this->assertSame(40, (int) $prod->fresh()->planned_qty);
    }

    public function test_pages_render_the_size_breakdown(): void
    {
        $product = $this->product();
        $this->store($product, ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();

        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()->assertSee('Rincian per Ukuran')->assertSee('Ukuran Sampel');
        $this->actingAs($this->admin())->get(route('admin.productions.edit', $prod))
            ->assertOk()->assertSee('sizes[M]', false);
        $this->actingAs($this->admin())->get(route('admin.productions.create', ['product_id' => $product->id, 'sizes' => ['M' => 7]]))
            ->assertOk()->assertSee('"M":"7"', false);
        $this->actingAs($this->admin())->getJson(route('admin.datatables.productions'))
            ->assertOk()->assertJsonPath('data.0.sizes', 'S 10 · M 30');
        $this->actingAs($this->admin())->get(route('admin.reports.production'))
            ->assertOk()->assertSee('S 0/10');
    }

    public function test_show_page_asks_for_size_output_during_mass_qc(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();
        $this->readyForSizedQc($prod, ['S' => 10, 'M' => 30]);

        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()->assertSee('size_qty[S]', false)->assertDontSee('size_output[S]', false);
    }

    public function test_legacy_batch_still_asks_for_size_output_during_mass_qc(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();
        $this->readyForMassQc($prod, 40);

        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()->assertSee('size_output[S]', false);
    }

    public function test_store_creates_per_size_rows_for_each_mass_stage(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();

        $this->assertSame(['S' => 10, 'M' => 30], $this->stage($prod, 'mass', 'cutting')->sizeRows->pluck('input_qty', 'size')->all());
        $this->assertSame(['S' => 0, 'M' => 0], $this->stage($prod, 'mass', 'sewing')->sizeRows->pluck('input_qty', 'size')->all());
        $this->assertSame(0, ProductionStageSize::whereIn('production_stage_id', $prod->stages()->where('phase', '!=', 'mass')->pluck('id'))->count());
    }

    public function test_per_size_output_flows_into_the_same_size_of_the_next_stage(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();
        $prod->stages()->where('phase', '!=', 'mass')->update(['status' => 'completed']);
        $prod->forceFill(['sample_approved_at' => now()])->save();
        $cutting = $this->stage($prod, 'mass', 'cutting');
        $cutting->update(['status' => 'in_progress']);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'size_qty' => ['S' => 4, 'M' => 25],
            ])
            ->assertSessionHasNoErrors();

        $cutting = $cutting->fresh('sizeRows');
        $sewing = $this->stage($prod, 'mass', 'sewing')->load('sizeRows');
        $this->assertSame(['S' => 6, 'M' => 5], $cutting->sizeRows->pluck('input_qty', 'size')->all());
        $this->assertSame(['S' => 4, 'M' => 25], $cutting->sizeRows->pluck('output_qty', 'size')->all());
        $this->assertSame([11, 29], [$cutting->input_qty, $cutting->output_qty]);
        $this->assertSame(['S' => 4, 'M' => 25], $sewing->sizeRows->pluck('input_qty', 'size')->all());
        $this->assertSame(29, $sewing->input_qty);
    }

    public function test_per_size_output_cannot_exceed_what_the_size_received(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();
        $qc = $this->readyForSizedQc($prod, ['S' => 10, 'M' => 30]);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'finish', 'size_qty' => ['S' => 12, 'M' => 26],
            ])
            ->assertSessionHasErrors('size_qty');

        $this->assertNotSame('completed', $prod->fresh()->status);
    }

    public function test_per_size_output_cannot_drop_below_what_the_next_stage_already_used(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();
        $this->readyForSizedQc($prod, ['S' => 10, 'M' => 30]);
        // Sewing S meneruskan 10 ke QC; QC sudah mengerjakan 8, jadi sewing S minimal 8.
        $this->stage($prod, 'mass', 'qc_packing')->sizeRows()->where('size', 'S')->update(['input_qty' => 2, 'output_qty' => 8]);
        $sewing = $this->stage($prod, 'mass', 'sewing');

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $sewing]), [
                'action' => 'save', 'size_qty' => ['S' => 7, 'M' => 30],
            ])
            ->assertSessionHasErrors('size_qty');
    }

    public function test_update_adjusts_the_remaining_cutting_per_size(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();
        $cutting = $this->stage($prod, 'mass', 'cutting');
        $cutting->sizeRows()->where('size', 'M')->update(['input_qty' => 20, 'output_qty' => 10]);

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'sizes' => ['M' => 15, 'L' => 5], 'start_date' => now()->toDateString(), 'status' => 'planned',
            ])
            ->assertSessionHasNoErrors();

        $cutting = $cutting->fresh('sizeRows');
        $this->assertSame(['M' => 5, 'L' => 5], $cutting->sizeRows->pluck('input_qty', 'size')->all());
        $this->assertSame(10, $cutting->input_qty);
        $this->assertSame(['M', 'L'], $this->stage($prod, 'mass', 'sewing')->sizeRows->pluck('size')->all());

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'sizes' => ['M' => 8, 'L' => 5], 'start_date' => now()->toDateString(), 'status' => 'planned',
            ])
            ->assertSessionHasErrors('sizes');
    }
}
