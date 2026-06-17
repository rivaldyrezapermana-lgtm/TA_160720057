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
            <x-ui.input name="planned_qty" type="number" label="Target Qty" required :value="$production->planned_qty" />
            <x-ui.input name="actual_qty" type="number" label="Aktual Qty" :value="$production->actual_qty" />
            <x-ui.select name="status" label="Status" required :options="collect(\App\Models\Production::STATUSES)->mapWithKeys(fn ($s) => [$s => $s])->toArray()" :selected="$production->status" />
            <x-ui.input name="start_date" type="date" label="Tanggal Mulai" required :value="$production->start_date?->toDateString()" />
            <x-ui.input name="end_date" type="date" label="Estimasi Selesai" :value="$production->end_date?->toDateString()" />
        </div>
    </x-ui.card>

    <x-ui.card>
        <x-ui.textarea name="notes" label="Catatan" :value="$production->notes" />
    </x-ui.card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.productions.show', $production->id) }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan Perubahan</button>
    </div>
</form>
@endsection
