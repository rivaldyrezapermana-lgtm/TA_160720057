<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesHistory;
use App\Services\FuzzyMamdaniService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(private FuzzyMamdaniService $fuzzy) {}

    public function index()
    {
        $products = Product::with(['sizes', 'salesHistories' => fn ($q) => $q->orderByDesc('year')->orderByDesc('month')])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                $history = $this->loadHistory($product);
                $latest = $product->salesHistories->first();

                if (! $latest || empty($history)) {
                    $product->last_recommendation = 0;
                    $product->size_breakdown = [];
                    $product->updated = '—';

                    return $product;
                }

                $result = $this->fuzzy->calculate(
                    (float) $latest->demand,
                    (float) $product->stock,
                    $history,
                );

                $product->last_recommendation = (int) ($result['recommended_production'] ?? 0);
                $product->size_breakdown = $this->fuzzy->distributeBySize(
                    $product->last_recommendation,
                    $this->sizeWeights($product),
                );
                $product->updated = $latest->updated_at?->translatedFormat('d M Y') ?? '—';

                return $product;
            });

        return view('admin.recommendations.index', compact('products'));
    }

    public function create(Request $request)
    {
        $products = Product::orderBy('name')->get(['id', 'name', 'stock']);

        // Old input wins after the calculate redirect, so the select, the stock
        // and the history table all describe the product that was calculated.
        $requestedId = (int) $request->old('product_id', $request->query('product_id'));
        $selectedProduct = $products->firstWhere('id', $requestedId) ?? $products->first();
        $selectedProductId = $selectedProduct?->id;

        $history = collect();
        if ($selectedProductId) {
            $history = SalesHistory::where('product_id', $selectedProductId)
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(fn (SalesHistory $h) => [
                    'month' => $this->monthLabel($h->year, $h->month),
                    'demand' => $h->demand,
                    'stock_end' => $h->stock_end,
                    'produced' => $h->produced,
                ])
                ->all();
        }

        return view('admin.recommendations.create', compact('products', 'selectedProduct', 'selectedProductId', 'history'));
    }

    public function calculate(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'demand' => ['required', 'numeric', 'min:0'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $history = $this->loadHistory($product);

        if (empty($history)) {
            return back()->with('error', 'Belum ada data riwayat penjualan untuk produk ini.')->withInput();
        }

        $result = $this->fuzzy->calculate(
            (float) $data['demand'],
            (float) $product->stock,
            $history,
        );

        $result['size_breakdown'] = $this->fuzzy->distributeBySize(
            (int) $result['recommended_production'],
            $this->sizeWeights($product),
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return back()->with('fuzzyResult', $result)->withInput();
    }

    public function history(Product $product)
    {
        $rows = SalesHistory::where('product_id', $product->id)
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $history = $rows->map(fn (SalesHistory $h) => [
            'demand' => $h->demand,
            'stock_end' => $h->stock_end,
            'produced' => $h->produced,
        ])->all();

        $history = $rows->map(function (SalesHistory $h) use ($history) {
            $result = empty($history) ? null : $this->fuzzy->calculate(
                (float) $h->demand,
                (float) $h->stock_end,
                $history,
            );

            return [
                'month' => $this->monthLabel($h->year, $h->month),
                'demand' => $h->demand,
                'stock_end' => $h->stock_end,
                'produced' => $h->produced,
                'recommendation' => (int) ($result['recommended_production'] ?? 0),
            ];
        });

        return view('admin.recommendations.history', compact('product', 'history'));
    }

    /** Build the history array the fuzzy service expects from a product's SalesHistory rows. */
    private function loadHistory(Product $product): array
    {
        $rows = $product->relationLoaded('salesHistories')
            ? $product->salesHistories
            : SalesHistory::where('product_id', $product->id)->orderBy('year')->orderBy('month')->get();

        return $rows->map(fn (SalesHistory $h) => [
            'demand' => $h->demand,
            'stock_end' => $h->stock_end,
            'produced' => $h->produced,
        ])->all();
    }

    /**
     * Units sold per size (non-cancelled orders), keyed by the product's sizes
     * in their stored order. Sizes with no sales weigh 0.
     *
     * @return array<string, float>
     */
    private function sizeWeights(Product $product): array
    {
        $sizes = $product->relationLoaded('sizes') ? $product->sizes : $product->sizes()->get();
        if ($sizes->isEmpty()) {
            return [];
        }

        $sold = OrderItem::where('product_id', $product->id)
            ->whereNotNull('size')
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->selectRaw('size, SUM(qty) as total')
            ->groupBy('size')
            ->pluck('total', 'size');

        return $sizes->sortBy('id')
            ->mapWithKeys(fn ($s) => [$s->size => (float) ($sold[$s->size] ?? 0)])
            ->all();
    }

    private function monthLabel(int $year, int $month): string
    {
        return Carbon::create($year, $month, 1)->translatedFormat('M Y');
    }
}
