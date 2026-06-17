@extends('layouts.admin')
@section('title', 'Tambah Mesin Produksi')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Mesin Produksi', 'url' => route('admin.production-machines.index')], ['label' => 'Tambah']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Tambah Mesin Produksi</h1>
@endsection

@section('content')
<form action="{{ route('admin.production-machines.store') }}" method="POST" class="max-w-2xl">
    @csrf
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama Mesin" required /></div>
            <x-ui.input name="code" label="Kode" required />
            <x-ui.select name="status" label="Status" required :options="['active' => 'Aktif', 'maintenance' => 'Perawatan', 'inactive' => 'Nonaktif']" selected="active" />
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
