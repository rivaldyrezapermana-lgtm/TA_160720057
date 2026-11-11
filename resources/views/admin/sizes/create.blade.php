@extends('layouts.admin')
@section('title', 'Tambah Ukuran')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Ukuran', 'url' => route('admin.sizes.index')], ['label' => 'Tambah']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Tambah Ukuran</h1>
@endsection

@section('content')
<form action="{{ route('admin.sizes.store') }}" method="POST" class="max-w-2xl">
    @csrf
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <x-ui.input name="name" label="Nama Ukuran" maxlength="10" required help="Ukuran baru ditempatkan di urutan terakhir." />
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.sizes.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
