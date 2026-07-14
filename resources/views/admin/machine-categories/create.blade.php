@extends('layouts.admin')
@section('title', 'Tambah Kategori Mesin')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Kategori Mesin', 'url' => route('admin.machine-categories.index')], ['label' => 'Tambah']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Tambah Kategori Mesin</h1>
@endsection

@section('content')
@php $stageLabels = ['design'=>'Desain','sample'=>'Sample','cutting'=>'Cutting','sewing'=>'Sewing','qc'=>'Quality Check','packing'=>'Packing']; @endphp
<form action="{{ route('admin.machine-categories.store') }}" method="POST" class="max-w-2xl">
    @csrf
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama Kategori" required /></div>
            <x-ui.input name="code" label="Kode" required />
            <x-ui.select name="stage" label="Tahap Produksi" :options="collect($stages)->mapWithKeys(fn($s) => [$s => $stageLabels[$s]])->toArray()" />
            <div class="md:col-span-2"><x-ui.textarea name="notes" label="Catatan" /></div>
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.machine-categories.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
