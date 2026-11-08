@extends('layouts.admin')
@section('title', 'Tambah Pengguna')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Pengguna', 'url' => route('admin.users.index')], ['label' => 'Tambah']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Tambah Pengguna</h1>
@endsection

@section('content')
<form action="{{ route('admin.users.store') }}" method="POST" class="max-w-2xl">
    @csrf
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama" required /></div>
            <x-ui.input name="email" type="email" label="Email" required />
            <x-ui.select name="role" label="Peran" required :options="['admin'=>'Admin','karyawan'=>'Karyawan','pembeli'=>'Pembeli']" />
            <x-ui.input name="phone" label="Telepon" />
            <x-ui.input name="password" type="password" label="Kata Sandi" required />
            <div class="md:col-span-2"><x-ui.textarea name="address" label="Alamat" /></div>
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.users.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
