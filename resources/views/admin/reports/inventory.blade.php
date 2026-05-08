@extends('layouts.admin')
@section('title', 'Laporan Inventori')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Laporan'], ['label' => 'Inventori']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Laporan Inventori</h1>
@endsection

@section('content')
<div class="flex justify-end mb-4">
    <a href="{{ route('admin.reports.export', 'inventory') }}?format=pdf" class="btn-secondary">Export PDF</a>
</div>

<x-ui.card>
    <table class="table-clean">
        <thead><tr><th>Tipe</th><th>Nama</th><th class="text-right">Stok</th><th class="text-right">Min</th><th>Unit</th><th>Status</th></tr></thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td><x-ui.badge>{{ $r['type'] }}</x-ui.badge></td>
                    <td class="font-medium">{{ $r['name'] }}</td>
                    <td class="text-right tabular-nums {{ $r['status'] === 'low' ? 'text-red-700 font-semibold' : '' }}">{{ $r['stock'] }}</td>
                    <td class="text-right tabular-nums text-ink-500">{{ $r['min'] }}</td>
                    <td class="text-ink-500">{{ $r['unit'] }}</td>
                    <td><x-ui.status-badge :status="$r['status']" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-ui.card>
@endsection
