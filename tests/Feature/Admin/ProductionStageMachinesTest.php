<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\MachineCategory;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionMachine;
use App\Models\ProductionStage;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionStageMachinesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Production $batch;

    private ProductionStage $cutting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
        $product = Product::create([
            'category_id' => Category::create(['name' => 'Gamis'])->id,
            'name' => 'Gamis A', 'sku' => 'GA-1', 'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
        ProductSize::create(['product_id' => $product->id, 'size' => 'M', 'stock' => 0]);

        $this->actingAs($this->admin)->post(route('admin.productions.store'), [
            'product_id' => $product->id,
            'start_date' => now()->toDateString(),
            'sizes' => ['M' => 10],
        ])->assertSessionHasNoErrors();

        $this->batch = Production::latest('id')->first();
        $this->batch->stages()->where('phase', '!=', 'mass')->update(['status' => 'completed']);
        $this->batch->forceFill(['sample_approved_at' => now()])->save();
        $this->cutting = $this->batch->stages()->where('phase', 'mass')->where('stage', 'cutting')->first();
    }

    private function machine(string $name, string $stage = 'cutting', string $status = 'active'): ProductionMachine
    {
        $category = MachineCategory::firstOrCreate(['stage' => $stage], ['name' => 'Kategori '.$stage, 'code' => 'CAT-'.$stage]);

        return ProductionMachine::create(['name' => $name, 'status' => $status, 'machine_category_id' => $category->id]);
    }

    private function saveStage(array $payload)
    {
        return $this->actingAs($this->admin)->patch(
            route('admin.productions.stage', [$this->batch, $this->cutting]),
            ['action' => 'save'] + $payload,
        );
    }

    public function test_a_stage_can_use_several_machines(): void
    {
        $first = $this->machine('Potong A');
        $second = $this->machine('Potong B');

        $this->saveStage(['production_machine_ids' => ['', $first->id, $second->id]])->assertSessionHasNoErrors();

        $this->assertSame(
            [$first->id, $second->id],
            $this->cutting->machines()->pluck('production_machines.id')->all(),
        );
    }

    private function cuttingForm(): string
    {
        $html = $this->actingAs($this->admin)->get(route('admin.productions.show', $this->batch))->assertOk()->getContent();
        $action = route('admin.productions.stage', [$this->batch, $this->cutting]);
        $start = strpos($html, 'action="'.$action.'"');
        $this->assertNotFalse($start, 'Form tahap cutting tidak ditemukan.');

        return substr($html, $start, strpos($html, '</form>', $start) - $start);
    }

    /** Baris mesin yang dirender, tanpa template baris kosong. */
    private function machineRows(string $form): array
    {
        $form = preg_replace('/<template.*?<\/template>/s', '', $form);
        preg_match_all('/<div class="[^"]*\bjs-machine-row(?:\s[^"]*)?">.*?<\/div>/s', $form, $rows);

        return $rows[0];
    }

    public function test_the_form_shows_one_selected_row_per_machine(): void
    {
        $first = $this->machine('Potong A');
        $second = $this->machine('Potong B');
        $this->machine('Potong C');
        $this->cutting->machines()->sync([$first->id, $second->id]);

        $form = $this->cuttingForm();
        $rows = $this->machineRows($form);

        $this->assertStringContainsString('<input type="hidden" name="production_machine_ids[]" value="">', $form);
        $this->assertStringNotContainsString('type="checkbox"', $form);
        $this->assertCount(2, $rows);
        foreach ([$first, $second] as $i => $machine) {
            $this->assertStringContainsString('<select name="production_machine_ids[]"', $rows[$i]);
            $this->assertSame(1, preg_match_all('/<option value="\d+"\s+selected/', $rows[$i]));
            $this->assertMatchesRegularExpression('/<option value="'.$machine->id.'"\s+selected/', $rows[$i]);
            $this->assertStringContainsString('js-machine-remove', $rows[$i]);
        }
        $this->assertStringContainsString('+ Tambah Mesin', $form);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*js-machine-add[^>]*disabled/', $form);
    }

    public function test_a_stage_without_machines_starts_with_no_rows_and_an_add_button(): void
    {
        $this->machine('Potong A');

        $form = $this->cuttingForm();

        $this->assertCount(0, $this->machineRows($form));
        $this->assertStringContainsString('Belum ada mesin dipilih.', $form);
        $this->assertStringContainsString('+ Tambah Mesin', $form);
        // Template baris baru ikut dirender agar tombol tambah bisa menyalinnya.
        $this->assertStringContainsString('js-machine-template', $form);
    }

    public function test_the_add_button_is_disabled_when_every_machine_is_chosen(): void
    {
        $only = $this->machine('Potong A');
        $this->cutting->machines()->sync([$only->id]);

        $this->assertMatchesRegularExpression('/<button[^>]*js-machine-add[^>]*disabled/s', $this->cuttingForm());
    }

    public function test_a_closed_stage_shows_its_machines_with_every_control_disabled(): void
    {
        $machine = $this->machine('Potong A');
        $this->machine('Potong B');
        $this->cutting->machines()->sync([$machine->id]);
        $this->cutting->update(['status' => 'completed', 'input_qty' => 0]);

        $form = $this->cuttingForm();
        $rows = $this->machineRows($form);

        $this->assertStringContainsString('data-closed', $form);
        $this->assertCount(1, $rows);
        $this->assertMatchesRegularExpression('/<select name="production_machine_ids\[\]"[^>]*disabled/', $rows[0]);
        $this->assertMatchesRegularExpression('/<option value="'.$machine->id.'"\s+selected/', $rows[0]);
        $this->assertMatchesRegularExpression('/<button[^>]*js-machine-remove[^>]*disabled/s', $rows[0]);
        $this->assertMatchesRegularExpression('/<button[^>]*js-machine-add[^>]*disabled/s', $form);
    }

    public function test_a_stage_with_no_machine_available_keeps_the_message(): void
    {
        $form = $this->cuttingForm();

        $this->assertStringContainsString('Belum ada mesin untuk tahap ini.', $form);
        $this->assertStringNotContainsString('+ Tambah Mesin', $form);
    }

    public function test_rows_from_a_failed_submit_are_shown_again(): void
    {
        $machine = $this->machine('Potong A');

        $this->saveStage(['_stage' => $this->cutting->id, 'production_machine_ids' => ['', $machine->id, 999]])
            ->assertSessionHasErrors('production_machine_ids.2');

        $rows = $this->machineRows($this->cuttingForm());

        $this->assertCount(2, $rows);
        $this->assertMatchesRegularExpression('/<option value="'.$machine->id.'"\s+selected/', $rows[0]);
    }

    public function test_two_rows_and_an_empty_row_save_both_machines(): void
    {
        $first = $this->machine('Potong A');
        $second = $this->machine('Potong B');

        // Penanda kosong, dua baris terpilih, dan satu baris yang belum dipilih.
        $this->saveStage(['production_machine_ids' => ['', $second->id, '', $first->id]])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $this->cutting->machines()->pluck('production_machines.id')->all(),
        );
    }

    public function test_unticking_every_machine_clears_the_stage(): void
    {
        $this->cutting->machines()->sync([$this->machine('Potong A')->id]);

        // Formulir selalu mengirim satu nilai kosong supaya kosong berarti "tidak ada mesin".
        $this->saveStage(['production_machine_ids' => ['']])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->cutting->machines()->count());
    }

    public function test_machines_are_kept_when_the_field_is_not_sent(): void
    {
        $machine = $this->machine('Potong A');
        $this->cutting->machines()->sync([$machine->id]);

        $this->saveStage(['qty' => 0])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->cutting->machines()->count());
    }

    public function test_a_machine_from_another_stage_is_rejected_and_nothing_changes(): void
    {
        $cutter = $this->machine('Potong A');
        $sewer = $this->machine('Jahit A', 'sewing');

        $this->saveStage(['production_machine_ids' => [$cutter->id, $sewer->id]])->assertSessionHas('error');

        $this->assertSame(0, $this->cutting->machines()->count());
    }

    public function test_an_unknown_machine_is_rejected(): void
    {
        $this->saveStage(['production_machine_ids' => [999]])->assertSessionHasErrors('production_machine_ids.0');
    }

    public function test_a_machine_under_maintenance_stays_listed_so_saving_does_not_drop_it(): void
    {
        $machine = $this->machine('Potong A');
        $this->cutting->machines()->sync([$machine->id]);
        $machine->update(['status' => 'maintenance']);

        $this->actingAs($this->admin)->get(route('admin.productions.show', $this->batch))
            ->assertSee('Potong A');

        $this->saveStage(['production_machine_ids' => [$machine->id]])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->cutting->machines()->count());
    }

    public function test_deleting_a_machine_removes_it_from_the_stage(): void
    {
        $machine = $this->machine('Potong A');
        $other = $this->machine('Potong B');
        $this->cutting->machines()->sync([$machine->id, $other->id]);

        $machine->delete();

        $this->assertSame([$other->id], $this->cutting->machines()->pluck('production_machines.id')->all());
    }
}
