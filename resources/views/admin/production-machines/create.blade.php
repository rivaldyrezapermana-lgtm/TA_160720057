@extends('layouts.admin')
@section('title', 'Tambah Alat Produksi')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Alat Produksi', 'url' => route('admin.production-machines.index')], ['label' => 'Tambah']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Tambah Alat Produksi</h1>
@endsection

@section('content')
<form action="{{ route('admin.production-machines.store') }}" method="POST" class="max-w-2xl">
    @csrf
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama Alat" required /></div>
            <div class="field">
                <label class="label">ID Alat</label>
                <input type="text" value="{{ $nextKode }}" class="input bg-ink-50" readonly disabled>
                <p class="field-help">Dibuat otomatis.</p>
            </div>
            <x-ui.select name="status" label="Status" required :options="['active' => 'Aktif', 'maintenance' => 'Perawatan', 'inactive' => 'Nonaktif']" selected="active" />
            <x-ui.select name="machine_category_id" label="Kategori" :options="$categories" />
            <x-ui.input name="capacity" type="number" label="Kapasitas (pcs/hari)" />
            <div class="md:col-span-2"><x-ui.textarea name="notes" label="Catatan" /></div>
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.production-machines.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
