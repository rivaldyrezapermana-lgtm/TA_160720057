@extends('layouts.admin')
@section('title', 'Tambah Bahan Baku')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Bahan Baku', 'url' => route('admin.materials.index')], ['label' => 'Tambah']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Tambah Bahan Baku</h1>
@endsection

@section('content')
<form action="{{ route('admin.materials.store') }}" method="POST" class="max-w-2xl">
    @csrf
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama Bahan" required /></div>
            <x-ui.input name="code" label="Kode" required />
            <x-ui.input name="unit" label="Satuan" placeholder="meter, roll, pcs..." required />
            <x-ui.input name="stock" type="number" label="Stok Awal" required />
            <x-ui.input name="min_stock" type="number" label="Minimum Stok" required help="Untuk alert stok rendah" />
            <x-ui.input name="unit_cost" type="number" label="Harga per Unit (Rp)" required />
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.materials.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
