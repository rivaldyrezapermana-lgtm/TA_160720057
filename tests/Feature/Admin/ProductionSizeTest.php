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

    public function test_store_skips_the_sample_size_when_the_product_already_has_a_sample(): void
    {
        $product = $this->product();
        $this->store($product, ['sizes' => ['S' => 10, 'M' => 30]]);
        $first = Production::latest('id')->first();
        $first->forceFill(['sample_approved_at' => now()])->save();

        // Ukuran sampel tidak dipakai, jadi isian yang tidak valid pun tidak menggagalkan.
        $this->store($product, ['sizes' => ['S' => 5, 'M' => 5], 'sample_size' => 'L'])
            ->assertSessionHasNoErrors();

        $prod = Production::latest('id')->first();
        $this->assertSame($first->id, $prod->sample_source_production_id);
        $this->assertNull($prod->sample_size);
    }

    public function test_create_page_knows_which_products_already_have_a_sample(): void
    {
        $withSample = $this->product();
        $this->store($withSample, ['sizes' => ['S' => 10, 'M' => 30]]);
        Production::latest('id')->first()->forceFill(['sample_approved_at' => now()])->save();

        $this->actingAs($this->admin())->get(route('admin.productions.create'))
            ->assertOk()
            ->assertViewHas('productsWithSample', fn ($ids) => collect($ids)->map(fn ($id) => (int) $id)->all() === [$withSample->id]);
    }

    public function test_reusing_a_sample_clears_the_sample_size(): void
    {
        $product = $this->product();
        $this->store($product, ['sizes' => ['S' => 10, 'M' => 30]]);
        $first = Production::latest('id')->first();
        $this->store($product, ['sizes' => ['S' => 5, 'M' => 8]]);
        $second = Production::latest('id')->first();
        $this->assertSame('M', $second->sample_size);
        $first->forceFill(['sample_approved_at' => now()])->save();

        $this->actingAs($this->admin())->post(route('admin.productions.check-sample', $second));

        $second->refresh();
        $this->assertSame($first->id, $second->sample_source_production_id);
        $this->assertNull($second->sample_size);
    }

    public function test_batch_reusing_a_sample_has_no_sample_size_on_edit(): void
    {
        $product = $this->product();
        $this->store($product, ['sizes' => ['S' => 10, 'M' => 30]]);
        Production::latest('id')->first()->forceFill(['sample_approved_at' => now()])->save();
        $this->store($product, ['sizes' => ['S' => 5, 'M' => 8]]);
        $prod = Production::latest('id')->first();

        $this->actingAs($this->admin())->get(route('admin.productions.edit', $prod))
            ->assertOk()->assertDontSee('name="sample_size"', false);

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'sizes' => ['S' => 5, 'M' => 10],
                'sample_size' => 'S',
                'start_date' => now()->toDateString(),
                'status' => 'planned',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($prod->fresh()->sample_size);
    }

    public function test_show_page_hides_a_leftover_sample_size_when_reusing_a_sample(): void
    {
        $product = $this->product();
        $this->store($product, ['sizes' => ['S' => 10, 'M' => 30]]);
        Production::latest('id')->first()->forceFill(['sample_approved_at' => now()])->save();
        $this->store($product, ['sizes' => ['S' => 5, 'M' => 8]]);
        $prod = Production::latest('id')->first();
        // Data lama: batch yang memakai sampel tapi masih menyimpan ukuran sampel.
        $prod->forceFill(['sample_size' => 'M'])->save();

        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()->assertDontSee('Ukuran Sampel');
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

    public function test_store_produces_only_the_submitted_subset_of_sizes(): void
    {
        $this->store($this->product(), ['sizes' => ['M' => 12]])->assertSessionHasNoErrors();

        $prod = Production::with('sizes')->latest('id')->first();
        $this->assertSame(['M' => 12], $prod->sizes->pluck('planned_qty', 'size')->all());
        $this->assertSame(12, (int) $prod->planned_qty);
        $this->assertSame(['M'], $this->stage($prod, 'mass', 'cutting')->sizeRows->pluck('size')->all());
    }

    public function test_store_orders_size_rows_by_the_master_list(): void
    {
        $this->store($this->product(), ['sizes' => ['XL' => 3, 'S' => 5]])->assertSessionHasNoErrors();

        $this->assertSame(['S', 'XL'], Production::latest('id')->first()->sizes->pluck('size')->all());
    }

    public function test_master_size_without_a_variant_creates_the_variant_and_the_batch(): void
    {
        $product = $this->product();

        $this->store($product, ['sizes' => ['XL' => 20]])->assertSessionHasNoErrors();

        $prod = Production::with('sizes')->latest('id')->first();
        $this->assertSame(['XL' => 20], $prod->sizes->pluck('planned_qty', 'size')->all());
        $this->assertSame(0, $product->sizes()->where('size', 'XL')->value('stock'));
    }

    public function test_finishing_a_batch_credits_a_newly_created_variant(): void
    {
        $product = $this->product();
        $this->store($product, ['sizes' => ['XL' => 20]]);
        $prod = Production::latest('id')->first();
        $qc = $this->readyForSizedQc($prod, ['XL' => 20]);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), ['action' => 'finish', 'size_qty' => ['XL' => 20]])
            ->assertSessionHasNoErrors();

        $this->assertSame(20, $product->sizes()->where('size', 'XL')->value('stock'));
    }

    public function test_store_rejects_an_unknown_size(): void
    {
        $product = $this->product();

        $this->store($product, ['sizes' => ['S' => 5, 'JUMBO' => 3]])->assertSessionHasErrors('sizes');

        $this->assertSame(0, Production::count());
        $this->assertFalse($product->sizes()->where('size', 'JUMBO')->exists());
    }

    public function test_store_accepts_a_product_size_that_is_not_in_the_master(): void
    {
        $this->store($this->product(['S', 'XXXL']), ['sizes' => ['XXXL' => 4]])->assertSessionHasNoErrors();

        $this->assertSame(['XXXL'], Production::latest('id')->first()->sizes->pluck('size')->all());
    }

    public function test_product_without_variants_can_be_detailed_per_size(): void
    {
        $product = $this->product([]);

        $this->store($product, ['sizes' => ['M' => 6]])->assertSessionHasNoErrors();

        $this->assertSame(['M' => 6], Production::latest('id')->first()->sizes->pluck('planned_qty', 'size')->all());
        $this->assertTrue($product->sizes()->where('size', 'M')->exists());
    }

    public function test_product_without_variants_still_rejects_an_unknown_size(): void
    {
        $this->store($this->product([]), ['planned_qty' => 25, 'sizes' => ['JUMBO' => 3]])
            ->assertSessionHasErrors('sizes');

        $this->assertSame(0, Production::count());
    }

    public function test_update_can_add_a_master_size_and_drop_an_uncut_size(): void
    {
        $product = $this->product();
        $this->store($product, ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'sizes' => ['M' => 30, 'XL' => 5], 'start_date' => now()->toDateString(), 'status' => 'planned',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['M' => 30, 'XL' => 5], $prod->fresh()->sizes->pluck('planned_qty', 'size')->all());
        $this->assertTrue($product->sizes()->where('size', 'XL')->exists());
    }

    public function test_update_rejects_an_unknown_size(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();

        $this->actingAs($this->admin())
            ->put(route('admin.productions.update', $prod), [
                'sizes' => ['S' => 10, 'JUMBO' => 5], 'start_date' => now()->toDateString(), 'status' => 'planned',
            ])
            ->assertSessionHasErrors('sizes');

        $this->assertSame(40, (int) $prod->fresh()->planned_qty);
    }

    public function test_create_form_lists_master_sizes_in_order_plus_the_products_own(): void
    {
        $product = $this->product(['M', 'XXXL']);

        $this->actingAs($this->admin())->get(route('admin.productions.create'))
            ->assertOk()
            ->assertViewHas('productSizes', fn ($sizes) => $sizes[$product->id] === ['S', 'M', 'L', 'XL', 'XXXL'])
            ->assertViewHas('productVariants', fn ($variants) => $variants[$product->id]->all() === ['M', 'XXXL'])
            ->assertSee('"'.$product->id.'":["S","M","L","XL","XXXL"]', false);
    }

    public function test_edit_form_ticks_only_the_batch_sizes_and_disables_the_rest(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();

        $html = $this->actingAs($this->admin())->get(route('admin.productions.edit', $prod))
            ->assertOk()->assertSee('sizes[XL]', false)->getContent();

        $this->assertSame(2, preg_match_all('/class="size-tick"\s+checked/', $html));
        $this->assertMatchesRegularExpression('/name="sizes\[XL\]"[^>]*disabled/', $html);
    }

    public function test_edit_form_keeps_a_size_that_is_already_cut_ticked(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();
        $this->stage($prod, 'mass', 'cutting')->sizeRows()->where('size', 'M')->update(['input_qty' => 20, 'output_qty' => 10]);

        $this->actingAs($this->admin())->get(route('admin.productions.edit', $prod))
            ->assertOk()->assertSee('Sudah dipotong 10 pcs');
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

    public function test_per_size_qty_is_subtracted_from_that_sizes_remaining(): void
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

        // Simpan lagi: angkanya mengurangi sisa tiap ukuran, bukan mengganti outputnya.
        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $cutting]), [
                'action' => 'save', 'size_qty' => ['S' => 2, 'M' => 5],
            ])
            ->assertSessionHasNoErrors();

        $cutting = $cutting->fresh('sizeRows');
        $sewing = $sewing->fresh('sizeRows');
        $this->assertSame(['S' => 4, 'M' => 0], $cutting->sizeRows->pluck('input_qty', 'size')->all());
        $this->assertSame(['S' => 6, 'M' => 30], $cutting->sizeRows->pluck('output_qty', 'size')->all());
        $this->assertSame([4, 36], [$cutting->input_qty, $cutting->output_qty]);
        $this->assertSame(['S' => 6, 'M' => 30], $sewing->sizeRows->pluck('input_qty', 'size')->all());
        $this->assertSame(36, $sewing->input_qty);
    }

    public function test_per_size_qty_cannot_exceed_that_sizes_remaining(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();
        $qc = $this->readyForSizedQc($prod, ['S' => 10, 'M' => 30]);
        // QC S menerima 10 dan sudah mengerjakan 8: sisanya tinggal 2.
        $qc->sizeRows()->where('size', 'S')->update(['input_qty' => 2, 'output_qty' => 8]);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'save', 'size_qty' => ['S' => 5, 'M' => 0],
            ])
            ->assertSessionHasErrors(['size_qty' => 'Ukuran S maksimal 2 pcs (sisa tahap ini).']);

        $row = $qc->sizeRows()->where('size', 'S')->first();
        $this->assertSame([2, 8], [$row->input_qty, $row->output_qty]);
    }

    public function test_a_rejected_size_output_stays_on_its_own_stage(): void
    {
        $this->store($this->product(), ['sizes' => ['S' => 10, 'M' => 30]]);
        $prod = Production::latest('id')->first();
        $qc = $this->readyForSizedQc($prod, ['S' => 10, 'M' => 30]);
        $show = route('admin.productions.show', $prod);

        // QC S hanya menerima 10; 99 ditolak dan dikembalikan ke form.
        $html = $this->actingAs($this->admin())->from($show)->followingRedirects()
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                '_stage' => $qc->id, 'action' => 'save', 'size_qty' => ['S' => 99, 'M' => 0],
            ])
            ->assertOk()
            ->getContent();

        // Angka 99 hanya muncul di form QC, bukan di kolom Output cutting/sewing.
        $this->assertSame(1, substr_count($html, 'value="99"'));
        // Pesan error: sekali di ringkasan atas halaman, sekali di bawah tabel QC saja.
        $this->assertSame(2, substr_count($html, 'maksimal 10 pcs'));
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
