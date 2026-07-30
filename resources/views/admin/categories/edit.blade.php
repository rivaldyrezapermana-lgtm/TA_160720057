@extends('layouts.admin')
@section('title', 'Edit Kategori')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Kategori', 'url' => route('admin.categories.index')], ['label' => 'Edit']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Edit Kategori</h1>
@endsection

@section('content')
<form action="{{ route('admin.categories.update', $category->id) }}" method="POST" class="max-w-xl">
    @csrf @method('PUT')
    <x-ui.card>
        <div class="space-y-4">
            <x-ui.input name="name" label="Nama Kategori" :value="$category->name" required />
            <x-ui.textarea name="description" label="Deskripsi" :value="$category->description" />
        </div>
    </x-ui.card>

    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.categories.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan Perubahan</button>
    </div>
</form>
@endsection
