@extends('layouts.admin')
@section('title', 'User')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'User']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Manajemen User</h1>
@endsection

@section('content')
<div class="flex justify-end mb-4">
    <a href="{{ route('admin.users.create') }}" class="btn-primary">+ Tambah User</a>
</div>
<x-ui.card>
    <table class="table-clean">
        <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Telepon</th><th class="text-right">Aksi</th></tr></thead>
        <tbody>
            @foreach ($users as $u)
                <tr>
                    <td class="font-medium">{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td>
                        @php $tone = ['admin' => 'dark', 'karyawan' => 'blue', 'pembeli' => 'gray'][$u->role] ?? 'gray'; @endphp
                        <x-ui.badge :tone="$tone">{{ ucfirst($u->role) }}</x-ui.badge>
                    </td>
                    <td>{{ $u->phone }}</td>
                    <td class="text-right">
                        <a href="{{ route('admin.users.edit', $u->id) }}" class="text-ink-600 hover:text-ink-900 text-sm">Edit</a>
                        <form action="{{ route('admin.users.destroy', $u->id) }}" method="POST" class="inline" onsubmit="return confirm('Hapus user?')">
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
