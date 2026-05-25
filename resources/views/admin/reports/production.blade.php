@extends('layouts.admin')
@section('title', 'Laporan Produksi')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Laporan'], ['label' => 'Produksi']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Laporan Produksi</h1>
@endsection

@section('content')
<div class="flex justify-between items-end mb-4">
    <form method="GET" class="flex gap-3 items-end">
        <div><label class="label">Dari</label><input type="date" name="from" class="input"></div>
        <div><label class="label">Sampai</label><input type="date" name="to" class="input"></div>
        <button class="btn-secondary">Filter</button>
    </form>
    <div class="flex gap-2">
        <a href="{{ route('admin.reports.export', 'production') }}" class="btn-secondary">Export CSV</a>
    </div>
</div>

<x-ui.card>
    <table class="table-clean">
        <thead><tr><th>Kode</th><th>Produk</th><th>Periode</th><th class="text-right">Plan</th><th class="text-right">Aktual</th><th class="text-right">Pencapaian</th></tr></thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td class="font-medium">{{ $r['code'] }}</td>
                    <td>{{ $r['product'] }}</td>
                    <td class="text-ink-500">{{ $r['period'] }}</td>
                    <td class="text-right tabular-nums">{{ $r['planned'] }}</td>
                    <td class="text-right tabular-nums">{{ $r['actual'] }}</td>
                    <td class="text-right tabular-nums font-medium">{{ $r['completion'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-ui.card>
@endsection
