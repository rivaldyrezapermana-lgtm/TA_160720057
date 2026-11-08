<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\MachineCategory;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionMachine;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionShowPageTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    private function admin(): User
    {
        return $this->user ??= User::create([
            'name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function batch(array $sizes = ['S' => 10, 'M' => 30]): Production
    {
        $product = Product::create([
            'category_id' => Category::create(['name' => 'Gamis'])->id,
            'name' => 'Gamis A', 'sku' => 'GA-1', 'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
        foreach (array_keys($sizes) as $size) {
            ProductSize::create(['product_id' => $product->id, 'size' => $size, 'stock' => 0]);
        }

        $this->actingAs($this->admin())->post(route('admin.productions.store'), [
            'product_id' => $product->id,
            'start_date' => now()->toDateString(),
            'sizes' => $sizes,
        ])->assertSessionHasNoErrors();

        return Production::latest('id')->first();
    }

    private function machine(string $name, string $kode, string $stage = 'cutting'): ProductionMachine
    {
        $category = MachineCategory::firstOrCreate(['stage' => $stage], ['name' => 'Kategori '.$stage, 'code' => 'CAT-'.$stage]);

        return ProductionMachine::create([
            'name' => $name, 'kode_mesin' => $kode, 'status' => 'active', 'machine_category_id' => $category->id,
        ]);
    }

    public function test_stage_buttons_sit_after_the_size_table_at_the_bottom_of_the_form(): void
    {
        $prod = $this->batch();
        $prod->stages()->where('phase', '!=', 'mass')->update(['status' => 'completed']);
        $prod->forceFill(['sample_approved_at' => now()])->save();

        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()
            ->assertSeeInOrder(['size_qty[S]', 'size_qty[M]', 'justify-end', 'value="start"', '</form>'], false);
    }

    public function test_completed_batch_has_no_empty_button_row(): void
    {
        $prod = $this->batch();
        $prod->forceFill(['status' => 'completed'])->save();

        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()
            ->assertDontSee('md:col-span-4 flex justify-end', false);
    }

    public function test_stage_machine_picker_uses_the_machine_code_and_orders_by_it(): void
    {
        $this->machine('Mesin Zeta', 'MSN-001');
        $this->machine('Mesin Alfa', 'MSN-002');
        $prod = $this->batch();
        $prod->stages()->where('phase', '!=', 'mass')->update(['status' => 'completed']);
        $prod->forceFill(['sample_approved_at' => now()])->save();

        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()
            ->assertSeeInOrder(['MSN-001 — Mesin Zeta', 'MSN-002 — Mesin Alfa']);
    }

    public function test_batch_machine_is_shown_and_selected_by_display_name(): void
    {
        $machine = $this->machine('Mesin Jahit Juki', 'MSN-007');
        $prod = $this->batch();
        $prod->forceFill(['production_machine_id' => $machine->id])->save();

        $this->actingAs($this->admin())->get(route('admin.productions.show', $prod))
            ->assertOk()->assertSee('MSN-007 — Mesin Jahit Juki');

        $this->actingAs($this->admin())->get(route('admin.productions.edit', $prod))
            ->assertOk()->assertSee('MSN-007 — Mesin Jahit Juki');
    }
}
