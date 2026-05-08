@extends('layouts.admin')
@section('title', 'Riwayat Rekomendasi')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Rekomendasi', 'url' => route('admin.recommendations.index')], ['label' => 'Riwayat']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Riwayat: {{ $product->name }}</h1>
@endsection

@section('content')
<x-ui.card>
    <table class="table-clean">
        <thead>
            <tr>
                <th>Periode</th>
                <th class="text-right">Permintaan</th>
                <th class="text-right">Sisa Stok</th>
                <th class="text-right">Diproduksi</th>
                <th class="text-right">Rekomendasi Sistem</th>
                <th class="text-right">Selisih</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($history as $h)
                @php $diff = $h['produced'] - $h['recommendation']; @endphp
                <tr>
                    <td class="font-medium">{{ $h['month'] }}</td>
                    <td class="text-right tabular-nums">{{ number_format($h['demand']) }}</td>
                    <td class="text-right tabular-nums">{{ number_format($h['stock_end']) }}</td>
                    <td class="text-right tabular-nums">{{ number_format($h['produced']) }}</td>
                    <td class="text-right tabular-nums font-medium">{{ number_format($h['recommendation']) }}</td>
                    <td class="text-right tabular-nums {{ $diff > 0 ? 'text-amber-600' : ($diff < 0 ? 'text-blue-600' : 'text-ink-500') }}">
                        {{ $diff > 0 ? '+' : '' }}{{ number_format($diff) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-ui.card>
@endsection
