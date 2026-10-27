<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\SalesHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationSizeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Uji', 'email' => 'admin.uji@labasa.test',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    /** A product with S/M sizes and two months of sales history. */
    private function product(): Product
    {
        $product = Product::create([
            'category_id' => Category::create(['name' => 'Gamis'])->id,
            'name' => 'Gamis Uji', 'sku' => 'SKU-UJI', 'price' => 100000, 'stock' => 10, 'is_active' => true,
        ]);
        foreach (['S', 'M'] as $size) {
            ProductSize::create(['product_id' => $product->id, 'size' => $size, 'stock' => 5]);
        }
        SalesHistory::create(['product_id' => $product->id, 'year' => 2025, 'month' => 1, 'demand' => 1000, 'stock_end' => 500, 'produced' => 900]);
        SalesHistory::create(['product_id' => $product->id, 'year' => 2025, 'month' => 2, 'demand' => 3000, 'stock_end' => 100, 'produced' => 2800]);

        return $product;
    }

    private function sell(Product $product, User $buyer, string $size, int $qty, string $status = 'completed'): void
    {
        $order = Order::create([
            'user_id' => $buyer->id, 'code' => 'ORD-'.uniqid(),
            'total' => 0, 'status' => $status, 'shipping_address' => 'Jl. Uji',
        ]);
        $order->items()->create(['product_id' => $product->id, 'size' => $size, 'qty' => $qty, 'price' => 0, 'subtotal' => 0]);
    }

    public function test_calculation_returns_breakdown_weighted_by_sold_sizes(): void
    {
        $admin = $this->admin();
        $product = $this->product();
        $this->sell($product, $admin, 'S', 1);
        $this->sell($product, $admin, 'M', 3);
        $this->sell($product, $admin, 'S', 50, 'cancelled');

        $result = $this->actingAs($admin)
            ->postJson(route('admin.recommendations.calculate'), ['product_id' => $product->id, 'demand' => 2000, 'stock' => 300])
            ->assertOk()
            ->json();

        $breakdown = collect($result['size_breakdown'])->keyBy('size');
        $this->assertSame($result['recommended_production'], $breakdown->sum('qty'));
        $this->assertEqualsWithDelta(0.25, $breakdown['S']['share'], 0.0001);
        $this->assertEqualsWithDelta(0.75, $breakdown['M']['share'], 0.0001);
    }

    public function test_index_shows_per_size_column(): void
    {
        $this->product();

        $this->actingAs($this->admin())
            ->get(route('admin.recommendations.index'))
            ->assertOk()
            ->assertSee('Per Ukuran')
            ->assertSee('S:');
    }
}
