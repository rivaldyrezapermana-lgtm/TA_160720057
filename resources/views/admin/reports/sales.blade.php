@extends('layouts.admin')
@section('title', 'Laporan Penjualan')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Laporan'], ['label' => 'Penjualan']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Laporan Penjualan</h1>
@endsection

@section('content')
<div class="flex justify-between items-end mb-4">
    <form method="GET" class="flex gap-3 items-end">
        <div><label class="label">Bulan</label><input type="month" name="month" class="input"></div>
        <button class="btn-secondary">Filter</button>
    </form>
    <div class="flex gap-2">
        <a href="{{ route('admin.reports.export', 'sales') }}" class="btn-secondary">Export CSV</a>
    </div>
</div>

<x-ui.card>
    <table class="table-clean">
        <thead><tr><th>Bulan</th><th class="text-right">Pesanan</th><th class="text-right">Pendapatan</th><th class="text-right">Rata-Rata</th></tr></thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td class="font-medium">{{ $r['month'] }}</td>
                    <td class="text-right tabular-nums">{{ $r['orders'] }}</td>
                    <td class="text-right tabular-nums font-medium">{{ $r['revenue'] }}</td>
                    <td class="text-right tabular-nums text-ink-500">{{ $r['avg'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-ui.card>
@endsection
