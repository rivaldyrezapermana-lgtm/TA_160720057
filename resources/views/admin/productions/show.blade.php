@extends('layouts.admin')
@section('title', $production->code)
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Produksi', 'url' => route('admin.productions.index')], ['label' => $production->code]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">{{ $production->code }}</h1>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <x-ui.card title="Informasi Batch">
            <div class="grid md:grid-cols-2 gap-4 text-sm">
                <div><p class="text-ink-500">Produk</p><p class="font-medium">{{ $production->product }}</p></div>
                <div><p class="text-ink-500">Status</p><x-ui.status-badge :status="$production->status" /></div>
                <div><p class="text-ink-500">Target</p><p class="font-medium tabular-nums">{{ $production->planned }} pcs</p></div>
                <div><p class="text-ink-500">Aktual</p><p class="font-medium tabular-nums">{{ $production->actual }} pcs</p></div>
                <div><p class="text-ink-500">Mulai</p><p class="font-medium">{{ $production->start }}</p></div>
                <div><p class="text-ink-500">Estimasi Selesai</p><p class="font-medium">{{ $production->end }}</p></div>
                <div class="md:col-span-2"><p class="text-ink-500">Catatan</p><p class="text-ink-800 mt-1">{{ $production->notes }}</p></div>
            </div>
        </x-ui.card>

        <x-ui.card title="Tahap Produksi">
            <div class="relative pl-8">
                <div class="absolute left-3 top-2 bottom-2 w-px bg-ink-200"></div>
                @foreach ($production->stages as $i => $stage)
                    @php
                        $iconClass = match($stage['status']) {
                            'completed' => 'bg-emerald-600 text-white',
                            'in_progress' => 'bg-ink-900 text-white',
                            default => 'bg-white border-2 border-ink-200 text-ink-400',
                        };
                        $labels = ['design'=>'Desain','sample'=>'Sample','cutting'=>'Cutting','sewing'=>'Sewing','qc'=>'Quality Check','packing'=>'Packing'];
                    @endphp
                    <div class="relative pb-6 last:pb-0">
                        <div class="absolute -left-8 w-6 h-6 rounded-full {{ $iconClass }} flex items-center justify-center text-xs font-semibold">
                            @if ($stage['status'] === 'completed') ✓ @else {{ $i+1 }} @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-ink-900">{{ $labels[$stage['stage']] ?? $stage['stage'] }}</p>
                                @if ($stage['started_at'])
                                    <p class="text-xs text-ink-500 mt-0.5">{{ $stage['started_at'] }} {{ $stage['finished_at'] ? '— '.$stage['finished_at'] : '— berlangsung' }}</p>
                                @else
                                    <p class="text-xs text-ink-400 mt-0.5">Belum dimulai</p>
                                @endif
                            </div>
                            @if ($stage['status'] === 'in_progress')
                                <button class="btn-secondary text-xs">Tandai Selesai</button>
                            @elseif ($stage['status'] === 'pending')
                                <button class="btn-secondary text-xs">Mulai</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card title="Bahan Digunakan">
            <table class="table-clean">
                <thead><tr><th>Bahan</th><th class="text-right">Qty</th><th>Unit</th></tr></thead>
                <tbody>
                    @foreach ($production->materials as $m)
                        <tr><td class="font-medium">{{ $m['name'] }}</td><td class="text-right tabular-nums">{{ $m['used'] }}</td><td class="text-ink-500">{{ $m['unit'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    </div>

    <div>
        <x-ui.card title="Progres">
            @php $pct = $production->planned > 0 ? round(($production->actual / $production->planned) * 100) : 0; @endphp
            <div class="text-center mb-4">
                <p class="font-display text-5xl font-semibold tabular-nums">{{ $pct }}<span class="text-2xl text-ink-400">%</span></p>
                <p class="text-sm text-ink-500 mt-1">{{ $production->actual }} dari {{ $production->planned }}</p>
            </div>
            <div class="h-2 bg-ink-100 rounded-full overflow-hidden">
                <div class="h-full bg-ink-900" style="width: {{ $pct }}%"></div>
            </div>
        </x-ui.card>
    </div>
</div>
@endsection
