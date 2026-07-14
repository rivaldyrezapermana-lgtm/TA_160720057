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
                <div><p class="text-ink-500">Mesin</p><p class="font-medium">{{ $production->machine?->name ?? '—' }}</p></div>
                <div><p class="text-ink-500">Status</p><x-ui.status-badge :status="$production->status" /></div>
                <div><p class="text-ink-500">Target</p><p class="font-medium tabular-nums">{{ $production->planned_qty }} pcs</p></div>
                <div><p class="text-ink-500">Aktual</p><p class="font-medium tabular-nums">{{ $production->actual_qty }} pcs</p></div>
                <div><p class="text-ink-500">Mulai</p><p class="font-medium">{{ $production->start_date?->translatedFormat('d M Y') ?? '-' }}</p></div>
                <div><p class="text-ink-500">Estimasi Selesai</p><p class="font-medium">{{ $production->end_date?->translatedFormat('d M Y') ?? '-' }}</p></div>
                <div class="md:col-span-2"><p class="text-ink-500">Catatan</p><p class="text-ink-800 mt-1">{{ $production->notes ?: '-' }}</p></div>
            </div>
        </x-ui.card>

        <x-ui.card title="Tahap Produksi" subtitle="Tahap berikutnya bisa mulai saat tahap sebelumnya mencapai 50%">
            @php
                $labels = ['design'=>'Desain','sample'=>'Sample','cutting'=>'Cutting','sewing'=>'Sewing','qc'=>'Quality Check','packing'=>'Packing'];
                $gate = $production->gateQty();
            @endphp
            <div class="space-y-3">
                @foreach ($production->stages as $i => $stage)
                    @php
                        $unlocked = $production->stageUnlocked($stage);
                        $pct = $production->stageProgressPct($stage);
                        $machines = $machinesByStage[$stage->stage] ?? [];
                        $badge = match($stage->status) {
                            'completed' => ['bg-emerald-50 border-emerald-200', 'Selesai'],
                            'in_progress' => ['bg-ink-50 border-ink-200', 'Berlangsung'],
                            default => ['bg-white border-ink-100', $unlocked ? 'Siap' : 'Terkunci'],
                        };
                    @endphp
                    <div class="border rounded-xl px-4 py-3 {{ $badge[0] }}">
                        <div class="flex items-center justify-between mb-2">
                            <p class="font-medium text-ink-900">{{ $i+1 }}. {{ $labels[$stage->stage] ?? $stage->stage }}</p>
                            <span class="text-xs text-ink-500">{{ $badge[1] }} · {{ $pct }}%</span>
                        </div>
                        <div class="h-1.5 bg-ink-100 rounded-full overflow-hidden mb-3">
                            <div class="h-full bg-ink-900" style="width: {{ $pct }}%"></div>
                        </div>

                        @if (! $unlocked && $stage->status === 'pending')
                            <p class="text-xs text-ink-400">Menunggu tahap sebelumnya mencapai {{ $gate }} pcs (50%).</p>
                        @else
                            <form action="{{ route('admin.productions.stage', [$production->id, $stage->id]) }}" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-2 items-end">
                                @csrf @method('PATCH')
                                <div class="field !mb-0">
                                    <label class="label text-xs">Input</label>
                                    <input type="number" name="input_qty" min="0" value="{{ $stage->input_qty }}" class="input" {{ $production->status === 'completed' ? 'disabled' : '' }}>
                                </div>
                                <div class="field !mb-0">
                                    <label class="label text-xs">Output</label>
                                    <input type="number" name="output_qty" min="0" value="{{ $stage->output_qty }}" class="input" {{ $production->status === 'completed' ? 'disabled' : '' }}>
                                </div>
                                <div class="field !mb-0">
                                    <label class="label text-xs">Mesin</label>
                                    <select name="production_machine_id" class="input" {{ $production->status === 'completed' ? 'disabled' : '' }}>
                                        <option value="">— Pilih —</option>
                                        @foreach ($machines as $mid => $mname)
                                            <option value="{{ $mid }}" @selected($stage->production_machine_id == $mid)>{{ $mname }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex gap-2">
                                    @if ($production->status !== 'completed')
                                        @if ($stage->status === 'pending')
                                            <button name="action" value="start" class="btn-secondary text-xs">Mulai</button>
                                        @elseif ($stage->status === 'in_progress')
                                            <button name="action" value="save" class="btn-secondary text-xs">Simpan</button>
                                            <button name="action" value="finish" class="btn-primary text-xs">Selesai</button>
                                        @endif
                                    @endif
                                </div>
                            </form>
                            @if (empty($machines) && ! in_array($stage->stage, ['design','sample'], true))
                                <p class="text-xs text-amber-600 mt-1">Belum ada mesin aktif untuk tahap ini. Tambahkan kategori mesin bertahap "{{ $labels[$stage->stage] ?? $stage->stage }}".</p>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card title="Bahan Terpakai" subtitle="{{ $production->status === 'completed' ? 'Aktual (batch selesai)' : 'Estimasi dari resep × target' }}">
            @php $consumed = $production->productionMaterials; @endphp
            <table class="table-clean">
                <thead><tr><th>Bahan</th><th class="text-right">Per Unit</th><th class="text-right">Total</th><th>Satuan</th></tr></thead>
                <tbody>
                    @if ($production->status === 'completed' && $consumed->isNotEmpty())
                        @foreach ($consumed as $c)
                            <tr>
                                <td>{{ $c->material?->name ?? '—' }}</td>
                                <td class="text-right tabular-nums">-</td>
                                <td class="text-right tabular-nums">{{ number_format($c->qty_used, 0, ',', '.') }}</td>
                                <td class="text-ink-500">{{ $c->material?->unit }}</td>
                            </tr>
                        @endforeach
                    @elseif ($bahan->isNotEmpty())
                        @foreach ($bahan as $b)
                            <tr>
                                <td>{{ $b['name'] }}</td>
                                <td class="text-right tabular-nums">{{ $b['per_unit'] }}</td>
                                <td class="text-right tabular-nums">{{ number_format($b['est'], 0, ',', '.') }}</td>
                                <td class="text-ink-500">{{ $b['unit'] }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr><td colspan="4" class="text-ink-400 text-center">Produk ini belum punya resep bahan.</td></tr>
                    @endif
                </tbody>
            </table>
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
