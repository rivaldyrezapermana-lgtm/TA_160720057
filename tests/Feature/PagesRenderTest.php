<?php

namespace Tests\Feature;

use App\Models\Production;
use App\Models\Size;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Every GET page must render against the seeded demo data. */
class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    private function createBatch(): Production
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))->post(route('admin.productions.store'), [
            'product_id' => 1,
            'start_date' => '2026-11-08',
            'sizes' => ['M' => 3, 'XL' => 2],
            'sample_size' => 'M',
        ])->assertRedirect();

        return Production::firstOrFail();
    }

    public function test_every_admin_get_page_renders(): void
    {
        $batch = $this->createBatch();
        $ids = [
            'production' => $batch->id, 'product' => 1, 'production_machine' => 1,
            'machine_category' => 1, 'size' => Size::firstOrFail()->id, 'category' => 1,
            'material' => 1, 'supplier' => 1, 'user' => 1, 'type' => 'production',
        ];
        $visited = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || ! str_starts_with($route->uri(), 'admin')) {
                continue;
            }

            $params = array_intersect_key($ids, array_flip($route->parameterNames()));
            if (count($params) !== count($route->parameterNames())) {
                continue; // Needs an order, purchase or chat thread, which the seeder doesn't create.
            }

            $status = $this->actingAs($this->user(User::ROLE_ADMIN))
                ->get(route($route->getName(), $params))
                ->getStatusCode();

            $this->assertLessThan(400, $status, $route->uri().' returned '.$status);
            $visited++;
        }

        $this->assertGreaterThan(40, $visited);
    }

    public function test_storefront_and_auth_pages_render(): void
    {
        foreach (['/', '/shop', '/shop/product/1', '/login', '/register'] as $uri) {
            $this->get($uri)->assertOk();
        }

        $this->actingAs($this->user(User::ROLE_PEMBELI));
        foreach (['/cart', '/checkout', '/my-orders', '/chat', '/profile'] as $uri) {
            $this->assertLessThan(400, $this->get($uri)->getStatusCode(), $uri);
        }
    }

    public function test_seeded_batch_page_shows_the_revised_features(): void
    {
        $batch = $this->createBatch();

        $this->assertSame(['M' => 3, 'XL' => 2], $batch->sizes->pluck('planned_qty', 'size')->all());

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post(route('admin.productions.pause', $batch))
            ->assertRedirect();

        $this->get(route('admin.productions.show', $batch))
            ->assertOk()
            ->assertSee('Log Produksi')
            ->assertSee('Pause');

        $this->get(route('admin.productions.edit', $batch))
            ->assertOk()
            ->assertSee('MSN-001 — Mesin Jahit Juki A');
    }

    public function test_seeded_recommendation_reproduces_the_proposal_example(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->get(route('admin.recommendations.create', ['product_id' => 1]))
            ->assertOk()
            ->assertSee('2,370')
            ->assertDontSee('name="stock"', false);

        $this->postJson(route('admin.recommendations.calculate'), ['product_id' => 1, 'demand' => 5877])
            ->assertOk()
            ->assertJsonPath('inputs.stock', 2370);
    }
}
