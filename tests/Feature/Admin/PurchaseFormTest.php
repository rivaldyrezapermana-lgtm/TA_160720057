<?php

namespace Tests\Feature\Admin;

use App\Models\Material;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PurchaseFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Material $kain;

    private Material $kancing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin.uji@labasa.test',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
        $this->supplier = Supplier::create(['name' => 'Pemasok Uji']);
        $this->kain = Material::create([
            'name' => 'Kain Katun', 'code' => 'K1', 'unit' => 'meter',
            'stock' => 12.5, 'min_stock' => 0, 'unit_cost' => 25000,
        ]);
        $this->kancing = Material::create([
            'name' => 'Kancing Bulat', 'code' => 'K2', 'unit' => 'pcs',
            'stock' => 0, 'min_stock' => 0, 'unit_cost' => 500,
        ]);
    }

    private function submit(array $items): TestResponse
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.purchases.create'))
            ->post(route('admin.purchases.store'), [
                'supplier_id' => $this->supplier->id,
                'purchase_date' => '2026-11-09',
                'items' => $items,
            ]);
    }

    public function test_typed_price_is_stored_and_total_is_correct(): void
    {
        $this->submit([
            ['material_id' => $this->kain->id, 'qty' => 3, 'unit_cost' => 30000],
            ['material_id' => $this->kancing->id, 'qty' => 10, 'unit_cost' => 450.5],
        ])->assertRedirect();

        $purchase = Purchase::with('items')->sole();

        $this->assertSame('pending', $purchase->status);
        $this->assertEquals(94505, (float) $purchase->total);
        $this->assertCount(2, $purchase->items);

        $kain = $purchase->items->firstWhere('material_id', $this->kain->id);
        $this->assertEquals(30000, (float) $kain->unit_cost);
        $this->assertEquals(90000, (float) $kain->subtotal);

        $kancing = $purchase->items->firstWhere('material_id', $this->kancing->id);
        $this->assertEquals(450.5, (float) $kancing->unit_cost);
        $this->assertEquals(4505, (float) $kancing->subtotal);
    }

    public function test_material_unit_cost_is_not_changed(): void
    {
        $this->submit([
            ['material_id' => $this->kain->id, 'qty' => 3, 'unit_cost' => 30000],
        ])->assertRedirect();

        $this->assertEquals(25000, (float) $this->kain->fresh()->unit_cost);
    }

    public function test_price_of_zero_is_accepted(): void
    {
        $this->submit([
            ['material_id' => $this->kain->id, 'qty' => 3, 'unit_cost' => 0],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(0, (float) Purchase::sole()->total);
    }

    public function test_show_page_displays_the_stored_prices(): void
    {
        $this->submit([
            ['material_id' => $this->kain->id, 'qty' => 3, 'unit_cost' => 30000],
            ['material_id' => $this->kancing->id, 'qty' => 3, 'unit_cost' => 12.5],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.purchases.show', Purchase::sole()))
            ->assertOk()
            ->assertSee('Rp 30.000')
            ->assertSee('Rp 90.000')
            ->assertSee('Rp 12,50')
            ->assertSee('Rp 37,50')
            ->assertSee('Rp 90.037,50')
            ->assertDontSee('Rp 25.000');
    }

    public function test_duplicate_material_rows_are_rejected(): void
    {
        $this->submit([
            ['material_id' => $this->kain->id, 'qty' => 3, 'unit_cost' => 30000],
            ['material_id' => $this->kain->id, 'qty' => 1, 'unit_cost' => 28000],
        ])->assertRedirect(route('admin.purchases.create'))
            ->assertSessionHasErrors(['items' => 'Bahan yang sama tidak boleh dipilih lebih dari satu kali.']);

        $this->assertSame(0, Purchase::count());
    }

    public function test_missing_price_is_rejected(): void
    {
        $this->submit([
            ['material_id' => $this->kain->id, 'qty' => 3, 'unit_cost' => ''],
        ])->assertSessionHasErrors('items.0.unit_cost');

        $this->assertSame(0, Purchase::count());
    }

    public function test_negative_price_is_rejected(): void
    {
        $this->submit([
            ['material_id' => $this->kain->id, 'qty' => 3, 'unit_cost' => -1],
        ])->assertSessionHasErrors('items.0.unit_cost');
    }

    public function test_row_with_only_quantity_needs_a_material(): void
    {
        $this->submit([
            ['material_id' => '', 'qty' => 3, 'unit_cost' => 1000],
        ])->assertSessionHasErrors('items.0.material_id');
    }

    public function test_row_with_only_material_needs_a_quantity(): void
    {
        $this->submit([
            ['material_id' => $this->kain->id, 'qty' => '', 'unit_cost' => 1000],
        ])->assertSessionHasErrors('items.0.qty');
    }

    public function test_invalid_quantity_and_unknown_material_are_rejected(): void
    {
        $this->submit([
            ['material_id' => 99999, 'qty' => 0, 'unit_cost' => 1000],
            ['material_id' => $this->kain->id, 'qty' => 1.5, 'unit_cost' => 1000],
        ])->assertSessionHasErrors(['items.0.material_id', 'items.0.qty', 'items.1.qty']);

        $this->assertSame(0, Purchase::count());
    }

    public function test_empty_rows_are_ignored(): void
    {
        $this->submit([
            ['material_id' => '', 'qty' => '', 'unit_cost' => ''],
            ['material_id' => $this->kain->id, 'qty' => 2, 'unit_cost' => 26000],
            ['material_id' => '', 'qty' => '', 'unit_cost' => '5000'],
        ])->assertSessionHasNoErrors();

        $purchase = Purchase::with('items')->sole();
        $this->assertCount(1, $purchase->items);
        $this->assertEquals(52000, (float) $purchase->total);
    }

    public function test_only_empty_rows_keeps_the_existing_error_message(): void
    {
        $this->submit([
            ['material_id' => '', 'qty' => '', 'unit_cost' => ''],
        ])->assertRedirect(route('admin.purchases.create'))
            ->assertSessionHas('error', 'Pilih minimal satu bahan beserta jumlahnya.');

        $this->assertSame(0, Purchase::count());
    }

    public function test_no_items_at_all_shows_the_same_message(): void
    {
        $this->submit([])
            ->assertSessionHasErrors(['items' => 'Pilih minimal satu bahan beserta jumlahnya.']);

        $this->assertSame(0, Purchase::count());
    }

    public function test_create_form_has_select_and_add_button_without_checkboxes(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.purchases.create'))
            ->assertOk()
            ->assertSee('name="items[0][material_id]"', false)
            ->assertSee('name="items[0][qty]"', false)
            ->assertSee('name="items[0][unit_cost]"', false)
            ->assertSee('data-unit="meter"', false)
            ->assertSee('+ Tambah Bahan')
            ->assertDontSee('type="checkbox"', false)
            ->assertDontSee('[selected]', false)
            ->assertDontSee('Rp 25.000');
    }

    public function test_form_starts_with_exactly_one_empty_row(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.purchases.create'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'name="items[0][material_id]"'));
        $this->assertStringNotContainsString('name="items[1][material_id]"', $html);
    }

    public function test_old_input_and_field_errors_are_repopulated_by_row_index(): void
    {
        $response = $this->followingRedirects()->submit([
            0 => ['material_id' => $this->kain->id, 'qty' => 3, 'unit_cost' => ''],
            4 => ['material_id' => $this->kancing->id, 'qty' => 0, 'unit_cost' => 750],
        ]);

        $response->assertOk()
            ->assertSee('name="items[0][qty]"', false)
            ->assertSee('name="items[4][qty]"', false)
            ->assertSee('value="750"', false)
            ->assertSee('Harga per unit pada baris 1 wajib diisi.')
            ->assertSee('Jumlah pada baris 2 minimal 1.');

        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/<option value="'.$this->kancing->id.'"[^>]*selected/',
            $html,
        );
        $this->assertStringNotContainsString('name="items[1][qty]"', $html);
    }

    public function test_receiving_adds_stock_to_decimal_material_stock(): void
    {
        $this->submit([
            ['material_id' => $this->kain->id, 'qty' => 3, 'unit_cost' => 30000],
            ['material_id' => $this->kancing->id, 'qty' => 10, 'unit_cost' => 450],
        ]);
        $purchase = Purchase::sole();

        $this->assertEquals(12.5, (float) $this->kain->fresh()->stock);

        $this->actingAs($this->admin)
            ->patch(route('admin.purchases.receive', $purchase))
            ->assertSessionHas('success');

        $this->assertSame('received', $purchase->fresh()->status);
        $this->assertEquals(15.5, (float) $this->kain->fresh()->stock);
        $this->assertEquals(10, (float) $this->kancing->fresh()->stock);

        // Rata-rata bergerak: (12,5 × 25.000 + 3 × 30.000) / 15,5. Stok kosong mengikuti pembelian.
        $this->assertEquals(25967.74, (float) $this->kain->fresh()->unit_cost);
        $this->assertEquals(450, (float) $this->kancing->fresh()->unit_cost);

        // Receiving twice must not add the stock again.
        $this->actingAs($this->admin)
            ->patch(route('admin.purchases.receive', $purchase))
            ->assertSessionHas('error');

        $this->assertEquals(15.5, (float) $this->kain->fresh()->stock);
        $this->assertEquals(25967.74, (float) $this->kain->fresh()->unit_cost);
    }
}
