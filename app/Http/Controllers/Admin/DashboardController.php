<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Production;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_products' => Product::where('is_active', true)->count(),
            'low_stock_materials' => Material::whereColumn('stock', '<=', 'min_stock')->count(),
            'productions_running' => Production::where('status', 'in_progress')->count(),
            'orders_pending' => Order::where('status', 'pending')->count(),
            'sales_this_month' => (float) Payment::where('status', 'verified')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
        ];

        $runningProductions = Production::with('product')
            ->where('status', 'in_progress')
            ->latest('start_date')
            ->take(5)
            ->get()
            ->map(function (Production $p) {
                $progress = $p->planned_qty > 0
                    ? min(100, (int) round(($p->actual_qty / $p->planned_qty) * 100))
                    : 0;

                $currentStage = $p->stages()->where('status', 'in_progress')->orderBy('id')->first()
                    ?? $p->stages()->where('status', 'pending')->orderBy('id')->first();

                return [
                    'code' => $p->code,
                    'product' => $p->product?->name ?? '—',
                    'stage' => ucfirst($currentStage?->stage ?? '—'),
                    'planned' => $p->planned_qty,
                    'actual' => $p->actual_qty,
                    'progress' => $progress,
                ];
            })
            ->all();

        $bestSellers = OrderItem::select('product_id', DB::raw('SUM(qty) as sold'))
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->groupBy('product_id')
            ->orderByDesc('sold')
            ->with('product:id,name')
            ->take(4)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->product?->name ?? '—',
                'sold' => (int) $row->sold,
            ])
            ->all();

        $lowStock = Material::whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->take(5)
            ->get()
            ->map(fn (Material $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'stock' => $m->stock,
                'min' => $m->min_stock,
                'unit' => $m->unit,
            ])
            ->all();

        $recentOrders = Order::with('user')
            ->latest()
            ->take(3)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'code' => $o->code,
                'customer' => $o->user?->name ?? '—',
                'total' => (float) $o->total,
                'status' => $o->status,
            ])
            ->all();

        return view('admin.dashboard.index', compact(
            'stats', 'runningProductions', 'bestSellers', 'lowStock', 'recentOrders'
        ));
    }
}
