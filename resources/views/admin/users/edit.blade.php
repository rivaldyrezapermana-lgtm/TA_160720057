@extends('layouts.admin')
@section('title', 'Edit User')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'User', 'url' => route('admin.users.index')], ['label' => $user->name]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Edit: {{ $user->name }}</h1>
@endsection

@section('content')
<form action="{{ route('admin.users.update', $user->id) }}" method="POST" class="max-w-2xl">
    @csrf @method('PUT')
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama" :value="$user->name" required /></div>
            <x-ui.input name="email" type="email" label="Email" :value="$user->email" required />
            <x-ui.select name="role" label="Role" :selected="$user->role" required :options="['admin'=>'Admin','karyawan'=>'Karyawan','pembeli'=>'Pembeli']" />
            <x-ui.input name="phone" label="No. HP" :value="$user->phone" />
            <x-ui.input name="password" type="password" label="Password (kosongkan jika tidak diubah)" />
            <div class="md:col-span-2"><x-ui.textarea name="address" label="Alamat" :value="$user->address" /></div>
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.users.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
