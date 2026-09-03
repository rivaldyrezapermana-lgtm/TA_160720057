@extends('layouts.admin')
@section('title', 'Edit Kategori Mesin')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Kategori Mesin', 'url' => route('admin.machine-categories.index')], ['label' => $category->name]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Edit: {{ $category->name }}</h1>
@endsection

@section('content')
@php $stageLabels = collect(\App\Models\ProductionStage::STAGES)->mapWithKeys(fn ($s) => [$s => \App\Models\ProductionStage::stageLabel($s)])->toArray(); @endphp
<form action="{{ route('admin.machine-categories.update', $category->id) }}" method="POST" class="max-w-2xl">
    @csrf @method('PUT')
    <x-ui.card>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="md:col-span-2"><x-ui.input name="name" label="Nama Kategori" :value="$category->name" required /></div>
            <x-ui.input name="code" label="Kode" :value="$category->code" required />
            <x-ui.select name="stage" label="Tahap Produksi" :selected="$category->stage" :options="collect($stages)->mapWithKeys(fn($s) => [$s => $stageLabels[$s]])->toArray()" />
            <div class="md:col-span-2"><x-ui.textarea name="notes" label="Catatan" :value="$category->notes" /></div>
        </div>
    </x-ui.card>
    <div class="flex justify-end gap-3 mt-4">
        <a href="{{ route('admin.machine-categories.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
