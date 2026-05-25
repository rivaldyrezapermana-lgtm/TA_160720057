<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use App\Models\Production;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function production(Request $request)
    {
        $rows = $this->productionRows($request)->all();

        return view('admin.reports.production', compact('rows'));
    }

    public function sales(Request $request)
    {
        $rows = $this->salesRows($request)->all();

        return view('admin.reports.sales', compact('rows'));
    }

    public function inventory(Request $request)
    {
        $rows = $this->inventoryRows()->all();

        return view('admin.reports.inventory', compact('rows'));
    }

    /** Stream a CSV download of one of the three reports. */
    public function export(Request $request, string $type): StreamedResponse
    {
        [$filename, $headers, $rows] = match ($type) {
            'production' => [
                'laporan-produksi-'.now()->format('Y-m-d').'.csv',
                ['Kode', 'Produk', 'Periode', 'Plan', 'Aktual', 'Pencapaian'],
                $this->productionRows($request)
                    ->map(fn ($r) => [$r['code'], $r['product'], $r['period'], $r['planned'], $r['actual'], $r['completion']]),
            ],
            'sales' => [
                'laporan-penjualan-'.now()->format('Y-m-d').'.csv',
                ['Bulan', 'Pesanan', 'Pendapatan', 'Rata-Rata'],
                $this->salesRows($request)
                    ->map(fn ($r) => [$r['month'], $r['orders'], $r['revenue'], $r['avg']]),
            ],
            'inventory' => [
                'laporan-inventori-'.now()->format('Y-m-d').'.csv',
                ['Tipe', 'Nama', 'Stok', 'Min', 'Unit', 'Status'],
                $this->inventoryRows()
                    ->map(fn ($r) => [$r['type'], $r['name'], $r['stock'], $r['min'], $r['unit'], $r['status']]),
            ],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens UTF-8 correctly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function productionRows(Request $request)
    {
        $query = Production::with('product')->latest('start_date');

        if ($from = $request->query('from')) {
            $query->whereDate('start_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('start_date', '<=', $to);
        }

        return $query->get()->map(function (Production $p) {
            $completion = $p->planned_qty > 0
                ? round(($p->actual_qty / $p->planned_qty) * 100).'%'
                : '0%';

            return [
                'code' => $p->code,
                'product' => $p->product?->name ?? '—',
                'period' => $p->start_date?->translatedFormat('M Y') ?? '—',
                'planned' => $p->planned_qty,
                'actual' => $p->actual_qty,
                'completion' => $completion,
            ];
        });
    }

    private function salesRows(Request $request)
    {
        $start = $request->query('month')
            ? Carbon::createFromFormat('Y-m', $request->query('month'))->startOfMonth()
            : now()->subMonths(11)->startOfMonth();
        $end = $request->query('month')
            ? Carbon::createFromFormat('Y-m', $request->query('month'))->endOfMonth()
            : now()->endOfMonth();

        $rows = collect();
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($end)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $orders = Order::whereIn('status', ['paid', 'processing', 'shipped', 'completed'])
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->get();

            $count = $orders->count();
            $revenue = (float) $orders->sum('total');
            $avg = $count > 0 ? $revenue / $count : 0;

            $rows->push([
                'month' => $cursor->translatedFormat('M Y'),
                'orders' => $count,
                'revenue' => 'Rp '.number_format($revenue, 0, ',', '.'),
                'avg' => 'Rp '.number_format($avg, 0, ',', '.'),
            ]);

            $cursor->addMonth();
        }

        return $rows->reverse()->values();
    }

    private function inventoryRows()
    {
        $materials = Material::orderBy('name')->get()->map(fn (Material $m) => [
            'type' => 'Bahan Baku',
            'name' => $m->name,
            'stock' => $m->stock,
            'min' => $m->min_stock,
            'unit' => $m->unit,
            'status' => $m->isLowStock() ? 'low' : 'ok',
        ]);

        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'type' => 'Produk',
                'name' => $p->name,
                'stock' => $p->stock,
                'min' => 20,
                'unit' => 'pcs',
                'status' => $p->stock < 20 ? 'low' : 'ok',
            ]);

        return $materials->concat($products);
    }
}
