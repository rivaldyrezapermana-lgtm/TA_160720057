@extends('layouts.admin')
@section('title', 'Edit Produk')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Produk', 'url' => route('admin.products.index')], ['label' => $product->name]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Edit: {{ $product->name }}</h1>
@endsection

@section('content')
<form action="{{ route('admin.products.update', $product->id) }}" method="POST" enctype="multipart/form-data" class="max-w-3xl space-y-4">
    @csrf @method('PUT')
    <x-ui.card title="Informasi Produk">
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama" :value="$product->name" required /></div>
            <x-ui.input name="sku" label="SKU" :value="$product->sku" required />
            <x-ui.select name="category_id" label="Kategori" required :selected="$product->category_id" :options="$categories->pluck('name','id')->toArray()" />
            <x-ui.input name="price" type="number" label="Harga (Rp)" :value="$product->price" required />
            <x-ui.input name="stock" type="number" label="Stok" :value="$product->stock" />
            <div class="md:col-span-2"><x-ui.textarea name="description" label="Deskripsi" :value="$product->description" rows="4" /></div>
            <div class="md:col-span-2">
                <label class="flex items-center gap-2 text-sm text-ink-700">
                    <input type="checkbox" name="is_active" value="1" @checked($product->is_active) class="rounded border-ink-300">
                    Tampilkan di storefront
                </label>
            </div>
        </div>
    </x-ui.card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.products.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan Perubahan</button>
    </div>
</form>
@endsection
