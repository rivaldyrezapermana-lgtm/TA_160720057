@extends('layouts.admin')
@section('title', 'Tambah Kategori')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Kategori', 'url' => route('admin.categories.index')], ['label' => 'Tambah']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Tambah Kategori</h1>
@endsection

@section('content')
<form action="{{ route('admin.categories.store') }}" method="POST" class="max-w-xl">
    @csrf
    <x-ui.card>
        <div class="space-y-4">
            <x-ui.input name="name" label="Nama Kategori" required />
            <x-ui.textarea name="description" label="Deskripsi" />
        </div>
    </x-ui.card>

    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.categories.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
