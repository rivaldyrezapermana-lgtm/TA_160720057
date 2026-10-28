@extends('layouts.admin')
@section('title', 'Edit '.$production->code)
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Produksi', 'url' => route('admin.productions.index')], ['label' => $production->code, 'url' => route('admin.productions.show', $production->id)], ['label' => 'Edit']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Edit {{ $production->code }}</h1>
@endsection

@section('content')
<form action="{{ route('admin.productions.update', $production->id) }}" method="POST" class="max-w-4xl space-y-4">
    @csrf @method('PUT')
    <x-ui.card title="Informasi Produksi">
        <div class="grid md:grid-cols-3 gap-4">
            <x-ui.select name="production_machine_id" label="Mesin Produksi" :options="$machines->pluck('name','id')->toArray()" :selected="$production->production_machine_id" placeholder="— Tanpa mesin —" />
            @if ($production->sizes->isEmpty())
                <x-ui.input name="planned_qty" type="number" label="Target Qty" required :value="$production->planned_qty" />
            @endif
            <x-ui.input name="actual_qty" type="number" label="Aktual Qty" :value="$production->actual_qty" />
            <x-ui.select name="status" label="Status" required :options="collect(\App\Models\Production::STATUSES)->mapWithKeys(fn ($s) => [$s => $s])->toArray()" :selected="$production->status" />
            <x-ui.input name="start_date" type="date" label="Tanggal Mulai" required :value="$production->start_date?->toDateString()" />
            <x-ui.input name="end_date" type="date" label="Estimasi Selesai" :value="$production->end_date?->toDateString()" />
        </div>
    </x-ui.card>

    @if ($production->sizes->isNotEmpty())
        @php
            $planned = $production->sizes->pluck('planned_qty', 'size');
            $sizeNames = $production->product->sizes->sortBy('id')->pluck('size')
                ->merge($planned->keys())->unique()->values();
        @endphp
        <x-ui.card title="Target per Ukuran" subtitle="Target batch dihitung ulang dari jumlah semua ukuran ({{ $production->planned_qty }} pcs saat ini)">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach ($sizeNames as $size)
                    <div class="field !mb-0">
                        <label class="label">Ukuran {{ $size }}</label>
                        <input type="number" min="0" name="sizes[{{ $size }}]" value="{{ old('sizes.'.$size, $planned[$size] ?? '') }}" class="input">
                    </div>
                @endforeach
            </div>
            @error('sizes')<p class="field-error mt-2">{{ $message }}</p>@enderror
            @error('planned_qty')<p class="field-error mt-2">{{ $message }}</p>@enderror
            @if ($production->hasSamplePhase())
                <div class="mt-4 pt-4 border-t border-ink-100 max-w-xs">
                    <x-ui.select name="sample_size" label="Ukuran Sampel" :selected="$production->sample_size"
                        :options="$planned->keys()->mapWithKeys(fn ($s) => [$s => $s])->toArray()" placeholder="— Ukuran terbanyak —" />
                </div>
            @endif
        </x-ui.card>
    @endif

    <x-ui.card>
        <x-ui.textarea name="notes" label="Catatan" :value="$production->notes" />
    </x-ui.card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.productions.show', $production->id) }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan Perubahan</button>
    </div>
</form>
@endsection
