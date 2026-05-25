@extends('layouts.admin')
@section('title', $order->code)
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Pesanan', 'url' => route('admin.orders.index')], ['label' => $order->code]]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">{{ $order->code }}</h1>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <x-ui.card title="Item Pesanan">
            <table class="table-clean">
                <thead><tr><th>Produk</th><th>Size</th><th class="text-right">Qty</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr></thead>
                <tbody>
                    @foreach ($order->items as $i)
                        <tr>
                            <td class="font-medium">{{ $i->product?->name ?? '—' }}</td>
                            <td class="text-ink-500">{{ $i->size ?? '-' }}</td>
                            <td class="text-right tabular-nums">{{ $i->qty }}</td>
                            <td class="text-right tabular-nums">Rp {{ number_format((float) $i->price, 0, ',', '.') }}</td>
                            <td class="text-right tabular-nums font-medium">Rp {{ number_format((float) $i->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-ink-200">
                    <tr><td colspan="4" class="px-4 py-3 text-right font-semibold">Total</td><td class="px-4 py-3 text-right font-display text-lg font-semibold">Rp {{ number_format((float) $order->total, 0, ',', '.') }}</td></tr>
                </tfoot>
            </table>
        </x-ui.card>

        <x-ui.card title="Pengiriman">
            <p class="text-sm font-medium text-ink-900">{{ $order->user?->name ?? '—' }}</p>
            <p class="text-sm text-ink-600">{{ $order->user?->phone ?? '—' }} · {{ $order->user?->email ?? '—' }}</p>
            <p class="text-sm text-ink-700 mt-2 whitespace-pre-line">{{ $order->shipping_address }}</p>
        </x-ui.card>

        <x-ui.card title="Pembayaran">
            @if ($order->payment)
                <div class="grid md:grid-cols-2 gap-4 text-sm">
                    <div><p class="text-ink-500">Metode</p><p class="font-medium capitalize">{{ $order->payment->method }}</p></div>
                    <div><p class="text-ink-500">Status</p><x-ui.status-badge :status="$order->payment->status" /></div>
                    <div>
                        <p class="text-ink-500">Bukti Transfer</p>
                        @if ($order->payment->proof_image)
                            <a href="{{ asset('storage/'.$order->payment->proof_image) }}" target="_blank" class="text-ink-900 underline text-sm">Lihat bukti</a>
                        @else
                            <span class="text-ink-400 text-sm">Belum diupload</span>
                        @endif
                    </div>
                    <div><p class="text-ink-500">Tanggal Bayar</p><p class="font-medium">{{ $order->payment->paid_at?->translatedFormat('d M Y, H:i') ?? '-' }}</p></div>
                </div>
            @else
                <p class="text-sm text-ink-500">Belum ada data pembayaran.</p>
            @endif
        </x-ui.card>
    </div>

    <div>
        <x-ui.card title="Status Pesanan">
            <p class="text-xs text-ink-500 mb-2">Status saat ini</p>
            <x-ui.status-badge :status="$order->status" />

            <p class="text-xs text-ink-500 mt-4 mb-2">Ubah Status</p>
            <form action="{{ route('admin.orders.status', $order->id) }}" method="POST" class="space-y-2">
                @csrf @method('PATCH')
                <select name="status" class="input">
                    @foreach (\App\Models\Order::STATUSES as $s)
                        <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
                <button class="btn-primary w-full justify-center">Update</button>
            </form>

            @if ($order->payment && $order->payment->status !== 'verified')
                <form action="{{ route('admin.orders.verify', $order->id) }}" method="POST" class="mt-3">
                    @csrf @method('PATCH')
                    <button class="btn-secondary w-full justify-center">Verifikasi Pembayaran</button>
                </form>
            @endif
        </x-ui.card>
    </div>
</div>
@endsection
