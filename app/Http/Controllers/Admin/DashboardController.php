<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        // TODO: replace with real DB queries
        $stats = [
            'total_products'      => 124,
            'low_stock_materials' => 4,
            'productions_running' => 6,
            'orders_pending'      => 11,
            'sales_this_month'    => 18_450_000,
        ];

        $runningProductions = [
            ['code' => 'PRD-2026-0042', 'product' => 'Gamis Anaya Navy', 'stage' => 'Sewing', 'planned' => 200, 'actual' => 124, 'progress' => 62],
            ['code' => 'PRD-2026-0041', 'product' => 'Koko Modern Sage',  'stage' => 'Cutting', 'planned' => 150, 'actual' => 0,   'progress' => 12],
            ['code' => 'PRD-2026-0040', 'product' => 'Tunik Basic Cream', 'stage' => 'QC',      'planned' => 100, 'actual' => 98,  'progress' => 95],
        ];

        $bestSellers = [
            ['name' => 'Gamis Anaya Navy', 'sold' => 86],
            ['name' => 'Koko Modern Sage', 'sold' => 64],
            ['name' => 'Tunik Basic Cream', 'sold' => 41],
            ['name' => 'Hijab Pashmina Plain', 'sold' => 38],
        ];

        $lowStock = [
            ['name' => 'Kain Katun Premium', 'stock' => 12, 'min' => 30, 'unit' => 'meter'],
            ['name' => 'Benang Hitam',       'stock' => 4,  'min' => 10, 'unit' => 'roll'],
            ['name' => 'Resleting 30cm',     'stock' => 22, 'min' => 50, 'unit' => 'pcs'],
        ];

        $recentOrders = [
            ['code' => 'ORD-2026-1209', 'customer' => 'Siti Nurhaliza', 'total' => 425_000, 'status' => 'pending'],
            ['code' => 'ORD-2026-1208', 'customer' => 'Ahmad Fauzi',     'total' => 280_000, 'status' => 'paid'],
            ['code' => 'ORD-2026-1207', 'customer' => 'Diana Putri',     'total' => 670_000, 'status' => 'shipped'],
        ];

        return view('admin.dashboard.index', compact(
            'stats', 'runningProductions', 'bestSellers', 'lowStock', 'recentOrders'
        ));
    }
}
