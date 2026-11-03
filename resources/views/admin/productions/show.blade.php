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
                @if ($production->sample_size)
                    <div><p class="text-ink-500">Ukuran Sampel</p><p class="font-medium">{{ $production->sample_size }}</p></div>
                @endif
                @if ($production->hasSamplePhase())
                    <div>
                        <p class="text-ink-500">Sampel</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <p class="font-medium">
                                @if ($production->sampleSource)
                                    Pakai sampel {{ $production->sampleSource->code }}
                                @elseif ($production->sampleApproved())
                                    Disetujui
                                @else
                                    Belum disetujui
                                @endif
                            </p>
                            @if ($production->status !== 'completed')
                                <form action="{{ route('admin.productions.check-sample', $production->id) }}" method="POST">
                                    @csrf
                                    <button class="btn-secondary text-xs" title="Belum ada sampel → ke Tahap Sampel. Sudah ada → langsung Produksi Massal.">Cek Sampel</button>
                                </form>
                            @endif
                            <button type="button" onclick="openModal('modal-sampel')" class="text-xs text-ink-500 hover:text-ink-800 underline">Detail</button>
                        </div>
                    </div>
                @endif
                <div><p class="text-ink-500">Mulai</p><p class="font-medium">{{ $production->start_date?->translatedFormat('d M Y') ?? '-' }}</p></div>
                <div><p class="text-ink-500">Estimasi Selesai</p><p class="font-medium">{{ $production->end_date?->translatedFormat('d M Y') ?? '-' }}</p></div>
                <div class="md:col-span-2"><p class="text-ink-500">Catatan</p><p class="text-ink-800 mt-1">{{ $production->notes ?: '-' }}</p></div>
            </div>
        </x-ui.card>

        @if ($production->sizes->isNotEmpty())
            <x-ui.card title="Rincian per Ukuran" subtitle="{{ $production->status === 'completed' ? 'Hasil sudah masuk ke stok tiap ukuran' : 'Hasil diisi saat QC & Packing massal diselesaikan' }}">
                <table class="table-clean">
                    <thead><tr><th>Ukuran</th><th class="text-right">Target</th><th class="text-right">Hasil</th><th class="text-right">Capaian</th></tr></thead>
                    <tbody>
                        @foreach ($production->sizes as $row)
                            <tr>
                                <td class="font-medium">{{ $row->size }}</td>
                                <td class="text-right tabular-nums">{{ $row->planned_qty }} pcs</td>
                                <td class="text-right tabular-nums">{{ $production->status === 'completed' ? $row->actual_qty.' pcs' : '-' }}</td>
                                <td class="text-right tabular-nums text-ink-500">{{ $production->status === 'completed' && $row->planned_qty > 0 ? round($row->actual_qty / $row->planned_qty * 100).'%' : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.card>
        @endif

        @php
            $phaseMeta = [
                'common' => ['Persiapan', 'Desain dan pola dikerjakan sekali di awal'],
                'sample' => ['Tahap Sampel', 'Satu potong dulu — produksi massal terbuka setelah sampel disetujui'],
                'mass' => ['Produksi Massal', 'Tahap berikutnya bisa mulai saat tahap sebelumnya mencapai 50%'],
            ];
            $grouped = $production->stages->sortBy('sort_order')->groupBy('phase');
            $gate = $production->gateQty();
            $locked = $production->status === 'completed';
        @endphp

        @if (! $production->hasSamplePhase())
            <x-ui.card>
                <p class="text-sm text-ink-600">Batch 1 pcs — produk ini sekaligus sampelnya, jadi tidak ada fase sampel terpisah.</p>
            </x-ui.card>
        @endif

        @foreach (['common', 'sample', 'mass'] as $phase)
            @continue (! isset($grouped[$phase]))
            <x-ui.card :title="$phaseMeta[$phase][0]" :subtitle="$phaseMeta[$phase][1]">
                @if ($phase === 'sample' && $production->sample_size)
                    <p id="tahap-sampel" class="text-xs text-ink-500 mb-3 scroll-mt-24">Sampel dibuat dalam ukuran <span class="font-medium text-ink-800">{{ $production->sample_size }}</span>.</p>
                @elseif ($phase === 'sample')
                    <span id="tahap-sampel" class="scroll-mt-24"></span>
                @endif
                @if ($phase === 'mass')
                    <span id="produksi-massal" class="scroll-mt-24"></span>
                    @if ($production->sampleSource && ! isset($grouped['common']))
                        <p class="text-xs text-ink-500 mb-3">
                            Desain dan pola mengikuti sampel {{ $production->sampleSource->code }} —
                            <a href="{{ route('admin.productions.show', $production->sampleSource->id) }}" class="underline hover:text-ink-800">lihat batch sampel</a>.
                        </p>
                    @endif
                @endif
                @if ($phase === 'mass' && $production->hasSamplePhase() && ! $production->sampleApproved())
                    <p class="text-xs text-amber-600 mb-3">Terkunci sampai sampel disetujui.</p>
                @endif
                <div class="space-y-3">
                    @foreach ($grouped[$phase] as $stage)
                        @php
                            $unlocked = $production->stageUnlocked($stage);
                            $hasQty = $stage->carriesQty();
                            $qtyEditable = $hasQty && $stage->phase === 'mass';
                            // Fase sampel selalu 1 pcs, jadi progresnya biner — bukan 1% dari target batch.
                            $pct = ($hasQty && $stage->phase === 'mass')
                                ? $production->stageProgressPct($stage)
                                : ($stage->status === 'completed' ? 100 : 0);
                            $machines = $machinesByStage[$stage->stage] ?? [];
                            $maxOut = $production->stageMaxOutput($stage);
                            $minOut = $production->stageMinOutput($stage);
                            $isSampleQc = $stage->phase === 'sample' && $stage->stage === 'qc_packing';
                            // Menutup QC massal pada batch multi-ukuran butuh rincian hasil per ukuran.
                            $sizedStage = $qtyEditable && $stage->sizeRows->isNotEmpty();
                            $sizeLimits = $sizedStage ? $production->stageSizeLimits($stage) : [];
                            $needsSizeOutput = ! $sizedStage && $stage->phase === 'mass' && $stage->stage === 'qc_packing'
                                && $stage->status === 'in_progress' && $production->sizes->count() > 1 && ! $locked;
                            $badge = match($stage->status) {
                                'completed' => ['bg-emerald-50 border-emerald-200', 'Selesai'],
                                'in_progress' => ['bg-ink-50 border-ink-200', 'Berlangsung'],
                                default => ['bg-white border-ink-100', $unlocked ? 'Siap' : 'Terkunci'],
                            };
                        @endphp
                        <div class="border rounded-xl px-4 py-3 {{ $badge[0] }}">
                            <div class="flex items-center justify-between mb-2">
                                <p class="font-medium text-ink-900">{{ $stage->sort_order }}. {{ $stage->label() }}</p>
                                <span class="text-xs text-ink-500">{{ $badge[1] }}{{ $hasQty ? ' · '.$pct.'%' : '' }}</span>
                            </div>
                            @if ($hasQty)
                                <div class="h-1.5 bg-ink-100 rounded-full overflow-hidden mb-3">
                                    <div class="h-full bg-ink-900" style="width: {{ $pct }}%"></div>
                                </div>
                            @endif

                            @if (! $unlocked && $stage->status === 'pending')
                                <p class="text-xs text-ink-400">
                                    @switch ($production->lockCause($stage))
                                        @case ('sample_not_approved') Menunggu sampel disetujui. @break
                                        @case ('gate_50') Menunggu tahap sebelumnya mencapai {{ $gate }} pcs (50%). @break
                                        @default Menunggu tahap sebelumnya selesai.
                                    @endswitch
                                </p>
                            @else
                                <form action="{{ route('admin.productions.stage', [$production->id, $stage->id]) }}" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-2 items-end">
                                    @csrf @method('PATCH')
                                    @if ($sizedStage)
                                        <div class="field !mb-0 md:col-span-2">
                                            <label class="label text-xs">Jumlah</label>
                                            <p class="text-sm text-ink-700 tabular-nums">Sisa {{ $stage->input_qty }} · Output {{ $stage->output_qty }} pcs</p>
                                            <p class="text-[11px] text-ink-400 mt-0.5">
                                                Diisi per ukuran di bawah ·
                                                @if ($production->nextQtyStage($stage))
                                                    diteruskan ke {{ $production->nextQtyStage($stage)->label() }}
                                                @else
                                                    masuk stok per ukuran saat Selesai
                                                @endif
                                            </p>
                                        </div>
                                    @elseif ($qtyEditable)
                                        <div class="field !mb-0">
                                            <label class="label text-xs">Input (sisa)</label>
                                            <p class="input bg-ink-50 tabular-nums">{{ $stage->input_qty }} pcs</p>
                                            <p class="text-[11px] text-ink-400 mt-0.5">Diterima {{ $maxOut }} pcs · berkurang otomatis saat output diisi</p>
                                        </div>
                                        <div class="field !mb-0">
                                            <label class="label text-xs">Output (total)</label>
                                            <input type="number" name="output_qty" min="{{ $minOut }}" max="{{ $maxOut }}" value="{{ $stage->output_qty }}" class="input" {{ $locked ? 'disabled' : '' }}>
                                            <p class="text-[11px] text-ink-400 mt-0.5">
                                                @if ($production->nextQtyStage($stage))
                                                    Diteruskan ke {{ $production->nextQtyStage($stage)->label() }}
                                                @else
                                                    Masuk stok saat Selesai
                                                @endif
                                                @if ($minOut > 0) · min {{ $minOut }} pcs @endif
                                            </p>
                                            @error('output_qty')<p class="text-xs text-red-600 mt-0.5">{{ $message }}</p>@enderror
                                        </div>
                                    @elseif ($hasQty)
                                        <div class="field !mb-0 md:col-span-2">
                                            <label class="label text-xs">Jumlah</label>
                                            <p class="text-sm text-ink-700 tabular-nums">1 pcs (sampel)</p>
                                        </div>
                                    @else
                                        <div class="field !mb-0 md:col-span-2">
                                            <label class="label text-xs">Jumlah</label>
                                            <p class="text-sm text-ink-500">Tidak menghasilkan pcs</p>
                                        </div>
                                    @endif
                                    @if ($stage->usesMachine())
                                        <div class="field !mb-0">
                                            <label class="label text-xs">Mesin</label>
                                            <select name="production_machine_id" class="input" {{ $locked ? 'disabled' : '' }}>
                                                <option value="">— Pilih —</option>
                                                @foreach ($machines as $mid => $mname)
                                                    <option value="{{ $mid }}" @selected($stage->production_machine_id == $mid)>{{ $mname }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                    <div class="flex gap-2">
                                        @unless ($locked)
                                            @if ($stage->status === 'pending')
                                                <button name="action" value="start" class="btn-secondary text-xs">Mulai</button>
                                            @elseif ($stage->status === 'in_progress')
                                                <button name="action" value="save" class="btn-secondary text-xs">Simpan</button>
                                                <button name="action" value="finish" class="btn-primary text-xs">Selesai</button>
                                            @else
                                                <button name="action" value="save" class="btn-secondary text-xs">Simpan</button>
                                            @endif
                                        @endunless
                                    </div>
                                    @if ($sizedStage)
                                        <div class="col-span-2 md:col-span-4 border-t border-ink-100 pt-3 mt-1">
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr class="text-xs text-ink-500">
                                                        <th class="text-left font-normal py-1">Ukuran</th>
                                                        <th class="text-right font-normal py-1">Diterima</th>
                                                        <th class="text-right font-normal py-1">Sisa</th>
                                                        <th class="text-right font-normal py-1 w-32">Output (total)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($stage->sizeRows as $row)
                                                        @php $lim = $sizeLimits[$row->size]; @endphp
                                                        <tr class="border-t border-ink-100">
                                                            <td class="py-1.5 font-medium">{{ $row->size }}</td>
                                                            <td class="py-1.5 text-right tabular-nums text-ink-500">{{ $lim['received'] }}</td>
                                                            <td class="py-1.5 text-right tabular-nums">{{ $row->input_qty }}</td>
                                                            <td class="py-1.5 text-right">
                                                                <input type="number" name="size_qty[{{ $row->size }}]" min="{{ $lim['min'] }}" max="{{ $lim['max'] }}"
                                                                    value="{{ old('size_qty.'.$row->size, $row->output_qty) }}"
                                                                    class="input !py-1 text-right tabular-nums" {{ $locked || $stage->status === 'pending' ? 'disabled' : '' }}>
                                                                @if ($lim['min'] > 0)<p class="text-[11px] text-ink-400">min {{ $lim['min'] }}</p>@endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            @error('size_qty')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                        </div>
                                    @endif
                                    @if ($needsSizeOutput)
                                        <div class="col-span-2 md:col-span-4 border-t border-ink-100 pt-3 mt-1">
                                            <p class="text-xs text-ink-500 mb-2">Hasil per ukuran — wajib diisi saat menekan <em>Selesai</em>; totalnya harus sama dengan output.</p>
                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                @foreach ($production->sizes as $row)
                                                    <div class="field !mb-0">
                                                        <label class="label text-xs">{{ $row->size }} <span class="text-ink-400 font-normal">/ {{ $row->planned_qty }}</span></label>
                                                        <input type="number" name="size_output[{{ $row->size }}]" min="0" max="{{ $row->planned_qty }}" value="{{ old('size_output.'.$row->size, $row->planned_qty) }}" class="input">
                                                    </div>
                                                @endforeach
                                            </div>
                                            @error('size_output')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                        </div>
                                    @endif
                                </form>

                                @if ($stage->stage === 'design')
                                    @php $sketchLocked = $locked || $stage->status === 'completed'; @endphp
                                    <div class="mt-3 border-t border-ink-100 pt-3">
                                        <p class="label text-xs">Sketsa Desain @unless ($sketchLocked)<span class="text-ink-400 font-normal">— wajib minimal satu sebelum tahap ini diselesaikan</span>@endunless</p>
                                        @if ($production->designSketches->isNotEmpty())
                                            <div class="grid grid-cols-3 md:grid-cols-5 gap-2 mb-2">
                                                @foreach ($production->designSketches as $sketch)
                                                    <div class="border border-ink-100 rounded-lg overflow-hidden bg-white">
                                                        <a href="{{ Storage::disk('public')->url($sketch->path) }}" target="_blank" rel="noopener" title="{{ $sketch->original_name }}">
                                                            <img src="{{ Storage::disk('public')->url($sketch->path) }}" alt="{{ $sketch->original_name }}" class="w-full aspect-square object-cover">
                                                        </a>
                                                        @unless ($sketchLocked)
                                                            <form action="{{ route('admin.productions.sketches.destroy', [$production->id, $sketch->id]) }}" method="POST" class="px-2 py-1 text-right">
                                                                @csrf @method('DELETE')
                                                                <button class="text-[11px] text-red-600 hover:underline">Hapus</button>
                                                            </form>
                                                        @endunless
                                                    </div>
                                                @endforeach
                                            </div>
                                        @elseif ($sketchLocked)
                                            <p class="text-xs text-ink-400">Tidak ada sketsa untuk batch ini.</p>
                                        @else
                                            <p class="text-xs text-ink-400 mb-2">Belum ada sketsa.</p>
                                        @endif
                                        @unless ($sketchLocked)
                                            <form action="{{ route('admin.productions.sketches.store', $production->id) }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                                @csrf
                                                <input type="file" name="sketches[]" multiple accept="image/jpeg,image/png,image/webp" class="text-xs">
                                                <button class="btn-secondary text-xs">Unggah Sketsa</button>
                                            </form>
                                            <p class="text-[11px] text-ink-400 mt-1">JPG, PNG, atau WebP, maksimal 2 MB per file.</p>
                                            @if ($errors->has('sketches') || $errors->has('sketches.*'))
                                                <p class="text-xs text-red-600 mt-1">{{ $errors->first('sketches') ?: $errors->first('sketches.*') }}</p>
                                            @endif
                                        @endunless
                                    </div>
                                @endif

                                @if ($isSampleQc && $stage->status === 'completed' && ! $locked)
                                    <div class="mt-3 border-t border-ink-100 pt-3">
                                        @if ($production->sampleApproved())
                                            <p class="text-xs text-emerald-700">Sampel disetujui {{ $production->sample_approved_at->translatedFormat('d M Y') }}. Produksi massal terbuka.</p>
                                        @else
                                            <form action="{{ route('admin.productions.stage', [$production->id, $stage->id]) }}" method="POST" class="space-y-2">
                                                @csrf @method('PATCH')
                                                <label class="label text-xs">Catatan revisi (wajib kalau sampel perlu diperbaiki)</label>
                                                <textarea name="notes" rows="2" class="input" placeholder="Contoh: panjang lengan kurang 2cm">{{ old('notes') }}</textarea>
                                                @error('notes')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                                <div class="flex gap-2">
                                                    <button name="action" value="approve_sample" class="btn-primary text-xs">Sampel disetujui</button>
                                                    <button name="action" value="revise_sample" class="btn-secondary text-xs">Perlu revisi</button>
                                                </div>
                                            </form>
                                        @endif
                                    </div>
                                @endif

                                @if (empty($machines) && $hasQty)
                                    <p class="text-xs text-amber-600 mt-1">Belum ada mesin aktif untuk tahap ini. Tambahkan kategori mesin bertahap "{{ $stage->label() }}".</p>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach

        @if ($production->sampleRevisions->isNotEmpty())
            <x-ui.card title="Riwayat Revisi Sampel" subtitle="{{ $production->sample_revision_count }} kali diperbaiki sebelum disetujui">
                <ul class="space-y-3">
                    @foreach ($production->sampleRevisions as $rev)
                        <li class="border-l-2 border-amber-300 pl-3">
                            <p class="text-sm font-medium text-ink-900">Revisi ke-{{ $rev->revision_no }}
                                <span class="text-xs font-normal text-ink-400">· {{ $rev->created_at?->translatedFormat('d M Y') }}{{ $rev->user ? ' · '.$rev->user->name : '' }}</span>
                            </p>
                            <p class="text-sm text-ink-600">{{ $rev->notes }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <x-ui.card title="Bahan Terpakai" subtitle="{{ $production->status === 'completed' ? 'Aktual (batch selesai)' : 'Estimasi dari resep × (target + sampel)' }}">
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

@if ($production->hasSamplePhase())
    @php
        $sampleQc = $production->stages->first(fn ($s) => $s->phase === 'sample' && $s->stage === 'qc_packing');
        $sampleStatus = match (true) {
            $production->sampleSource !== null => ['text-emerald-700', 'Pakai sampel '.$production->sampleSource->code],
            $production->sampleApproved() => ['text-emerald-700', 'Disetujui '.$production->sample_approved_at->translatedFormat('d M Y')],
            $sampleQc?->status === 'completed' => ['text-amber-600', 'Sudah jadi, menunggu dicek'],
            $production->stages->contains(fn ($s) => $s->phase === 'sample' && $s->status !== 'pending') => ['text-ink-700', 'Sedang dibuat'],
            default => ['text-ink-500', 'Belum dibuat'],
        };
        $canReuse = ! $locked && $existingSample !== null && $production->canReuseSample();
    @endphp
    <x-ui.modal id="modal-sampel" title="Cek Sampel — {{ $production->product?->name ?? $production->code }}" size="xl">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-4">
            <div><p class="text-ink-500 text-xs">Batch</p><p class="font-medium">{{ $production->code }}</p></div>
            <div><p class="text-ink-500 text-xs">Ukuran</p><p class="font-medium">{{ $production->sample_size ?? '—' }}</p></div>
            <div><p class="text-ink-500 text-xs">Status</p><p class="font-medium {{ $sampleStatus[0] }}">{{ $sampleStatus[1] }}</p></div>
            <div><p class="text-ink-500 text-xs">Revisi</p><p class="font-medium tabular-nums">{{ $production->sample_revision_count }} kali</p></div>
        </div>

        @if ($existingSample)
            <div class="rounded-lg border px-4 py-3 mb-4 {{ $canReuse ? 'border-emerald-200 bg-emerald-50' : 'border-ink-100 bg-ink-50' }}">
                <p class="text-sm text-ink-800">
                    Produk ini sudah punya sampel dari batch <span class="font-medium">{{ $existingSample->code }}</span>
                    @if ($existingSample->sample_approved_at)
                        (disetujui {{ $existingSample->sample_approved_at->translatedFormat('d M Y') }}).
                    @else
                        (batch selesai).
                    @endif
                </p>
                @if ($canReuse)
                    <p class="text-xs text-ink-500 mt-1">Tidak perlu buat sampel lagi — Persiapan dan tahap sampel batch ini dilewati, langsung ke produksi massal.</p>
                    <form action="{{ route('admin.productions.reuse-sample', $production->id) }}" method="POST" class="mt-2">
                        @csrf
                        <button class="btn-primary text-xs">Pakai sampel ini, langsung produksi massal</button>
                    </form>
                @elseif (! $production->sampleSource && ! $production->sampleApproved())
                    <p class="text-xs text-ink-500 mt-1">Sampel batch ini sudah mulai dibuat, jadi lanjutkan di Tahap Sampel.</p>
                @endif
            </div>

            <p class="label text-xs">Foto Desain {{ $existingSample->code }}</p>
            @include('admin.productions.partials.sketch-grid', ['sketches' => $existingSample->designSketches])
        @elseif (! $production->sampleApproved())
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 mb-4">
                <p class="text-sm text-ink-800">Produk ini belum punya sampel. Buat sampel dulu (1 pcs) sebelum produksi massal.</p>
                <a href="#tahap-sampel" onclick="closeModal('modal-sampel')" class="btn-primary text-xs mt-2 inline-flex">Ke Tahap Sampel</a>
            </div>
        @endif

        @if (! $production->sampleSource)
            <p class="label text-xs mt-4">Foto Desain Batch Ini</p>
            @include('admin.productions.partials.sketch-grid', ['sketches' => $production->designSketches])
        @endif

        @if ($production->sampleRevisions->isNotEmpty())
            <p class="label text-xs mt-4">Catatan Revisi Terakhir</p>
            <p class="text-sm text-ink-700">{{ $production->sampleRevisions->first()->notes }}</p>
        @endif
    </x-ui.modal>
@endif
@endsection
