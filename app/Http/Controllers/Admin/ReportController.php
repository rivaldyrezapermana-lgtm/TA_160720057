<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function production(Request $request)
    {
        $rows = collect(range(1, 8))->map(fn($i) => [
            'code'=>'PRD-2026-'.str_pad($i+20,4,'0',STR_PAD_LEFT),
            'product'=>['Gamis Anaya Navy','Koko Modern Sage','Tunik Basic Cream'][$i % 3],
            'period'=>now()->subMonths($i)->format('M Y'),
            'planned'=>rand(100,300),'actual'=>rand(80,300),
            'completion'=>rand(80, 100).'%',
        ]);
        return view('admin.reports.production', compact('rows'));
    }

    public function sales(Request $request)
    {
        $rows = collect(range(1, 12))->map(fn($i) => [
            'month'=>now()->subMonths($i)->format('M Y'),
            'orders'=>rand(20,80),
            'revenue'=>'Rp '.number_format(rand(15000,40000)*1000, 0, ',', '.'),
            'avg'=>'Rp '.number_format(rand(200,500)*1000, 0, ',', '.'),
        ]);
        return view('admin.reports.sales', compact('rows'));
    }

    public function inventory(Request $request)
    {
        $rows = collect([
            ['type'=>'Bahan Baku','name'=>'Kain Katun Premium','stock'=>12,'min'=>30,'unit'=>'meter','status'=>'low'],
            ['type'=>'Bahan Baku','name'=>'Benang Hitam','stock'=>4,'min'=>10,'unit'=>'roll','status'=>'low'],
            ['type'=>'Produk','name'=>'Gamis Anaya Navy','stock'=>48,'min'=>20,'unit'=>'pcs','status'=>'ok'],
            ['type'=>'Produk','name'=>'Koko Modern Sage','stock'=>18,'min'=>20,'unit'=>'pcs','status'=>'low'],
        ]);
        return view('admin.reports.inventory', compact('rows'));
    }

    public function export($type)
    {
        return back()->with('success', "Export {$type} sedang disiapkan (TODO).");
    }
}
