@extends('layouts.admin')
@section('title', 'Pemasok')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Pemasok']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Pemasok</h1>
@endsection

@section('content')
<div class="flex justify-end mb-4">
    <a href="{{ route('admin.suppliers.create') }}" class="btn-primary">+ Tambah Pemasok</a>
</div>
<x-ui.card>
    <table class="table-clean">
        <thead><tr><th>Nama</th><th>Narahubung</th><th>Telepon</th><th>Email</th><th>Alamat</th><th class="text-right">Aksi</th></tr></thead>
        <tbody>
            @foreach ($suppliers as $s)
                <tr>
                    <td class="font-medium">{{ $s->name }}</td>
                    <td>{{ $s->contact_person }}</td>
                    <td>{{ $s->phone }}</td>
                    <td class="text-ink-500">{{ $s->email }}</td>
                    <td class="text-ink-500">{{ $s->address }}</td>
                    <td class="text-right">
                        <a href="{{ route('admin.suppliers.edit', $s->id) }}" class="text-ink-600 hover:text-ink-900 text-sm">Ubah</a>
                        <form action="{{ route('admin.suppliers.destroy', $s->id) }}" method="POST" class="inline" onsubmit="return confirm('Hapus pemasok ini?')">
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
