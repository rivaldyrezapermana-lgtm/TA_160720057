@extends('layouts.admin')
@section('title', 'Edit Bahan')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Bahan Baku', 'url' => route('admin.materials.index')], ['label' => $material->name]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Edit: {{ $material->name }}</h1>
@endsection

@section('content')
<form action="{{ route('admin.materials.update', $material->id) }}" method="POST" class="max-w-2xl">
    @csrf @method('PUT')
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama Bahan" :value="$material->name" required /></div>
            <x-ui.input name="code" label="Kode" :value="$material->code" required />
            <x-ui.input name="unit" label="Satuan" :value="$material->unit" required />
            <div class="field">
                <label class="label">Stok</label>
                <input type="number" value="{{ $material->stock }}" class="input bg-ink-50" readonly disabled>
                <p class="field-help">Stok hanya berubah lewat pembelian &amp; produksi.</p>
            </div>
            <x-ui.input name="min_stock" type="number" label="Min Stok" :value="$material->min_stock" required />
            <x-ui.input name="unit_cost" type="number" label="Harga/Unit" :value="$material->unit_cost" required />
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.materials.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
