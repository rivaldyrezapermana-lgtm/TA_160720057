@extends('layouts.admin')
@section('title', 'Hitung Rekomendasi')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Rekomendasi', 'url' => route('admin.recommendations.index')], ['label' => 'Hitung']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Hitung Rekomendasi Produksi</h1>
@endsection

@php
    $result = session('fuzzyResult');
@endphp

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

    {{-- Input Form --}}
    <div class="lg:col-span-2">
        <x-ui.card title="Input Parameter" subtitle="Masukkan permintaan dan persediaan saat ini">
            <form action="{{ route('admin.recommendations.calculate') }}" method="POST" class="space-y-4">
                @csrf
                <x-ui.select name="product_id" label="Produk" required
                    :selected="old('product_id', 1)"
                    :options="$products->pluck('name','id')->toArray()" />

                <x-ui.input name="demand" type="number" label="Permintaan (unit)" required
                    :value="old('demand', 5877)"
                    help="Permintaan untuk periode yang akan dihitung" />

                <x-ui.input name="stock" type="number" label="Persediaan Saat Ini (unit)" required
                    :value="old('stock', 2370)"
                    help="Stok produk yang masih tersedia" />

                <button type="submit" class="btn-primary w-full justify-center">
                    Hitung Rekomendasi
                </button>
            </form>
        </x-ui.card>

        <x-ui.card title="Data Historis" class="mt-4">
            <p class="text-xs text-ink-500 mb-3">Range yang digunakan untuk fuzzifikasi diambil dari data 12 bulan terakhir:</p>
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-ink-100">
                        <th class="text-left pb-2 font-semibold text-ink-500 uppercase tracking-wider">Bulan</th>
                        <th class="text-right pb-2 font-semibold text-ink-500 uppercase tracking-wider">Permintaan</th>
                        <th class="text-right pb-2 font-semibold text-ink-500 uppercase tracking-wider">Sisa Stok</th>
                        <th class="text-right pb-2 font-semibold text-ink-500 uppercase tracking-wider">Diproduksi</th>
                    </tr>
                </thead>
                <tbody class="text-ink-700">
                    @foreach ($history as $h)
                        <tr class="border-b border-ink-50 last:border-0">
                            <td class="py-1.5">{{ $h['month'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ number_format($h['demand']) }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ number_format($h['stock_end']) }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ number_format($h['produced']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    </div>

    {{-- Result --}}
    <div class="lg:col-span-3">
        @if ($result)
            <div class="bg-ink-900 text-white rounded-2xl p-8 mb-4">
                <p class="text-xs uppercase tracking-[0.18em] text-ink-300 font-semibold">Rekomendasi Produksi</p>
                <p class="font-display text-6xl font-semibold mt-2 tabular-nums">
                    {{ number_format($result['recommended_production']) }}
                    <span class="text-2xl font-normal text-ink-400">unit</span>
                </p>
                <p class="text-sm text-ink-300 mt-2">
                    Berdasarkan permintaan {{ number_format($result['inputs']['demand']) }} dan stok {{ number_format($result['inputs']['stock']) }} unit.
                </p>
            </div>

            <x-ui.card title="Tahap 1 — Fuzzifikasi" subtitle="Derajat keanggotaan untuk variabel input">
                <div class="space-y-4">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-ink-500 font-semibold mb-2">Variabel: Permintaan</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="border border-ink-100 rounded-lg p-3">
                                <p class="text-xs text-ink-500">µ Permintaan TURUN</p>
                                <p class="font-display text-2xl font-semibold tabular-nums mt-1">{{ $result['fuzzification']['demand_turun'] }}</p>
                                <div class="h-1 bg-ink-100 rounded-full mt-2"><div class="h-full bg-ink-900 rounded-full" style="width: {{ $result['fuzzification']['demand_turun'] * 100 }}%"></div></div>
                            </div>
                            <div class="border border-ink-100 rounded-lg p-3">
                                <p class="text-xs text-ink-500">µ Permintaan NAIK</p>
                                <p class="font-display text-2xl font-semibold tabular-nums mt-1">{{ $result['fuzzification']['demand_naik'] }}</p>
                                <div class="h-1 bg-ink-100 rounded-full mt-2"><div class="h-full bg-ink-900 rounded-full" style="width: {{ $result['fuzzification']['demand_naik'] * 100 }}%"></div></div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-ink-500 font-semibold mb-2">Variabel: Persediaan</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="border border-ink-100 rounded-lg p-3">
                                <p class="text-xs text-ink-500">µ Persediaan SEDIKIT</p>
                                <p class="font-display text-2xl font-semibold tabular-nums mt-1">{{ $result['fuzzification']['stock_sedikit'] }}</p>
                                <div class="h-1 bg-ink-100 rounded-full mt-2"><div class="h-full bg-ink-900 rounded-full" style="width: {{ $result['fuzzification']['stock_sedikit'] * 100 }}%"></div></div>
                            </div>
                            <div class="border border-ink-100 rounded-lg p-3">
                                <p class="text-xs text-ink-500">µ Persediaan BANYAK</p>
                                <p class="font-display text-2xl font-semibold tabular-nums mt-1">{{ $result['fuzzification']['stock_banyak'] }}</p>
                                <div class="h-1 bg-ink-100 rounded-full mt-2"><div class="h-full bg-ink-900 rounded-full" style="width: {{ $result['fuzzification']['stock_banyak'] * 100 }}%"></div></div>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-ink-500 pt-2 border-t border-ink-100">
                        Range: Permintaan [{{ number_format($result['ranges']['demand_min']) }} – {{ number_format($result['ranges']['demand_max']) }}],
                        Persediaan [{{ number_format($result['ranges']['stock_min']) }} – {{ number_format($result['ranges']['stock_max']) }}]
                    </p>
                </div>
            </x-ui.card>

            <x-ui.card title="Tahap 2 — Inferensi (4 Aturan)" subtitle="α-predikat tiap aturan menggunakan operator MIN" class="mt-4">
                <table class="table-clean">
                    <thead><tr><th>Aturan</th><th>Kondisi (IF)</th><th>Konsekuen (THEN)</th><th class="text-right">α</th></tr></thead>
                    <tbody>
                        <tr><td class="font-medium">R1</td><td>Permintaan TURUN ∧ Persediaan BANYAK</td><td class="text-ink-500">Produksi BERKURANG</td><td class="text-right tabular-nums font-medium">{{ $result['rules']['R1_turun_banyak_berkurang'] }}</td></tr>
                        <tr><td class="font-medium">R2</td><td>Permintaan TURUN ∧ Persediaan SEDIKIT</td><td class="text-ink-500">Produksi BERKURANG</td><td class="text-right tabular-nums font-medium">{{ $result['rules']['R2_turun_sedikit_berkurang'] }}</td></tr>
                        <tr><td class="font-medium">R3</td><td>Permintaan NAIK ∧ Persediaan BANYAK</td><td class="text-ink-500">Produksi BERTAMBAH</td><td class="text-right tabular-nums font-medium">{{ $result['rules']['R3_naik_banyak_bertambah'] }}</td></tr>
                        <tr><td class="font-medium">R4</td><td>Permintaan NAIK ∧ Persediaan SEDIKIT</td><td class="text-ink-500">Produksi BERTAMBAH</td><td class="text-right tabular-nums font-medium">{{ $result['rules']['R4_naik_sedikit_bertambah'] }}</td></tr>
                    </tbody>
                </table>
                <div class="mt-4 pt-4 border-t border-ink-100 grid grid-cols-2 gap-4 text-sm">
                    <div><p class="text-ink-500">α agregat BERKURANG</p><p class="font-display text-xl font-semibold tabular-nums mt-1">{{ $result['aggregated']['alpha_berkurang'] }}</p></div>
                    <div><p class="text-ink-500">α agregat BERTAMBAH</p><p class="font-display text-xl font-semibold tabular-nums mt-1">{{ $result['aggregated']['alpha_bertambah'] }}</p></div>
                </div>
            </x-ui.card>

            <x-ui.card title="Tahap 3 — Defuzzifikasi (Weighted Average)" class="mt-4">
                <div class="bg-ink-50 rounded-lg p-4 font-mono text-sm">
                    <p class="text-ink-500 text-xs mb-2">FORMULA</p>
                    <p>z = (α<sub>berkurang</sub> × z<sub>berkurang</sub> + α<sub>bertambah</sub> × z<sub>bertambah</sub>) / (α<sub>berkurang</sub> + α<sub>bertambah</sub>)</p>
                    <p class="mt-3 text-ink-500 text-xs">SUBSTITUSI</p>
                    <p>
                        z = ({{ $result['aggregated']['alpha_berkurang'] }} × {{ number_format($result['aggregated']['z_berkurang']) }}
                        + {{ $result['aggregated']['alpha_bertambah'] }} × {{ number_format($result['aggregated']['z_bertambah']) }})
                        / ({{ $result['aggregated']['alpha_berkurang'] }} + {{ $result['aggregated']['alpha_bertambah'] }})
                    </p>
                    <p class="mt-3 text-ink-900 font-bold">z ≈ {{ number_format($result['recommended_production']) }} unit</p>
                </div>
            </x-ui.card>
        @else
            <div class="bg-white border border-dashed border-ink-200 rounded-2xl p-12 text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-ink-50 flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-ink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <p class="font-display text-lg font-semibold text-ink-900">Hasil Akan Muncul Di Sini</p>
                <p class="text-sm text-ink-500 mt-2 max-w-xs mx-auto">Isi parameter input lalu klik <em>Hitung Rekomendasi</em> untuk melihat tahap fuzzifikasi, inferensi, dan defuzzifikasi.</p>
            </div>
        @endif
    </div>
</div>
@endsection
