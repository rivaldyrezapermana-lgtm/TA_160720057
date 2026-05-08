@extends('layouts.admin')
@section('title', $purchase->code)
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Pembelian', 'url' => route('admin.purchases.index')], ['label' => $purchase->code]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">{{ $purchase->code }}</h1>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <x-ui.card title="Detail PO">
            <div class="grid md:grid-cols-2 gap-4 text-sm">
                <div><p class="text-ink-500">Kode PO</p><p class="font-medium">{{ $purchase->code }}</p></div>
                <div><p class="text-ink-500">Supplier</p><p class="font-medium">{{ $purchase->supplier }}</p></div>
                <div><p class="text-ink-500">Tanggal</p><p class="font-medium">{{ $purchase->date }}</p></div>
                <div><p class="text-ink-500">Status</p><x-ui.status-badge :status="$purchase->status" /></div>
            </div>
        </x-ui.card>

        <x-ui.card title="Item Pembelian">
            <table class="table-clean">
                <thead><tr><th>Bahan</th><th class="text-right">Qty</th><th>Unit</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr></thead>
                <tbody>
                    @foreach ($purchase->items as $i)
                        <tr>
                            <td class="font-medium">{{ $i['material'] }}</td>
                            <td class="text-right tabular-nums">{{ $i['qty'] }}</td>
                            <td class="text-ink-500">{{ $i['unit'] }}</td>
                            <td class="text-right tabular-nums">Rp {{ number_format($i['unit_cost'], 0, ',', '.') }}</td>
                            <td class="text-right tabular-nums font-medium">Rp {{ number_format($i['subtotal'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-ink-200">
                    <tr><td colspan="4" class="px-4 py-3 text-right font-semibold">Total</td><td class="px-4 py-3 text-right font-display text-lg font-semibold">{{ $purchase->total }}</td></tr>
                </tfoot>
            </table>
        </x-ui.card>
    </div>
    <div>
        <x-ui.card title="Aksi">
            <div class="space-y-2">
                <button class="btn-primary w-full justify-center">Tandai Diterima</button>
                <button class="btn-secondary w-full justify-center">Cetak PO</button>
                <button class="btn-danger w-full justify-center">Batalkan PO</button>
            </div>
        </x-ui.card>
    </div>
</div>
@endsection
