<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionStage;
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

    /** Lompati persiapan, sampel, dan dua tahap massal pertama; QC massal sedang berjalan. */
    private function readyForMassQc(Production $p, int $qty): ProductionStage
    {
        $p->stages()->where('phase', '!=', 'mass')->update(['status' => 'completed', 'output_qty' => 1]);
        $p->forceFill(['sample_approved_at' => now()])->save();
        $p->stages()->where('phase', 'mass')->update(['input_qty' => $qty, 'output_qty' => $qty, 'status' => 'in_progress']);

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
        $qc = $this->readyForMassQc($prod, 38);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'finish', 'input_qty' => 38, 'output_qty' => 38,
                'size_output' => ['S' => 9, 'M' => 29],
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
        $qc = $this->readyForMassQc($prod, 18);

        $this->actingAs($this->admin())
            ->patch(route('admin.productions.stage', [$prod, $qc]), [
                'action' => 'finish', 'input_qty' => 18, 'output_qty' => 18,
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
        $this->readyForMassQc($prod, 40);

        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()->assertSee('size_output[S]', false);
    }
}
