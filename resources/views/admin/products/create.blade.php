@extends('layouts.admin')
@section('title', 'Tambah Produk')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Produk', 'url' => route('admin.products.index')], ['label' => 'Tambah']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Tambah Produk</h1>
@endsection

@section('content')
<form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data" class="max-w-3xl space-y-4">
    @csrf
    <x-ui.card title="Informasi Produk">
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama Produk" required /></div>
            <x-ui.input name="sku" label="SKU" required />
            <x-ui.select name="category_id" label="Kategori" required :options="$categories->pluck('name','id')->toArray()" />
            <x-ui.input name="price" type="number" label="Harga (Rp)" required />
            <x-ui.input name="stock" type="number" label="Stok Awal" />
            <div class="md:col-span-2"><x-ui.textarea name="description" label="Deskripsi" rows="4" /></div>
            <div class="md:col-span-2">
                <label class="label">Foto Produk</label>
                <input type="file" name="image" accept="image/*" class="input">
            </div>
            <div class="md:col-span-2">
                <label class="flex items-center gap-2 text-sm text-ink-700">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-ink-300">
                    Tampilkan di storefront
                </label>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Ukuran" subtitle="Tambah varian ukuran (S/M/L/XL) dengan stok masing-masing">
        <div id="sizes-wrapper" class="space-y-2">
            <div class="grid grid-cols-12 gap-2 text-xs uppercase tracking-wider text-ink-500 font-semibold">
                <div class="col-span-2">Size</div>
                <div class="col-span-2">Lingkar Dada</div>
                <div class="col-span-2">Panjang</div>
                <div class="col-span-2">Lengan</div>
                <div class="col-span-3">Stok</div>
                <div class="col-span-1"></div>
            </div>
            @foreach (['S','M','L','XL'] as $sz)
                <div class="grid grid-cols-12 gap-2">
                    <input type="text" name="sizes[{{ $sz }}][size]" value="{{ $sz }}" class="input col-span-2" readonly>
                    <input type="number" name="sizes[{{ $sz }}][chest_cm]" placeholder="cm" class="input col-span-2">
                    <input type="number" name="sizes[{{ $sz }}][length_cm]" placeholder="cm" class="input col-span-2">
                    <input type="number" name="sizes[{{ $sz }}][sleeve_cm]" placeholder="cm" class="input col-span-2">
                    <input type="number" name="sizes[{{ $sz }}][stock]" placeholder="0" class="input col-span-3">
                    <div class="col-span-1"></div>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card title="Bahan Baku (Resep)" subtitle="Centang bahan yang dibutuhkan untuk membuat 1 unit produk ini">
        <table class="table-clean">
            <thead><tr><th>Bahan</th><th class="text-right">Qty per Unit</th><th>Unit</th></tr></thead>
            <tbody>
                @foreach ($materials as $m)
                    <tr>
                        <td>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="materials[{{ $m->id }}][use]" value="1" @checked(old('materials.'.$m->id.'.use')) class="rounded">
                                <span>{{ $m->name }}</span>
                            </label>
                        </td>
                        <td><input type="number" min="1" name="materials[{{ $m->id }}][qty_required]" value="{{ old('materials.'.$m->id.'.qty_required') }}" class="input text-right" placeholder="0"></td>
                        <td class="text-ink-500">{{ $m->unit }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.products.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan Produk</button>
    </div>
</form>
@endsection
