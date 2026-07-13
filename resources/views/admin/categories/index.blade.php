@extends('layouts.admin')
@section('title', 'Kategori')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Kategori']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Kategori</h1>
@endsection

@section('content')
<div class="flex justify-end mb-4">
    <a href="{{ route('admin.categories.create') }}" class="btn-primary">+ Tambah Kategori</a>
</div>

<x-ui.card>
    <table class="table-clean">
        <thead>
            <tr>
                <th>id</th>
                <th>Nama</th>
                <th>Slug</th>
                <th>Deskripsi</th>
                <th class="text-right">Jumlah Produk</th>
                <th class="text-right">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($categories as $c)
                <tr>
                    <td class="font-medium">{{ $c->id}}</td>
                    <td class="font-medium">{{ $c->name }}</td>
                    <td class="text-ink-500">{{ $c->slug }}</td>
                    <td class="text-ink-600">{{ $c->description }}</td>
                    <td class="text-right tabular-nums">{{ $c->products_count }}</td>
                    <td class="text-right">
                        <a href="{{ route('admin.categories.edit', $c->id) }}" class="text-ink-600 hover:text-ink-900 text-sm">Edit</a>
                        <form action="{{ route('admin.categories.destroy', $c->id) }}" method="POST" class="inline" onsubmit="return confirm('Hapus kategori ini?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:text-red-700 text-sm ml-3">Hapus</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-ui.card>
@endsection
