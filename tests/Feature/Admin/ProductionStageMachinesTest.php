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

    public function test_the_form_shows_every_selected_machine_as_ticked(): void
    {
        $first = $this->machine('Potong A');
        $second = $this->machine('Potong B');
        $this->machine('Potong C');
        $this->cutting->machines()->sync([$first->id, $second->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.productions.show', $this->batch))->assertOk();

        $response->assertSee('name="production_machine_ids[]"', false);
        $this->assertSame(2, preg_match_all('/name="production_machine_ids\[\]" value="\d+"\s+checked/', $response->getContent()));
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
