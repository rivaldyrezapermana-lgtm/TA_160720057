<?php

namespace Tests\Feature\Admin;

use App\Models\MachineCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineCategoryTest extends TestCase
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

    public function test_index_loads(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.machine-categories.index'))
            ->assertOk();
    }

    public function test_category_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.machine-categories.store'), [
                'name' => 'Mesin Jahit', 'code' => 'CAT-SEW', 'stage' => 'sewing',
            ])
            ->assertRedirectToRoute('admin.machine-categories.index');

        $this->assertDatabaseHas('machine_categories', ['code' => 'CAT-SEW', 'stage' => 'sewing']);
    }

    public function test_create_requires_name_and_code(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.machine-categories.store'), ['stage' => 'sewing'])
            ->assertSessionHasErrors(['name', 'code']);
    }

    public function test_category_can_be_deleted_when_unused(): void
    {
        $cat = MachineCategory::create(['name' => 'X', 'code' => 'CAT-X', 'stage' => null]);

        $this->actingAs($this->admin())
            ->delete(route('admin.machine-categories.destroy', $cat))
            ->assertRedirectToRoute('admin.machine-categories.index');

        $this->assertDatabaseMissing('machine_categories', ['id' => $cat->id]);
    }
}
