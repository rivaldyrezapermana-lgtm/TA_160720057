<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRecipePerSizeTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Material $kain;

    private Material $label;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create([
            'category_id' => Category::create(['name' => 'Gamis'])->id,
            'name' => 'Gamis A', 'sku' => 'GA-1', 'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
        foreach (['S', 'M', 'L'] as $size) {
            ProductSize::create(['product_id' => $this->product->id, 'size' => $size, 'stock' => 0]);
        }
        $this->kain = Material::create(['name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'stock' => 100, 'min_stock' => 0, 'unit_cost' => 40000]);
        $this->label = Material::create(['name' => 'Label', 'code' => 'L1', 'unit' => 'pcs', 'stock' => 100, 'min_stock' => 0, 'unit_cost' => 1000]);

        foreach (['S' => 2, 'M' => 2.25, 'L' => 2.5] as $size => $qty) {
            $this->product->materials()->create(['material_id' => $this->kain->id, 'size' => $size, 'qty_required' => $qty]);
            $this->product->materials()->create(['material_id' => $this->label->id, 'size' => $size, 'qty_required' => 1]);
        }
    }

    public function test_hpp_differs_per_size(): void
    {
        $this->assertEquals(['S' => 81000.0, 'M' => 91000.0, 'L' => 101000.0], $this->product->hppBySize());
    }

    public function test_hpp_without_a_size_is_the_highest(): void
    {
        $this->assertEquals(101000.0, $this->product->computeHpp());
        $this->assertEquals(81000.0, $this->product->computeHpp('S'));
    }

    public function test_hpp_follows_the_latest_material_price(): void
    {
        $this->kain->update(['unit_cost' => 50000]);

        $this->assertEquals(101000.0, $this->product->hppBySize()['S']);
    }

    public function test_receiving_a_purchase_averages_the_price_and_updates_hpp(): void
    {
        // Stok 100 @ Rp 40.000 + 100 @ Rp 50.000 = rata-rata Rp 45.000.
        $this->kain->receivePurchase(100, 50000);

        $this->assertEquals(200.0, (float) $this->kain->stock);
        $this->assertEquals(45000.0, (float) $this->kain->fresh()->unit_cost);
        // S: 2 × 45.000 + 1 × 1.000. L: 2,5 × 45.000 + 1.000 menjadi catatan HPP produk.
        $this->assertEquals(91000.0, $this->product->hppBySize()['S']);
        $this->assertEquals(113500.0, (float) $this->product->fresh()->hpp);
    }

    public function test_empty_or_negative_stock_takes_the_purchase_price(): void
    {
        $this->kain->update(['stock' => -5]);

        $this->kain->receivePurchase(10, 50000);

        $this->assertEquals(5.0, (float) $this->kain->fresh()->stock);
        $this->assertEquals(50000.0, (float) $this->kain->fresh()->unit_cost);
    }

    public function test_correcting_a_material_price_updates_product_hpp(): void
    {
        $admin = User::create(['name' => 'Op', 'email' => 'op2@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->put(route('admin.materials.update', $this->kain), [
            'name' => 'Kain', 'code' => 'K1', 'unit' => 'm', 'min_stock' => 0, 'unit_cost' => 50000,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(126000.0, (float) $this->product->fresh()->hpp);
    }

    public function test_a_size_without_its_own_recipe_uses_the_generic_lines(): void
    {
        ProductSize::create(['product_id' => $this->product->id, 'size' => 'XL', 'stock' => 0]);

        $this->assertEquals(0.0, $this->product->hppBySize()['XL']);

        $this->product->materials()->create(['material_id' => $this->label->id, 'qty_required' => 3]);

        $this->assertEquals(3000.0, $this->product->hppBySize()['XL']);
        $this->assertEquals(81000.0, $this->product->hppBySize()['S']);
    }

    public function test_finishing_a_batch_consumes_each_sizes_own_recipe_plus_the_sample(): void
    {
        $admin = User::create(['name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('admin.productions.store'), [
            'product_id' => $this->product->id,
            'start_date' => now()->toDateString(),
            'sizes' => ['S' => 4, 'L' => 2],
            'sample_size' => 'S',
        ])->assertSessionHasNoErrors();

        $batch = Production::latest('id')->first();
        $batch->stages()->where('phase', '!=', 'mass')->update(['status' => 'completed']);
        $batch->forceFill(['sample_approved_at' => now()])->save();

        foreach ($batch->stages()->where('phase', 'mass')->orderBy('sort_order')->get() as $stage) {
            $this->actingAs($admin)->patch(route('admin.productions.stage', [$batch, $stage]), ['action' => 'start'])->assertSessionHas('success');
            $this->actingAs($admin)->patch(route('admin.productions.stage', [$batch, $stage]), [
                'action' => 'finish', 'size_qty' => ['S' => 4, 'L' => 2],
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame('completed', $batch->fresh()->status);

        // Kain: S (4 + 1 sampel) × 2 + L 2 × 2,5 = 15 m. Label: 7 pcs.
        $this->assertEquals(85.0, (float) $this->kain->fresh()->stock);
        $this->assertEquals(93.0, (float) $this->label->fresh()->stock);
        $this->assertEquals(15.0, (float) $batch->productionMaterials()->where('material_id', $this->kain->id)->value('qty_used'));
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin.resep@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN]);
    }

    /** @param  array<int, array<string, mixed>>  $materials */
    private function formPayload(array $materials, array $extra = []): array
    {
        return $extra + [
            'name' => 'Gamis A',
            'category_id' => $this->product->category_id,
            'price' => 100000,
            'is_active' => 1,
            'materials' => $materials,
        ];
    }

    public function test_store_saves_per_size_rows_and_hpp_matches(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->formPayload([
            ['material_id' => $this->kain->id, 'qty' => ['S' => '2', 'M' => '2.25', 'L' => '2.5', 'XL' => '']],
            ['material_id' => $this->label->id, 'qty' => ['S' => '1', 'M' => '1', 'L' => '1', 'XL' => '0']],
        ]))->assertSessionHasNoErrors();

        $product = Product::latest('id')->first();

        $this->assertSame(6, $product->materials()->count());
        $this->assertDatabaseHas('product_materials', ['product_id' => $product->id, 'material_id' => $this->kain->id, 'size' => 'M', 'qty_required' => 2.25]);
        $this->assertEquals(['S' => 81000.0, 'M' => 91000.0, 'L' => 101000.0], $product->hppBySize());
        $this->assertEquals(101000, (float) $product->hpp);
    }

    public function test_duplicate_materials_are_rejected(): void
    {
        $before = $this->product->materials()->count();

        $this->actingAs($this->admin())->put(route('admin.products.update', $this->product), $this->formPayload([
            ['material_id' => $this->kain->id, 'qty' => ['S' => '2']],
            ['material_id' => $this->kain->id, 'qty' => ['M' => '3']],
        ]))->assertSessionHasErrors(['materials' => 'Bahan baku yang sama tidak boleh dipilih lebih dari satu kali.']);

        $this->assertSame($before, $this->product->materials()->count());
    }

    public function test_rows_without_a_material_or_quantity_are_ignored(): void
    {
        $this->actingAs($this->admin())->put(route('admin.products.update', $this->product), $this->formPayload([
            ['material_id' => '', 'qty' => ['S' => '9']],
            ['material_id' => $this->label->id, 'qty' => ['S' => '', 'M' => '0']],
            // Baris kosong dengan bahan yang sama bukan duplikat.
            ['material_id' => $this->kain->id, 'qty' => ['S' => '']],
            ['material_id' => $this->kain->id, 'qty' => ['S' => '2']],
        ]))->assertSessionHasNoErrors();

        $lines = $this->product->materials()->get();
        $this->assertCount(1, $lines);
        $this->assertSame($this->kain->id, $lines[0]->material_id);
    }

    public function test_recipe_quantity_must_be_a_non_negative_number(): void
    {
        $this->actingAs($this->admin())->put(route('admin.products.update', $this->product), $this->formPayload([
            ['material_id' => $this->kain->id, 'qty' => ['S' => '-1', 'M' => 'abc']],
        ]))->assertSessionHasErrors(['materials.0.qty.S', 'materials.0.qty.M']);
    }

    public function test_recipe_size_outside_the_master_is_rejected(): void
    {
        $this->actingAs($this->admin())->put(route('admin.products.update', $this->product), $this->formPayload([
            ['material_id' => $this->kain->id, 'qty' => ['Jumbo' => '2']],
        ]))->assertSessionHasErrors('materials');
    }

    public function test_removing_a_row_deletes_it_and_old_lines_without_a_size(): void
    {
        $this->product->materials()->create(['material_id' => $this->label->id, 'qty_required' => 3]);

        $this->actingAs($this->admin())->put(route('admin.products.update', $this->product), $this->formPayload([
            ['material_id' => $this->kain->id, 'qty' => ['S' => '2', 'M' => '2.25']],
        ]))->assertSessionHasNoErrors();

        $this->assertEquals(
            [['material_id' => $this->kain->id, 'size' => 'M', 'qty' => 2.25], ['material_id' => $this->kain->id, 'size' => 'S', 'qty' => 2.0]],
            $this->product->materials()->orderBy('size')->get()
                ->map(fn ($line) => ['material_id' => $line->material_id, 'size' => $line->size, 'qty' => (float) $line->qty_required])->all()
        );
    }

    public function test_removing_every_row_empties_the_recipe(): void
    {
        $this->actingAs($this->admin())->put(route('admin.products.update', $this->product), [
            'name' => 'Gamis A', 'category_id' => $this->product->category_id, 'price' => 100000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->product->materials()->count());
        $this->assertEquals(0, (float) $this->product->fresh()->hpp);
    }

    public function test_update_keeps_existing_stock(): void
    {
        $this->product->update(['stock' => 40]);
        $this->product->sizes()->where('size', 'M')->update(['stock' => 25, 'chest_cm' => 90]);

        $this->actingAs($this->admin())->put(route('admin.products.update', $this->product), $this->formPayload([
            ['material_id' => $this->kain->id, 'qty' => ['M' => '2', 'XL' => '3']],
        ], [
            'stock' => 999,
            'sizes' => [
                'M' => ['size' => 'M', 'chest_cm' => '96', 'length_cm' => '', 'sleeve_cm' => '', 'stock' => '999'],
                'L' => ['size' => 'L', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => ''],
                'XL' => ['size' => 'XL', 'chest_cm' => '', 'length_cm' => '', 'sleeve_cm' => ''],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(40, $this->product->fresh()->stock);
        $this->assertSame(
            ['L' => 0, 'M' => 25, 'S' => 0, 'XL' => 0],
            $this->product->sizes()->orderBy('size')->pluck('stock', 'size')->all()
        );
        $this->assertSame(96, $this->product->sizes()->where('size', 'M')->value('chest_cm'));
    }

    public function test_posted_stock_is_ignored_on_create(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->formPayload([], [
            'stock' => 77,
            'sizes' => ['M' => ['size' => 'M', 'chest_cm' => '90', 'stock' => '12']],
        ]))->assertSessionHasNoErrors();

        $product = Product::latest('id')->first();
        $this->assertSame(0, $product->stock);
        $this->assertSame(['M' => 0], $product->sizes()->pluck('stock', 'size')->all());
    }

    public function test_create_form_has_the_plus_button_and_no_checkbox_list_or_stock_input(): void
    {
        $this->actingAs($this->admin())->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('+ Tambah Bahan')
            ->assertSee('materials[__INDEX__][qty][XL]', false)
            ->assertDontSee('materials[0][material_id]', false)
            ->assertDontSee('[use]', false)
            ->assertDontSee('name="stock"', false)
            ->assertDontSee('[stock]', false);
    }

    public function test_edit_form_prefills_rows_grouped_by_material_and_shows_stock_read_only(): void
    {
        $this->product->sizes()->where('size', 'M')->update(['stock' => 25]);

        $response = $this->actingAs($this->admin())->get(route('admin.products.edit', $this->product))->assertOk();

        $response->assertSee('name="materials[0][qty][M]" value="2.25"', false)
            ->assertSee('name="materials[1][qty][L]" value="1"', false)
            ->assertDontSee('materials[2][material_id]', false)
            ->assertDontSee('[stock]', false)
            ->assertDontSee('[use]', false)
            ->assertSee('value="25"', false)
            ->assertSee('Stok bertambah otomatis dari hasil produksi.');
    }

    public function test_edit_form_spreads_a_line_without_a_size_over_every_size(): void
    {
        $this->product->materials()->delete();
        $this->product->materials()->create(['material_id' => $this->kain->id, 'qty_required' => 3]);

        $this->actingAs($this->admin())->get(route('admin.products.edit', $this->product))
            ->assertSee('name="materials[0][qty][S]" value="3"', false)
            ->assertSee('name="materials[0][qty][XL]" value="3"', false);
    }

    public function test_validation_errors_repopulate_rows_by_index(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->from(route('admin.products.edit', $this->product))
            ->followingRedirects()
            ->put(route('admin.products.update', $this->product), $this->formPayload([
                5 => ['material_id' => $this->label->id, 'qty' => ['S' => '4']],
                7 => ['material_id' => $this->label->id, 'qty' => ['M' => '6']],
            ]))
            ->assertSee('Bahan baku yang sama tidak boleh dipilih lebih dari satu kali.')
            ->assertSee('name="materials[5][qty][S]" value="4"', false)
            ->assertSee('name="materials[7][qty][M]" value="6"', false)
            ->assertDontSee('materials[0][material_id]', false)
            ->assertSee('let nextIndex = 8;', false);
    }

    public function test_price_below_hpp_warning_names_only_the_sizes_under_price(): void
    {
        $this->actingAs($this->admin())->put(route('admin.products.update', $this->product), $this->formPayload([
            ['material_id' => $this->kain->id, 'qty' => ['S' => '2', 'M' => '2.25', 'L' => '2.5']],
        ], ['price' => 95000]))->assertSessionHas(
            'warning',
            'Harga jual di bawah HPP pada ukuran L (harga Rp 95.000, HPP Rp 100.000). Periksa kembali margin.'
        );
    }

    public function test_price_warning_is_shown_on_the_product_list(): void
    {
        $this->actingAs($this->admin())->withSession(['warning' => 'Harga jual di bawah HPP ukuran L.'])
            ->get(route('admin.products.index'))
            ->assertSee('Harga jual di bawah HPP ukuran L.');
    }

    public function test_price_at_or_above_every_hpp_has_no_warning(): void
    {
        $this->actingAs($this->admin())->put(route('admin.products.update', $this->product), $this->formPayload([
            ['material_id' => $this->kain->id, 'qty' => ['L' => '2.5']],
        ]))->assertSessionMissing('warning');
    }

    public function test_show_page_lists_hpp_margin_and_recipe_per_size(): void
    {
        $this->actingAs($this->admin())->get(route('admin.products.show', $this->product))
            ->assertOk()
            ->assertSeeInOrder(['HPP per Ukuran', 'S', 'Rp 81.000', 'Rp 100.000', 'Rp 19.000', 'L', 'Rp 101.000', 'Rp 100.000', '-Rp 1.000'])
            ->assertSeeInOrder(['Bahan Baku (Resep)', 'Kain', '2', '2,25', '2,5', 'm', 'Label', '1', '1', '1', 'pcs']);
    }

    public function test_show_page_handles_a_product_with_only_generic_lines_and_no_sizes(): void
    {
        $this->product->sizes()->delete();
        $this->product->materials()->delete();
        $this->product->materials()->create(['material_id' => $this->kain->id, 'qty_required' => 1.5]);

        $this->actingAs($this->admin())->get(route('admin.products.show', $this->product))
            ->assertOk()
            ->assertSeeInOrder(['Semua Ukuran', 'Rp 60.000'])
            ->assertSeeInOrder(['Kain', '1,5', 'm']);
    }

    public function test_product_list_shows_the_hpp_range(): void
    {
        $this->actingAs($this->admin())->getJson(route('admin.datatables.products'))
            ->assertJsonFragment(['sku' => 'GA-1', 'hpp' => 'Rp 81.000 – Rp 101.000']);
    }

    public function test_material_quantity_label_drops_trailing_zeros(): void
    {
        $this->assertSame('12', Material::formatQty('12.00'));
        $this->assertSame('12,5', Material::formatQty('12.50'));
        $this->assertSame('1.500', Material::formatQty(1500));
    }
}
