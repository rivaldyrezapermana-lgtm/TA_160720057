<?php

namespace Tests\Feature\Admin;

use App\Models\ProductionMachine;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionMachineCodeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Uji',
            'email' => 'admin.uji@labasa.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    private function machine(string $name = 'Mesin Uji'): ProductionMachine
    {
        return ProductionMachine::create(['name' => $name, 'status' => 'active']);
    }

    public function test_store_generates_sequential_codes(): void
    {
        $admin = $this->admin();

        foreach (['Mesin Pertama', 'Mesin Kedua'] as $name) {
            $this->actingAs($admin)
                ->post(route('admin.production-machines.store'), ['name' => $name, 'status' => 'active'])
                ->assertRedirectToRoute('admin.production-machines.index')
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseHas('production_machines', ['name' => 'Mesin Pertama', 'kode_mesin' => 'MSN-001']);
        $this->assertDatabaseHas('production_machines', ['name' => 'Mesin Kedua', 'kode_mesin' => 'MSN-002']);
    }

    public function test_posted_code_is_ignored_on_store(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.production-machines.store'), [
                'name' => 'Mesin Baru', 'status' => 'active', 'kode_mesin' => 'MSN-999',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('production_machines', ['name' => 'Mesin Baru', 'kode_mesin' => 'MSN-001']);
        $this->assertDatabaseMissing('production_machines', ['kode_mesin' => 'MSN-999']);
    }

    public function test_posted_code_is_ignored_on_update(): void
    {
        $machine = $this->machine();

        $this->actingAs($this->admin())
            ->put(route('admin.production-machines.update', $machine), [
                'name' => 'Nama Baru', 'status' => 'maintenance', 'kode_mesin' => 'MSN-999',
            ])
            ->assertSessionHasNoErrors();

        $machine->refresh();
        $this->assertSame('Nama Baru', $machine->name);
        $this->assertSame('maintenance', $machine->status);
        $this->assertSame('MSN-001', $machine->kode_mesin);
    }

    public function test_code_must_be_unique(): void
    {
        $this->machine();

        $this->expectException(QueryException::class);

        ProductionMachine::create(['name' => 'Ganda', 'status' => 'active', 'kode_mesin' => 'MSN-001']);
    }

    public function test_next_code_continues_after_the_highest_when_a_middle_machine_is_deleted(): void
    {
        $this->machine('A');
        $middle = $this->machine('B');
        $this->machine('C');

        $middle->delete();

        $this->assertSame('MSN-004', ProductionMachine::nextKode());
        $this->assertSame('MSN-004', $this->machine('D')->kode_mesin);
    }

    public function test_index_data_returns_code_sorted_by_it(): void
    {
        $first = $this->machine('A');
        $second = $this->machine('B');

        $response = $this->actingAs($this->admin())
            ->getJson(route('admin.datatables.production-machines'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.code');

        $this->assertSame(
            [$first->kode_mesin, $second->kode_mesin],
            array_column($response->json('data'), 'kode_mesin')
        );
    }

    public function test_index_shows_the_id_column_and_sorts_by_it(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.production-machines.index'))
            ->assertOk()
            ->assertSee('ID Mesin')
            ->assertSee("{ data: 'kode_mesin' }", false)
            ->assertSee('order: [[0,', false);
    }

    public function test_create_form_previews_the_next_code_without_submitting_it(): void
    {
        $this->machine();

        $this->actingAs($this->admin())
            ->get(route('admin.production-machines.create'))
            ->assertOk()
            ->assertSee('ID Mesin')
            ->assertSee('Dibuat otomatis')
            ->assertSee('value="MSN-002"', false)
            ->assertDontSee('name="kode_mesin"', false)
            ->assertDontSee('name="code"', false);
    }

    public function test_edit_form_shows_the_code_read_only_with_the_display_name(): void
    {
        $machine = $this->machine('Mesin Jahit');

        $this->actingAs($this->admin())
            ->get(route('admin.production-machines.edit', $machine))
            ->assertOk()
            ->assertSee('Ubah: MSN-001 — Mesin Jahit')
            ->assertSee('value="MSN-001"', false)
            ->assertDontSee('name="kode_mesin"', false)
            ->assertDontSee('name="code"', false);
    }
}
