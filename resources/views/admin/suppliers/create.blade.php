@extends('layouts.admin')
@section('title', 'Tambah Supplier')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Supplier', 'url' => route('admin.suppliers.index')], ['label' => 'Tambah']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Tambah Supplier</h1>
@endsection

@section('content')
<form action="{{ route('admin.suppliers.store') }}" method="POST" class="max-w-2xl">
    @csrf
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama Supplier" required /></div>
            <x-ui.input name="contact_person" label="Contact Person" />
            <x-ui.input name="phone" label="Telepon" />
            <x-ui.input name="email" type="email" label="Email" />
            <div></div>
            <div class="md:col-span-2"><x-ui.textarea name="address" label="Alamat" /></div>
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.suppliers.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
