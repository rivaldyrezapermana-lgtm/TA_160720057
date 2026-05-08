@extends('layouts.admin')
@section('title', $product->name)
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Produk', 'url' => route('admin.products.index')], ['label' => $product->name]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">{{ $product->name }}</h1>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <x-ui.card title="Detail Produk">
            <div class="grid md:grid-cols-2 gap-4 text-sm">
                <div><p class="text-ink-500">SKU</p><p class="font-medium text-ink-900">{{ $product->sku }}</p></div>
                <div><p class="text-ink-500">Kategori</p><p class="font-medium text-ink-900">{{ $product->category }}</p></div>
                <div><p class="text-ink-500">Harga</p><p class="font-medium text-ink-900">Rp {{ number_format($product->price, 0, ',', '.') }}</p></div>
                <div><p class="text-ink-500">Total Stok</p><p class="font-medium text-ink-900">{{ $product->stock }} pcs</p></div>
                <div class="md:col-span-2"><p class="text-ink-500">Deskripsi</p><p class="text-ink-800 mt-1">{{ $product->description }}</p></div>
            </div>
        </x-ui.card>

        <x-ui.card title="Varian Ukuran">
            <table class="table-clean">
                <thead><tr><th>Size</th><th class="text-right">Lingkar Dada</th><th class="text-right">Panjang</th><th class="text-right">Lengan</th><th class="text-right">Stok</th></tr></thead>
                <tbody>
                    @foreach ($product->sizes as $s)
                        <tr><td class="font-medium">{{ $s['size'] }}</td><td class="text-right">{{ $s['chest'] }} cm</td><td class="text-right">{{ $s['length'] }} cm</td><td class="text-right">{{ $s['sleeve'] }} cm</td><td class="text-right tabular-nums">{{ $s['stock'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    </div>

    <div class="space-y-6">
        <x-ui.card title="Status">
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-ink-500">Status</span><x-ui.status-badge :status="$product->is_active ? 'active' : 'inactive'" /></div>
            </div>
            <div class="mt-4 space-y-2">
                <a href="{{ route('admin.products.edit', $product->id) }}" class="btn-secondary w-full justify-center">Edit Produk</a>
                <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('Hapus produk ini?')">
                    @csrf @method('DELETE')
                    <button class="btn-danger w-full justify-center">Hapus Produk</button>
                </form>
            </div>
        </x-ui.card>
    </div>
</div>
@endsection
