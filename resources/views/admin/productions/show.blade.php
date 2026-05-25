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
                <div><p class="text-ink-500">Produk</p><p class="font-medium">{{ $production->product?->name ?? '—' }}</p></div>
                <div><p class="text-ink-500">Status</p><x-ui.status-badge :status="$production->status" /></div>
                <div><p class="text-ink-500">Target</p><p class="font-medium tabular-nums">{{ $production->planned_qty }} pcs</p></div>
                <div><p class="text-ink-500">Aktual</p><p class="font-medium tabular-nums">{{ $production->actual_qty }} pcs</p></div>
                <div><p class="text-ink-500">Mulai</p><p class="font-medium">{{ $production->start_date?->translatedFormat('d M Y') ?? '-' }}</p></div>
                <div><p class="text-ink-500">Estimasi Selesai</p><p class="font-medium">{{ $production->end_date?->translatedFormat('d M Y') ?? '-' }}</p></div>
                <div class="md:col-span-2"><p class="text-ink-500">Catatan</p><p class="text-ink-800 mt-1">{{ $production->notes ?: '-' }}</p></div>
            </div>
        </x-ui.card>

        <x-ui.card title="Tahap Produksi">
            <div class="relative pl-8">
                <div class="absolute left-3 top-2 bottom-2 w-px bg-ink-200"></div>
                @foreach ($production->stages as $i => $stage)
                    @php
                        $iconClass = match($stage->status) {
                            'completed' => 'bg-emerald-600 text-white',
                            'in_progress' => 'bg-ink-900 text-white',
                            default => 'bg-white border-2 border-ink-200 text-ink-400',
                        };
                        $labels = ['design'=>'Desain','sample'=>'Sample','cutting'=>'Cutting','sewing'=>'Sewing','qc'=>'Quality Check','packing'=>'Packing'];
                    @endphp
                    <div class="relative pb-6 last:pb-0">
                        <div class="absolute -left-8 w-6 h-6 rounded-full {{ $iconClass }} flex items-center justify-center text-xs font-semibold">
                            @if ($stage->status === 'completed') ✓ @else {{ $i+1 }} @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-ink-900">{{ $labels[$stage->stage] ?? $stage->stage }}</p>
                                @if ($stage->started_at)
                                    <p class="text-xs text-ink-500 mt-0.5">{{ $stage->started_at->translatedFormat('d M H:i') }} {{ $stage->finished_at ? '— '.$stage->finished_at->translatedFormat('d M H:i') : '— berlangsung' }}</p>
                                @else
                                    <p class="text-xs text-ink-400 mt-0.5">Belum dimulai</p>
                                @endif
                            </div>
                            @if ($stage->status === 'in_progress')
                                <form action="{{ route('admin.productions.stage', [$production->id, $stage->id]) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="action" value="finish">
                                    <button class="btn-secondary text-xs">Tandai Selesai</button>
                                </form>
                            @elseif ($stage->status === 'pending')
                                <form action="{{ route('admin.productions.stage', [$production->id, $stage->id]) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="action" value="start">
                                    <button class="btn-secondary text-xs">Mulai</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card title="Bahan Digunakan">
            @if ($production->materials->isEmpty())
                <p class="text-sm text-ink-500">Tidak ada bahan dicatat untuk batch ini.</p>
            @else
                <table class="table-clean">
                    <thead><tr><th>Bahan</th><th class="text-right">Qty</th><th>Unit</th></tr></thead>
                    <tbody>
                        @foreach ($production->materials as $m)
                            <tr>
                                <td class="font-medium">{{ $m->material?->name ?? '—' }}</td>
                                <td class="text-right tabular-nums">{{ $m->qty_used }}</td>
                                <td class="text-ink-500">{{ $m->material?->unit ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>
    </div>

    <div class="space-y-4">
        <x-ui.card title="Progres">
            @php $pct = $production->planned_qty > 0 ? round(($production->actual_qty / $production->planned_qty) * 100) : 0; @endphp
            <div class="text-center mb-4">
                <p class="font-display text-5xl font-semibold tabular-nums">{{ $pct }}<span class="text-2xl text-ink-400">%</span></p>
                <p class="text-sm text-ink-500 mt-1">{{ $production->actual_qty }} dari {{ $production->planned_qty }}</p>
            </div>
            <div class="h-2 bg-ink-100 rounded-full overflow-hidden">
                <div class="h-full bg-ink-900" style="width: {{ $pct }}%"></div>
            </div>
        </x-ui.card>

        <x-ui.card title="Aksi">
            <a href="{{ route('admin.productions.edit', $production->id) }}" class="btn-secondary w-full justify-center mb-2">Edit Batch</a>
            @if (in_array($production->status, ['planned','cancelled'], true))
                <form action="{{ route('admin.productions.destroy', $production->id) }}" method="POST" onsubmit="return confirm('Hapus batch ini?')">
                    @csrf @method('DELETE')
                    <button class="btn-secondary w-full justify-center text-red-600">Hapus Batch</button>
                </form>
            @endif
        </x-ui.card>
    </div>
</div>
@endsection
