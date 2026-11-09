@extends('layouts.admin')
@section('title', $purchase->code)
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Pembelian', 'url' => route('admin.purchases.index')], ['label' => $purchase->code]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">{{ $purchase->code }}</h1>
@endsection

@section('content')
@php
    // Harga diketik manual: rupiah bulat tanpa sen, selain itu tampilkan dua desimal.
    $rupiah = fn ($value) => 'Rp '.number_format((float) $value, fmod((float) $value, 1) == 0 ? 0 : 2, ',', '.');
@endphp
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <x-ui.card title="Detail Pesanan Pembelian">
            <div class="grid md:grid-cols-2 gap-4 text-sm">
                <div><p class="text-ink-500">Kode Pesanan Pembelian</p><p class="font-medium">{{ $purchase->code }}</p></div>
                <div><p class="text-ink-500">Pemasok</p><p class="font-medium">{{ $purchase->supplier?->name ?? '—' }}</p></div>
                <div><p class="text-ink-500">Tanggal</p><p class="font-medium">{{ $purchase->purchase_date?->translatedFormat('d M Y') ?? '-' }}</p></div>
                <div><p class="text-ink-500">Status</p><x-ui.status-badge :status="$purchase->status" /></div>
            </div>
        </x-ui.card>

        <x-ui.card title="Item Pembelian">
            <table class="table-clean">
                <thead><tr><th>Bahan</th><th class="text-right">Jumlah</th><th>Satuan</th><th class="text-right">Harga per Unit</th><th class="text-right">Subtotal</th></tr></thead>
                <tbody>
                    @foreach ($purchase->items as $i)
                        <tr>
                            <td class="font-medium">{{ $i->material?->name ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ $i->qty }}</td>
                            <td class="text-ink-500">{{ $i->material?->unit ?? '-' }}</td>
                            <td class="text-right tabular-nums">{{ $rupiah($i->unit_cost) }}</td>
                            <td class="text-right tabular-nums font-medium">{{ $rupiah($i->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-ink-200">
                    <tr><td colspan="4" class="px-4 py-3 text-right font-semibold">Total</td><td class="px-4 py-3 text-right font-display text-lg font-semibold">{{ $rupiah($purchase->total) }}</td></tr>
                </tfoot>
            </table>
        </x-ui.card>
    </div>
    <div>
        <x-ui.card title="Aksi">
            <div class="space-y-2">
                @if ($purchase->status === 'pending')
                    <form action="{{ route('admin.purchases.receive', $purchase->id) }}" method="POST">
                        @csrf @method('PATCH')
                        <button class="btn-primary w-full justify-center">Tandai Diterima</button>
                    </form>
                    <form action="{{ route('admin.purchases.cancel', $purchase->id) }}" method="POST" onsubmit="return confirm('Batalkan pesanan pembelian ini?')">
                        @csrf @method('PATCH')
                        <button class="btn-secondary w-full justify-center text-red-600">Batalkan Pesanan Pembelian</button>
                    </form>
                @endif
                @if ($purchase->status !== 'received')
                    <form action="{{ route('admin.purchases.destroy', $purchase->id) }}" method="POST" onsubmit="return confirm('Hapus pesanan pembelian ini?')">
                        @csrf @method('DELETE')
                        <button class="btn-secondary w-full justify-center text-red-600">Hapus Pesanan Pembelian</button>
                    </form>
                @endif
            </div>
        </x-ui.card>
    </div>
</div>
@endsection
