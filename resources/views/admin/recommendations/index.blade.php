@extends('layouts.admin')
@section('title', 'Rekomendasi Produksi')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Rekomendasi (Fuzzy)']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Rekomendasi Produksi (Fuzzy Mamdani)</h1>
@endsection

@section('content')
<div class="mb-6 max-w-3xl">
    <p class="text-sm text-ink-600 leading-relaxed">
        Sistem ini menggunakan metode <span class="font-semibold">Fuzzy Mamdani</span> untuk menghitung rekomendasi
        jumlah produksi berdasarkan permintaan dan persediaan saat ini. Inferensi dilakukan dengan 4 aturan
        IF–THEN dan defuzzifikasi menggunakan metode <em>weighted average</em>.
    </p>
</div>

<div class="flex justify-end mb-4">
    <a href="{{ route('admin.recommendations.create') }}" class="btn-primary">+ Hitung Rekomendasi</a>
</div>

<x-ui.card title="Rekomendasi Terbaru per Produk">
    <table class="table-clean">
        <thead><tr><th>Produk</th><th class="text-right">Rekomendasi Terakhir</th><th>Diperbarui</th><th class="text-right">Aksi</th></tr></thead>
        <tbody>
            @foreach ($products as $p)
                <tr>
                    <td class="font-medium">{{ $p->name }}</td>
                    <td class="text-right tabular-nums font-display text-lg">{{ number_format($p->last_recommendation) }} <span class="text-xs text-ink-500">unit</span></td>
                    <td class="text-ink-500">{{ $p->updated }}</td>
                    <td class="text-right">
                        <a href="{{ route('admin.recommendations.history', $p->id) }}" class="text-ink-600 hover:text-ink-900 text-sm">Riwayat</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-ui.card>
@endsection
