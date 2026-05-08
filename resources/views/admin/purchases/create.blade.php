@extends('layouts.admin')
@section('title', 'PO Baru')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Pembelian', 'url' => route('admin.purchases.index')], ['label' => 'PO Baru']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Buat PO Baru</h1>
@endsection

@section('content')
<form action="{{ route('admin.purchases.store') }}" method="POST" class="max-w-4xl space-y-4">
    @csrf
    <x-ui.card title="Informasi PO">
        <div class="grid md:grid-cols-3 gap-4">
            <x-ui.select name="supplier_id" label="Supplier" required :options="$suppliers->pluck('name','id')->toArray()" />
            <x-ui.input name="purchase_date" type="date" label="Tanggal" required />
            <x-ui.input name="code" label="Kode PO" placeholder="auto" />
        </div>
    </x-ui.card>

    <x-ui.card title="Item Pembelian" subtitle="Daftar bahan yang dibeli">
        <table class="table-clean">
            <thead><tr><th>Bahan</th><th class="text-right">Qty</th><th>Unit</th><th class="text-right">Harga/Unit</th><th class="text-right">Subtotal</th></tr></thead>
            <tbody>
                @foreach ($materials as $m)
                    <tr>
                        <td>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="items[{{ $m->id }}][selected]" value="1" class="rounded">
                                <span>{{ $m->name }}</span>
                            </label>
                        </td>
                        <td><input type="number" name="items[{{ $m->id }}][qty]" class="input text-right" placeholder="0"></td>
                        <td class="text-ink-500">{{ $m->unit }}</td>
                        <td class="text-right tabular-nums">Rp {{ number_format($m->unit_cost, 0, ',', '.') }}</td>
                        <td class="text-right tabular-nums text-ink-500">—</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>

    <x-ui.card>
        <x-ui.textarea name="notes" label="Catatan" rows="3" />
    </x-ui.card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.purchases.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan PO</button>
    </div>
</form>
@endsection
