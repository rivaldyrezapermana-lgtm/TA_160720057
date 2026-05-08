@extends('layouts.customer')
@section('title', 'Pesanan Berhasil')

@section('content')
<div class="max-w-2xl mx-auto px-6 py-16">
    <div class="text-center mb-8">
        <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="font-display text-3xl font-semibold">Pesanan berhasil dibuat</h1>
        <p class="text-ink-600 mt-2">Kode pesanan: <span class="font-medium text-ink-900">{{ $order->code }}</span></p>
    </div>

    <div class="bg-white border border-ink-100 rounded-xl p-6">
        <h2 class="font-display text-xl font-semibold mb-4">Instruksi Pembayaran</h2>
        <p class="text-sm text-ink-600 mb-3">Silakan transfer ke rekening berikut sesuai total pembayaran:</p>

        <div class="bg-ink-900 text-white rounded-lg p-5 mb-4">
            <p class="text-xs uppercase tracking-wider text-ink-300 font-semibold">Bank</p>
            <p class="font-display text-lg font-semibold mt-1">{{ $order->bank }}</p>
            <p class="text-xs uppercase tracking-wider text-ink-300 font-semibold mt-3">Total</p>
            <p class="font-display text-2xl font-semibold tabular-nums mt-1">Rp {{ number_format($order->total, 0, ',', '.') }}</p>
        </div>

        <p class="text-sm text-ink-600 mb-4">Setelah transfer, upload bukti pembayaran agar pesanan diproses:</p>

        <form action="{{ route('checkout.proof', $order->id) }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <input type="file" name="proof" accept="image/*" required class="input">
            <div class="flex gap-3">
                <button class="btn-primary flex-1 justify-center">Upload Bukti</button>
                <a href="{{ route('customer.orders.show', $order->id) }}" class="btn-secondary">Lihat Pesanan</a>
            </div>
        </form>
    </div>
</div>
@endsection
