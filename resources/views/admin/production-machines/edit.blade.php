@extends('layouts.admin')
@section('title', 'Edit Mesin')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Mesin Produksi', 'url' => route('admin.production-machines.index')], ['label' => $machine->name]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Edit: {{ $machine->name }}</h1>
@endsection

@section('content')
<form action="{{ route('admin.production-machines.update', $machine->id) }}" method="POST" class="max-w-2xl">
    @csrf @method('PUT')
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama Mesin" :value="$machine->name" required /></div>
            <x-ui.input name="code" label="Kode" :value="$machine->code" required />
            <x-ui.select name="status" label="Status" required :options="['active' => 'Aktif', 'maintenance' => 'Perawatan', 'inactive' => 'Nonaktif']" :selected="$machine->status" />
            <x-ui.input name="capacity" type="number" label="Kapasitas (pcs/hari)" :value="$machine->capacity" />
            <div class="md:col-span-2"><x-ui.textarea name="notes" label="Catatan" :value="$machine->notes" /></div>
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.production-machines.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
