<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Production;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStageTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    private function admin(): User
    {
        return $this->user ??= User::create([
            'name' => 'Op', 'email' => 'op@labasa.test', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    /**
     * A running batch parked on the mass QC & Packing stage, built the same
     * shape ProductionController@store creates for a batch with a sample phase.
     */
    private function batchOnMassQcPacking(): Production
    {
        $cat = Category::create(['name' => 'Gamis']);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Gamis A', 'sku' => 'GA-1',
            'price' => 100000, 'stock' => 0, 'is_active' => true,
        ]);
        $user = $this->admin();
        $prod = Production::create([
            'product_id' => $product->id, 'user_id' => $user->id, 'code' => 'PRD-DASH-1',
            'planned_qty' => 100, 'actual_qty' => 0, 'start_date' => now()->toDateString(), 'status' => 'in_progress',
        ]);

        $rows = [
            ['common', 'design', 'completed'],
            ['common', 'pola', 'completed'],
            ['sample', 'cutting', 'completed'],
            ['sample', 'sewing', 'completed'],
            ['sample', 'qc_packing', 'completed'],
            ['mass', 'cutting', 'completed'],
            ['mass', 'sewing', 'completed'],
            ['mass', 'qc_packing', 'in_progress'],
        ];

        foreach ($rows as $i => [$phase, $stage, $status]) {
            $prod->stages()->create([
                'phase' => $phase, 'sort_order' => $i + 1, 'stage' => $stage,
                'status' => $status, 'input_qty' => 0, 'output_qty' => 0,
            ]);
        }

        return $prod;
    }

    public function test_dashboard_shows_the_human_stage_label_not_the_raw_slug(): void
    {
        $this->batchOnMassQcPacking();

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('QC &amp; Packing', false);
        $response->assertDontSee('Qc_packing');
    }
}
