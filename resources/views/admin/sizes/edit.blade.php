@extends('layouts.admin')
@section('title', 'Ubah Ukuran')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Ukuran', 'url' => route('admin.sizes.index')], ['label' => $size->name]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Ubah: {{ $size->name }}</h1>
@endsection

@section('content')
<form action="{{ route('admin.sizes.update', $size->id) }}" method="POST" class="max-w-2xl">
    @csrf @method('PUT')
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <x-ui.input name="name" label="Nama Ukuran" :value="$size->name" maxlength="10" required
                :readonly="$used" :class="$used ? 'bg-ink-50' : ''"
                :help="$used ? 'Nama tidak dapat diubah karena sudah digunakan pada produk, produksi, atau pesanan.' : null" />
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.sizes.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan Perubahan</button>
    </div>
</form>
@endsection
