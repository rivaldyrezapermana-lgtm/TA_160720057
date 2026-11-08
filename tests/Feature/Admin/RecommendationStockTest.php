<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\SalesHistory;
use App\Models\User;
use App\Services\FuzzyMamdaniService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecommendationStockTest extends TestCase
{
    use RefreshDatabase;

    private const OUTSIDE_RANGE_NOTE = 'Stok saat ini berada di luar rentang data historis';

    /** Demand, stock_end, produced per month: same rows as DatabaseSeeder $hist. */
    private const SEED_HISTORY = [
        [1792, 1535, 4023], [9868, 3761, 8580], [6809, 2473, 5316], [2647, 980, 2410],
        [486, 743, 1774],   [5132, 2021, 6228], [8752, 3117, 8148], [6767, 2513, 6741],
        [8379, 2487, 6661], [1017, 769, 1335],  [6271, 2178, 1254], [6473, 2135, 7135],
    ];

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'admin.uji@labasa.test'], [
            'name' => 'Admin Uji', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function product(string $name, int $stock, array $history = []): Product
    {
        $product = Product::create([
            'category_id' => Category::firstOrCreate(['name' => 'Gamis'])->id,
            'name' => $name, 'sku' => 'SKU-'.$name, 'price' => 100000, 'stock' => $stock, 'is_active' => true,
        ]);

        foreach ($history as $i => [$demand, $stockEnd, $produced]) {
            SalesHistory::create([
                'product_id' => $product->id, 'year' => 2025, 'month' => $i + 1,
                'demand' => $demand, 'stock_end' => $stockEnd, 'produced' => $produced,
            ]);
        }

        return $product;
    }

    /** History in the service's array shape, for comparing against a direct call. */
    private function serviceHistory(array $rows): array
    {
        return array_map(fn ($r) => ['demand' => $r[0], 'stock_end' => $r[1], 'produced' => $r[2]], $rows);
    }

    private function calculate(Product $product, array $extra = []): array
    {
        return $this->actingAs($this->admin())
            ->postJson(route('admin.recommendations.calculate'), ['product_id' => $product->id, 'demand' => 2000] + $extra)
            ->assertOk()
            ->json();
    }

    public function test_calculation_needs_no_posted_stock_and_fuzzifies_product_stock(): void
    {
        $product = $this->product('A', 600, [[1000, 100, 900], [3000, 900, 2800]]);

        $result = $this->calculate($product);

        $this->assertEquals(600, $result['inputs']['stock']);
        // (600 - 100) / (900 - 100) on the ascending BANYAK slope.
        $this->assertEqualsWithDelta(0.625, $result['fuzzification']['stock_banyak'], 0.0001);
        $this->assertEqualsWithDelta(0.375, $result['fuzzification']['stock_sedikit'], 0.0001);
    }

    public function test_posted_stock_is_ignored(): void
    {
        $product = $this->product('A', 600, [[1000, 100, 900], [3000, 900, 2800]]);
        $expected = $this->calculate($product);

        foreach ([100, 999999, 'bukan-angka'] as $posted) {
            $this->assertSame($expected, $this->calculate($product, ['stock' => $posted]));
        }
    }

    public function test_seed_history_with_stock_2370_matches_the_service_directly(): void
    {
        $product = $this->product('A', 2370, self::SEED_HISTORY);

        $result = $this->actingAs($this->admin())
            ->postJson(route('admin.recommendations.calculate'), ['product_id' => $product->id, 'demand' => 5877])
            ->assertOk()
            ->json();

        $direct = app(FuzzyMamdaniService::class)->calculate(5877, 2370, $this->serviceHistory(self::SEED_HISTORY));

        $this->assertSame($direct['recommended_production'], $result['recommended_production']);
        $this->assertEquals($direct['fuzzification'], $result['fuzzification']);
    }

    public function test_form_shows_stock_read_only_without_an_editable_input(): void
    {
        $product = $this->product('A', 4321);

        $this->actingAs($this->admin())
            ->get(route('admin.recommendations.create', ['product_id' => $product->id]))
            ->assertOk()
            ->assertSee('Persediaan Saat Ini (unit)')
            ->assertSee('Diambil otomatis dari stok produk')
            ->assertSee('4,321')
            ->assertDontSee('name="stock"', false)
            ->assertDontSee('2,370')
            ->assertDontSee('2370');
    }

    public function test_query_product_id_selects_product_for_select_stock_and_history(): void
    {
        $this->product('A', 1111, [[7001, 100, 900]]);
        $second = $this->product('B', 2222, [[7002, 100, 900]]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.recommendations.create', ['product_id' => $second->id]))
            ->assertOk()
            ->assertSee('2,222')
            ->assertDontSee('1,111')
            ->assertSee('7,002')
            ->assertDontSee('7,001')
            ->getContent();

        $this->assertStringContainsString('<option value="'.$second->id.'" selected>', $html);
    }

    public function test_without_query_the_first_product_is_selected(): void
    {
        $first = $this->product('A', 1111, [[7001, 100, 900]]);
        $this->product('B', 2222, [[7002, 100, 900]]);

        $html = $this->actingAs($this->admin())
            ->get(route('admin.recommendations.create'))
            ->assertOk()
            ->assertSee('1,111')
            ->assertSee('7,001')
            ->assertDontSee('7,002')
            ->getContent();

        $this->assertStringContainsString('<option value="'.$first->id.'" selected>', $html);
    }

    public function test_unknown_product_id_falls_back_to_the_first_product(): void
    {
        $this->product('A', 1111, [[7001, 100, 900]]);

        $this->actingAs($this->admin())
            ->get(route('admin.recommendations.create', ['product_id' => 99999]))
            ->assertOk()
            ->assertSee('1,111')
            ->assertSee('7,001');
    }

    public function test_after_calculate_old_input_wins_over_the_query_for_select_stock_and_history(): void
    {
        $first = $this->product('A', 1111, [[7001, 100, 900], [7003, 300, 950]]);
        $second = $this->product('B', 2222, [[7002, 100, 900], [7004, 300, 950]]);

        $html = $this->actingAs($this->admin())
            ->followingRedirects()
            ->from(route('admin.recommendations.create', ['product_id' => $first->id]))
            ->post(route('admin.recommendations.calculate'), ['product_id' => $second->id, 'demand' => 7003])
            ->assertOk()
            ->assertSee('Tahap 1')
            ->assertSee('2,222')
            ->assertSee('7,002')
            ->assertDontSee('7,001')
            ->getContent();

        $this->assertStringContainsString('<option value="'.$second->id.'" selected>', $html);
    }

    public function test_changing_product_reloads_the_page_with_the_query(): void
    {
        $this->product('A', 1111);

        $this->actingAs($this->admin())
            ->get(route('admin.recommendations.create'))
            ->assertSee('#product_id', false)
            ->assertSee('?product_id=', false);
    }

    public function test_index_uses_current_product_stock_not_latest_stock_end(): void
    {
        // The latest month has demand NAIK 0.8 and stock_end mid-range, so the
        // stock input changes the outcome (alpha_bertambah 0.5 versus 0.8).
        $rows = [[1000, 100, 1000], [3500, 900, 5000], [3000, 500, 3000]];
        $product = $this->product('A', 5000, $rows);
        $history = $this->serviceHistory($rows);
        $service = app(FuzzyMamdaniService::class);

        $fromProduct = $service->calculate(3000, 5000, $history)['recommended_production'];
        $fromHistory = $service->calculate(3000, 500, $history)['recommended_production'];
        $this->assertNotSame($fromProduct, $fromHistory);

        $products = $this->actingAs($this->admin())
            ->get(route('admin.recommendations.index'))
            ->assertOk()
            ->viewData('products');

        $this->assertSame($fromProduct, $products->firstWhere('id', $product->id)->last_recommendation);
    }

    public function test_history_page_still_replays_historical_stock_end(): void
    {
        $rows = [[1000, 100, 1000], [3500, 900, 5000], [3000, 500, 3000]];
        $product = $this->product('A', 5000, $rows);

        $replay = $this->actingAs($this->admin())
            ->get(route('admin.recommendations.history', $product))
            ->assertOk()
            ->viewData('history');

        $expected = app(FuzzyMamdaniService::class)->calculate(3000, 500, $this->serviceHistory($rows));
        $this->assertSame($expected['recommended_production'], $replay->last()['recommendation']);
    }

    #[DataProvider('outsideRangeStocks')]
    public function test_note_appears_when_stock_is_outside_history_range(int $stock, float $sedikit, float $banyak, string $set): void
    {
        $product = $this->product('A', $stock, [[1000, 100, 900], [3000, 900, 2800]]);

        $this->assertEqualsWithDelta($sedikit, $this->calculate($product)['fuzzification']['stock_sedikit'], 0.0001);
        $this->assertEqualsWithDelta($banyak, $this->calculate($product)['fuzzification']['stock_banyak'], 0.0001);

        $this->actingAs($this->admin())
            ->followingRedirects()
            ->from(route('admin.recommendations.create'))
            ->post(route('admin.recommendations.calculate'), ['product_id' => $product->id, 'demand' => 2000])
            ->assertSee(self::OUTSIDE_RANGE_NOTE)
            ->assertSee('bernilai penuh pada himpunan '.$set);
    }

    public static function outsideRangeStocks(): array
    {
        return [
            'below minimum' => [50, 1.0, 0.0, 'SEDIKIT'],
            'above maximum' => [1500, 0.0, 1.0, 'BANYAK'],
        ];
    }

    #[DataProvider('noNoteCases')]
    public function test_note_is_absent_otherwise(int $stock, array $history): void
    {
        $product = $this->product('A', $stock, $history);

        $this->actingAs($this->admin())
            ->followingRedirects()
            ->from(route('admin.recommendations.create'))
            ->post(route('admin.recommendations.calculate'), ['product_id' => $product->id, 'demand' => 2000])
            ->assertSee('Tahap 1')
            ->assertDontSee(self::OUTSIDE_RANGE_NOTE);
    }

    public static function noNoteCases(): array
    {
        $history = [[1000, 100, 900], [3000, 900, 2800]];

        return [
            'inside range' => [600, $history],
            'on the lower bound' => [100, $history],
            'on the upper bound' => [900, $history],
            // A single month gives no range: the service scores both sets 0, not "penuh".
            'no range in history' => [50, [[1000, 100, 900]]],
        ];
    }
}
