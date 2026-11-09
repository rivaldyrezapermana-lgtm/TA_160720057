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
                <div><p class="text-ink-500">Mesin</p><p class="font-medium">{{ $production->machine?->display_name ?? '—' }}</p></div>
                <div><p class="text-ink-500">Status</p><x-ui.status-badge :status="$production->status" /></div>
                <div><p class="text-ink-500">Target</p><p class="font-medium tabular-nums">{{ $production->planned_qty }} pcs</p></div>
                <div><p class="text-ink-500">Aktual</p><p class="font-medium tabular-nums">{{ $production->actual_qty }} pcs</p></div>
                @if ($production->sample_size && $production->makesOwnSample())
                    <div><p class="text-ink-500">Ukuran Sampel</p><p class="font-medium">{{ $production->sample_size }}</p></div>
                @endif
                @if ($production->hasSamplePhase())
                    <div>
                        <p class="text-ink-500">Sampel</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <p class="font-medium">
                                @if ($production->sampleSource)
                                    Menggunakan sampel {{ $production->sampleSource->code }}
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
            <x-ui.card title="Rincian per Ukuran" subtitle="{{ $production->status === 'completed' ? 'Hasil sudah masuk ke stok tiap ukuran.' : 'Hasil diisi saat QC & Packing massal diselesaikan.' }}">
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
                'common' => ['Persiapan', 'Desain dan pola dikerjakan sekali di awal.'],
                'sample' => ['Tahap Sampel', 'Satu potong terlebih dahulu — produksi massal terbuka setelah sampel disetujui.'],
                'mass' => ['Produksi Massal', 'Tahap berikutnya dapat dimulai saat tahap sebelumnya mencapai 50%.'],
            ];
            $grouped = $production->stages->sortBy('sort_order')->groupBy('phase');
            $gate = $production->gateQty();
            $locked = $production->status === 'completed';
        @endphp

        @if (! $production->hasSamplePhase())
            <x-ui.card>
                <p class="text-sm text-ink-600">Batch 1 pcs — produk ini sekaligus sampelnya, sehingga tidak ada fase sampel terpisah.</p>
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
                            // Mesin yang sudah terpasang tetap tampil walaupun sedang tidak aktif,
                            // supaya tidak terlepas saat form disimpan.
                            $machines = ($machinesByStage[$stage->stage] ?? [])
                                + $stage->machines->mapWithKeys(fn ($m) => [$m->id => $m->display_name])->all();
                            $received = $production->stageReceived($stage);
                            $isSampleQc = $stage->phase === 'sample' && $stage->stage === 'qc_packing';
                            // Menutup QC massal pada batch multi-ukuran butuh rincian hasil per ukuran.
                            $sizedStage = $qtyEditable && $stage->sizeRows->isNotEmpty();
                            // Semua form tahap memakai nama field yang sama; isian lama dan
                            // pesan error hanya milik tahap yang tadi dikirim.
                            $isOldStage = (int) old('_stage') === (int) $stage->id;
                            $needsSizeOutput = ! $sizedStage && $stage->phase === 'mass' && $stage->stage === 'qc_packing'
                                && $stage->status === 'in_progress' && $production->sizes->count() > 1 && ! $locked;
                            // Isian terbuka selama tahap belum selesai. Tahap massal yang selesai
                            // lebih dulu tetap terbuka kalau tahap sebelumnya masih mengirim pcs,
                            // supaya pcs itu tidak tersangkut di sisa.
                            $stageClosed = $locked
                                || ($stage->status === 'completed' && (! $qtyEditable || (int) $stage->input_qty === 0));
                            $confirmsPcs = $qtyEditable && ! $stageClosed;
                            $badge = match($stage->status) {
                                'completed' => ['bg-emerald-50 border-emerald-200', 'Selesai'],
                                'in_progress' => ['bg-ink-50 border-ink-200', 'Berjalan'],
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
                                <form action="{{ route('admin.productions.stage', [$production->id, $stage->id]) }}" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-2 items-end"
                                    @if ($confirmsPcs) data-daily-form data-stage-label="{{ $stage->label() }}" @endif>
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="_stage" value="{{ $stage->id }}">
                                    @if ($sizedStage)
                                        <div class="field !mb-0 col-span-2">
                                            <label class="label text-xs">Jumlah</label>
                                            <p class="text-sm text-ink-700 tabular-nums">Diterima {{ $received }} · Sisa {{ $stage->input_qty }} · Output {{ $stage->output_qty }} pcs</p>
                                            <p class="text-[11px] text-ink-400 mt-0.5">
                                                Isi pcs yang baru dikerjakan per ukuran di bawah — sisa berkurang, output bertambah ·
                                                @if ($production->nextQtyStage($stage))
                                                    diteruskan ke {{ $production->nextQtyStage($stage)->label() }}
                                                @else
                                                    masuk stok per ukuran saat Selesai
                                                @endif
                                            </p>
                                        </div>
                                    @elseif ($qtyEditable)
                                        <div class="field !mb-0">
                                            <label class="label text-xs">Input (Sisa)</label>
                                            <p class="input bg-ink-50 tabular-nums">{{ $stage->input_qty }} pcs</p>
                                            <p class="text-[11px] text-ink-400 mt-0.5">Diterima {{ $received }} pcs · output {{ $stage->output_qty }} pcs</p>
                                        </div>
                                        <div class="field !mb-0">
                                            <label class="label text-xs">Kurangi Sisa</label>
                                            <input type="number" name="qty" min="0" max="{{ $stage->input_qty }}" value="{{ $isOldStage ? old('qty') : '' }}" placeholder="0" class="input disabled:bg-ink-50 disabled:cursor-not-allowed" data-daily-qty {{ $stageClosed ? 'disabled' : '' }}>
                                            <p class="text-[11px] text-ink-400 mt-0.5">
                                                Pcs yang baru dikerjakan · menambah output,
                                                @if ($production->nextQtyStage($stage))
                                                    diteruskan ke {{ $production->nextQtyStage($stage)->label() }}
                                                @else
                                                    masuk stok saat Selesai
                                                @endif
                                            </p>
                                            @if ($isOldStage)
                                                @error('qty')<p class="text-xs text-red-600 mt-0.5">{{ $message }}</p>@enderror
                                            @endif
                                        </div>
                                    @elseif ($hasQty)
                                        <div class="field !mb-0 col-span-2">
                                            <label class="label text-xs">Jumlah</label>
                                            <p class="text-sm text-ink-700 tabular-nums">1 pcs (sampel)</p>
                                        </div>
                                    @else
                                        <div class="field !mb-0 col-span-2 {{ $stage->usesMachine() ? '' : 'md:col-span-4' }}">
                                            <label class="label text-xs">Jumlah</label>
                                            <p class="text-sm text-ink-500">Tidak menghasilkan pcs</p>
                                        </div>
                                    @endif
                                    @if ($stage->usesMachine())
                                        @php
                                            $selectedMachines = $isOldStage
                                                ? array_map('intval', array_filter((array) old('production_machine_ids', [])))
                                                : $stage->machines->pluck('id')->all();
                                        @endphp
                                        <div class="field !mb-0 col-span-2 self-start">
                                            <label class="label text-xs">Mesin <span class="text-ink-400 font-normal">(dapat memilih lebih dari satu)</span></label>
                                            <input type="hidden" name="production_machine_ids[]" value="">
                                            @forelse ($machines as $mid => $mname)
                                                <label class="flex items-center gap-2 text-sm text-ink-800 py-0.5 cursor-pointer">
                                                    <input type="checkbox" name="production_machine_ids[]" value="{{ $mid }}" @checked(in_array($mid, $selectedMachines, true)) class="rounded" {{ $stageClosed ? 'disabled' : '' }}>
                                                    <span>{{ $mname }}</span>
                                                </label>
                                            @empty
                                                <p class="text-sm text-ink-500">Belum ada mesin untuk tahap ini.</p>
                                            @endforelse
                                            @if ($isOldStage)
                                                @error('production_machine_ids.*')<p class="text-xs text-red-600 mt-0.5">{{ $message }}</p>@enderror
                                            @endif
                                        </div>
                                    @endif
                                    @if ($sizedStage)
                                        <div class="col-span-2 md:col-span-4 border-t border-ink-100 pt-3 mt-1">
                                            <table class="w-full text-sm">
                                                <thead>
                                                    <tr class="text-xs text-ink-500">
                                                        <th class="text-left font-normal py-1">Ukuran</th>
                                                        <th class="text-right font-normal py-1">Diterima</th>
                                                        <th class="text-right font-normal py-1">Sisa</th>
                                                        <th class="text-right font-normal py-1">Output</th>
                                                        <th class="text-right font-normal py-1 w-32">Kurangi Sisa</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($stage->sizeRows as $row)
                                                        <tr class="border-t border-ink-100">
                                                            <td class="py-1.5 font-medium">{{ $row->size }}</td>
                                                            <td class="py-1.5 text-right tabular-nums text-ink-500">{{ $row->input_qty + $row->output_qty }}</td>
                                                            <td class="py-1.5 text-right tabular-nums">{{ $row->input_qty }}</td>
                                                            <td class="py-1.5 text-right tabular-nums">{{ $row->output_qty }}</td>
                                                            <td class="py-1.5 text-right">
                                                                <input type="number" name="size_qty[{{ $row->size }}]" min="0" max="{{ $row->input_qty }}"
                                                                    value="{{ $isOldStage ? old('size_qty.'.$row->size) : '' }}" placeholder="0"
                                                                    class="input !py-1 text-right tabular-nums disabled:bg-ink-50 disabled:cursor-not-allowed" data-daily-qty {{ $stageClosed ? 'disabled' : '' }}>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            @if ($isOldStage)
                                                @error('size_qty')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                            @endif
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
                                    @if ($qtyEditable && $stage->status === 'completed' && ! $stageClosed)
                                        <p class="col-span-2 md:col-span-4 text-xs text-amber-700">Tahap sudah selesai, tetapi masih ada sisa {{ $stage->input_qty }} pcs. Isian terkunci setelah sisanya habis.</p>
                                    @endif
                                    {{-- Tombol selalu terlihat dan baru nonaktif setelah tahapnya selesai. --}}
                                    @php $buttonState = 'disabled:opacity-50 disabled:cursor-not-allowed'; @endphp
                                    <div class="col-span-2 md:col-span-4 flex flex-wrap items-center justify-end gap-2 border-t border-ink-100 pt-3 mt-1">
                                        @if ($qtyEditable)
                                            <button name="action" value="{{ $stage->status === 'pending' ? 'start' : 'save' }}" data-daily-today class="btn-secondary text-xs mr-auto {{ $buttonState }}" title="Menyimpan pcs yang diselesaikan hari ini" @disabled($stageClosed)>Selesai Hari Ini</button>
                                        @endif
                                        @if ($stage->status === 'pending' && ! $stageClosed)
                                            <button name="action" value="start" class="btn-secondary text-xs">Mulai</button>
                                        @else
                                            <button name="action" value="save" class="btn-secondary text-xs {{ $buttonState }}" @disabled($stageClosed)>Simpan</button>
                                            <button name="action" value="finish" class="btn-primary text-xs {{ $buttonState }}" @disabled($stageClosed || $stage->status === 'completed')>Selesai</button>
                                        @endif
                                    </div>
                                </form>

                                @if ($stage->stage === 'design')
                                    @php $sketchLocked = $locked || $stage->status === 'completed'; @endphp
                                    <div class="mt-3 border-t border-ink-100 pt-3">
                                        <p class="label text-xs">Sketsa Desain @unless ($sketchLocked)<span class="text-ink-400 font-normal">— wajib minimal satu sebelum tahap ini diselesaikan</span>@endunless</p>
                                        @if ($production->designSketches->isNotEmpty())
                                            <div class="grid grid-cols-3 md:grid-cols-5 gap-2 mb-2">
                                                @foreach ($production->designSketches as $sketch)
                                                    <div class="border border-ink-100 rounded-lg overflow-hidden bg-white">
                                                        <a href="{{ asset('storage/'.$sketch->path) }}" target="_blank" rel="noopener" title="{{ $sketch->original_name }}">
                                                            <img src="{{ asset('storage/'.$sketch->path) }}" alt="{{ $sketch->original_name }}" class="w-full aspect-square object-cover">
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
                                            <form action="{{ route('admin.productions.sketches.store', $production->id) }}" method="POST" enctype="multipart/form-data" class="space-y-2">
                                                @csrf
                                                <input type="file" name="sketches[]" multiple accept="image/jpeg,image/png,image/webp" class="block text-xs">
                                                <p class="text-[11px] text-ink-400">JPG, PNG, atau WebP, maksimal 2 MB per file.</p>
                                                <div class="flex justify-end">
                                                    <button class="btn-secondary text-xs">Unggah Sketsa</button>
                                                </div>
                                            </form>
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
                                                <label class="label text-xs">Catatan Revisi (wajib jika sampel perlu diperbaiki)</label>
                                                <textarea name="notes" rows="2" class="input" placeholder="Contoh: panjang lengan kurang 2 cm">{{ old('notes') }}</textarea>
                                                @error('notes')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                                <div class="flex justify-end gap-2">
                                                    <button name="action" value="approve_sample" class="btn-primary text-xs">Sampel Disetujui</button>
                                                    <button name="action" value="revise_sample" class="btn-secondary text-xs">Perlu Revisi</button>
                                                </div>
                                            </form>
                                        @endif
                                    </div>
                                @endif

                                @if (empty($machines) && $hasQty)
                                    <p class="text-xs text-amber-600 mt-1">Belum ada mesin aktif untuk tahap ini. Tambahkan kategori mesin dengan tahap "{{ $stage->label() }}".</p>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach

        <x-ui.card title="Log Hari Ini" subtitle="{{ now('Asia/Jakarta')->locale('id')->translatedFormat('l, j F Y') }} (WIB)">
            @php $todayLogs = $production->todayLogSummary(); @endphp
            @if ($todayLogs === [])
                <p class="text-sm text-ink-400">Belum ada hasil produksi yang dicatat hari ini.</p>
            @else
                <table class="table-clean">
                    <thead><tr><th>Tahap</th><th class="text-right">Input</th><th class="text-right">Output</th><th class="text-right">Sisa</th></tr></thead>
                    <tbody>
                        @foreach ($todayLogs as $row)
                            <tr>
                                <td class="font-medium">{{ $row['stage'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['input'] }} pcs</td>
                                <td class="text-right tabular-nums">{{ $row['output'] }} pcs</td>
                                <td class="text-right tabular-nums">{{ $row['sisa'] }} pcs</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>

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
                <thead><tr><th>Bahan</th><th class="text-right">Total</th><th>Satuan</th></tr></thead>
                <tbody>
                    @if ($production->status === 'completed' && $consumed->isNotEmpty())
                        @foreach ($consumed as $c)
                            <tr>
                                <td>{{ $c->material?->name ?? '—' }}</td>
                                <td class="text-right tabular-nums">{{ \App\Models\Material::formatQty($c->qty_used) }}</td>
                                <td class="text-ink-500">{{ $c->material?->unit }}</td>
                            </tr>
                        @endforeach
                    @elseif ($bahan->isNotEmpty())
                        @foreach ($bahan as $b)
                            <tr>
                                <td>{{ $b['name'] }}</td>
                                <td class="text-right tabular-nums">{{ \App\Models\Material::formatQty($b['est']) }}</td>
                                <td class="text-ink-500">{{ $b['unit'] }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr><td colspan="3" class="text-ink-400 text-center">Produk ini belum memiliki resep bahan.</td></tr>
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
            <a href="{{ route('admin.productions.edit', $production->id) }}" class="btn-secondary w-full justify-center mb-2">Ubah Batch</a>
            @if (in_array($production->status, ['planned','cancelled'], true))
                <form action="{{ route('admin.productions.destroy', $production->id) }}" method="POST" onsubmit="return confirm('Hapus batch ini?')">
                    @csrf @method('DELETE')
                    <button class="btn-secondary w-full justify-center text-red-600">Hapus Batch</button>
                </form>
            @endif
        </x-ui.card>

        @unless (in_array($production->status, ['completed', 'cancelled'], true))
            <x-ui.card title="Pause Produksi" subtitle="Mencatat posisi produksi saat ini. Status batch dan tahap tidak berubah.">
                <form action="{{ route('admin.productions.pause', $production->id) }}" method="POST" class="space-y-2">
                    @csrf
                    <label for="keterangan" class="label text-xs">Keterangan <span class="text-ink-400 font-normal">(opsional)</span></label>
                    <textarea name="keterangan" id="keterangan" rows="2" maxlength="500" class="input" placeholder="Contoh: istirahat, mesin diperbaiki">{{ old('keterangan') }}</textarea>
                    <p class="text-[11px] text-ink-400">Jika dikosongkan, posisi tahap saat ini dicatat otomatis.</p>
                    @error('keterangan')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <button class="btn-secondary w-full justify-center">Pause</button>
                </form>
            </x-ui.card>
        @endunless

        <x-ui.card title="Log Produksi">
            @if ($production->logs->isEmpty())
                <p class="text-sm text-ink-400">Belum ada catatan pause.</p>
            @else
                <ul class="space-y-3">
                    @foreach ($production->logs as $log)
                        <li class="border-l-2 border-ink-200 pl-3">
                            <p class="text-xs text-ink-500">{{ $log->waktu_label }}</p>
                            @if ($log->output_qty !== null)
                                <p class="text-sm font-medium text-ink-800">{{ $log->stage?->label() ?? 'Tahap dihapus' }}</p>
                                <p class="text-xs text-ink-700 tabular-nums">Input {{ $log->input_qty }} · Output {{ $log->output_qty }} · Sisa {{ $log->sisa_qty }}</p>
                            @endif
                            @if (filled($log->keterangan))
                                <p class="text-sm text-ink-800 break-words">{{ $log->keterangan }}</p>
                            @endif
                            <p class="text-xs text-ink-400">{{ $log->user?->name ?? '—' }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</div>

@if ($production->hasSamplePhase())
    @php
        $sampleQc = $production->stages->first(fn ($s) => $s->phase === 'sample' && $s->stage === 'qc_packing');
        $sampleStatus = match (true) {
            $production->sampleSource !== null => ['text-emerald-700', 'Menggunakan sampel '.$production->sampleSource->code],
            $production->sampleApproved() => ['text-emerald-700', 'Disetujui '.$production->sample_approved_at->translatedFormat('d M Y')],
            $sampleQc?->status === 'completed' => ['text-amber-600', 'Sudah selesai, menunggu pemeriksaan'],
            $production->stages->contains(fn ($s) => $s->phase === 'sample' && $s->status !== 'pending') => ['text-ink-700', 'Sedang dibuat'],
            default => ['text-ink-500', 'Belum dibuat'],
        };
        $canReuse = ! $locked && $existingSample !== null && $production->canReuseSample();
    @endphp
    <x-ui.modal id="modal-sampel" title="Cek Sampel — {{ $production->product?->name ?? $production->code }}" size="xl">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-4">
            <div><p class="text-ink-500 text-xs">Batch</p><p class="font-medium">{{ $production->code }}</p></div>
            <div><p class="text-ink-500 text-xs">Ukuran</p><p class="font-medium">{{ $production->makesOwnSample() ? ($production->sample_size ?? '—') : '—' }}</p></div>
            <div><p class="text-ink-500 text-xs">Status</p><p class="font-medium {{ $sampleStatus[0] }}">{{ $sampleStatus[1] }}</p></div>
            <div><p class="text-ink-500 text-xs">Revisi</p><p class="font-medium tabular-nums">{{ $production->sample_revision_count }} kali</p></div>
        </div>

        @if ($existingSample)
            <div class="rounded-lg border px-4 py-3 mb-4 {{ $canReuse ? 'border-emerald-200 bg-emerald-50' : 'border-ink-100 bg-ink-50' }}">
                <p class="text-sm text-ink-800">
                    Produk ini sudah memiliki sampel dari batch <span class="font-medium">{{ $existingSample->code }}</span>
                    @if ($existingSample->sample_approved_at)
                        (disetujui {{ $existingSample->sample_approved_at->translatedFormat('d M Y') }}).
                    @else
                        (batch selesai).
                    @endif
                </p>
                @if ($canReuse)
                    <p class="text-xs text-ink-500 mt-1">Tidak perlu membuat sampel lagi — Persiapan dan tahap sampel batch ini dilewati, langsung ke produksi massal.</p>
                    <form action="{{ route('admin.productions.reuse-sample', $production->id) }}" method="POST" class="mt-2">
                        @csrf
                        <button class="btn-primary text-xs">Gunakan Sampel Ini, Langsung Produksi Massal</button>
                    </form>
                @elseif (! $production->sampleSource && ! $production->sampleApproved())
                    <p class="text-xs text-ink-500 mt-1">Sampel batch ini sudah mulai dibuat, sehingga proses dilanjutkan di Tahap Sampel.</p>
                @endif
            </div>

            <p class="label text-xs">Foto Desain {{ $existingSample->code }}</p>
            @include('admin.productions.partials.sketch-grid', ['sketches' => $existingSample->designSketches])
        @elseif (! $production->sampleApproved())
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 mb-4">
                <p class="text-sm text-ink-800">Produk ini belum memiliki sampel. Buat sampel terlebih dahulu (1 pcs) sebelum produksi massal.</p>
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

@unless ($locked)
    <x-ui.modal id="modal-konfirmasi-harian" title="Konfirmasi Hasil Harian">
        <p id="konfirmasi-harian-teks" class="text-sm text-ink-800"></p>
        <div class="flex justify-end gap-2 mt-4">
            <button type="button" id="konfirmasi-harian-batal" class="btn btn-secondary">Batal</button>
            <button type="button" id="konfirmasi-harian-ya" class="btn btn-primary">Ya</button>
        </div>
    </x-ui.modal>
@endunless
@endsection

@push('scripts')
<script>
$(function () {
    const MODAL = 'modal-konfirmasi-harian';
    let pending = null;

    // Event submit baru muncul setelah validasi min/max browser lolos, dan menekan
    // Enter di isian pcs memakai tombol submit pertama (Simpan) sebagai submitter.
    $(document).on('submit', 'form[data-daily-form]', function (e) {
        const form = $(this);
        form.find('input.js-daily-action').remove();

        let total = 0;
        form.find('[data-daily-qty]:enabled').each(function () {
            total += parseInt(this.value, 10) || 0;
        });
        if (total <= 0) {
            return;
        }

        e.preventDefault();
        pending = { form: this, submitter: e.originalEvent ? e.originalEvent.submitter : null };
        $('#konfirmasi-harian-teks').text('Yakin menyelesaikan ' + total + ' pcs pada tahap ' + form.data('stage-label') + ' hari ini?');
        openModal(MODAL);
        // Fokus di Batal: Enter yang tertahan dari isian tidak langsung menyimpan.
        $('#konfirmasi-harian-batal').trigger('focus');
    });

    $('#konfirmasi-harian-batal').on('click', function () {
        closeModal(MODAL);
    });

    $('#konfirmasi-harian-ya').on('click', function () {
        if (pending === null) {
            return;
        }
        const { form, submitter } = pending;
        pending = null;
        closeModal(MODAL);

        // form.submit() tidak mengirim nilai tombol, jadi aksinya dibawa input tersembunyi.
        $('<input>', {
            type: 'hidden',
            class: 'js-daily-action',
            name: 'action',
            value: submitter && submitter.name === 'action' ? submitter.value : 'save',
        }).appendTo(form);
        form.submit();
    });
});
</script>
@endpush
