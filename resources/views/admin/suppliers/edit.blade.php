@extends('layouts.admin')
@section('title', 'Edit Supplier')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Supplier', 'url' => route('admin.suppliers.index')], ['label' => $supplier->name]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Edit: {{ $supplier->name }}</h1>
@endsection

@section('content')
<form action="{{ route('admin.suppliers.update', $supplier->id) }}" method="POST" class="max-w-2xl">
    @csrf @method('PUT')
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama" :value="$supplier->name" required /></div>
            <x-ui.input name="contact_person" label="Contact" :value="$supplier->contact_person" />
            <x-ui.input name="phone" label="Telepon" :value="$supplier->phone" />
            <x-ui.input name="email" type="email" label="Email" :value="$supplier->email" />
            <div></div>
            <div class="md:col-span-2"><x-ui.textarea name="address" label="Alamat" :value="$supplier->address" /></div>
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.suppliers.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
