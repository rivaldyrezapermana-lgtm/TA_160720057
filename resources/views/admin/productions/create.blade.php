@extends('layouts.admin')
@section('title', 'Batch Produksi Baru')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Produksi', 'url' => route('admin.productions.index')], ['label' => 'Baru']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Buat Batch Produksi</h1>
@endsection

@section('content')
<form action="{{ route('admin.productions.store') }}" method="POST" class="max-w-4xl space-y-4">
    @csrf
    <x-ui.card title="Informasi Produksi">
        <div class="grid md:grid-cols-3 gap-4">
            <x-ui.select name="product_id" label="Produk" required :options="$products->pluck('name','id')->toArray()" />
            <x-ui.input name="planned_qty" type="number" label="Target Qty" required />
            <x-ui.input name="code" label="Kode Batch" placeholder="auto" />
            <x-ui.input name="start_date" type="date" label="Tanggal Mulai" required />
            <x-ui.input name="end_date" type="date" label="Estimasi Selesai" />
        </div>
    </x-ui.card>

    <x-ui.card title="Bahan yang Digunakan">
        <table class="table-clean">
            <thead><tr><th>Bahan</th><th class="text-right">Qty Dibutuhkan</th><th>Unit</th></tr></thead>
            <tbody>
                @foreach ($materials as $m)
                    <tr>
                        <td>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="materials[{{ $m->id }}][use]" value="1" class="rounded">
                                <span>{{ $m->name }}</span>
                            </label>
                        </td>
                        <td><input type="number" name="materials[{{ $m->id }}][qty]" class="input text-right" placeholder="0"></td>
                        <td class="text-ink-500">{{ $m->unit }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>

    <x-ui.card>
        <x-ui.textarea name="notes" label="Catatan" />
    </x-ui.card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.productions.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Mulai Produksi</button>
    </div>
</form>
@endsection
