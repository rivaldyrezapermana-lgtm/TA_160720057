@extends('layouts.customer')
@section('title', $order->code)

@section('content')
<div class="max-w-5xl mx-auto px-6 py-10">
    <nav class="text-sm text-ink-500 mb-6">
        <a href="{{ route('customer.orders.index') }}" class="hover:text-ink-900">Pesanan Saya</a> · <span class="text-ink-900">{{ $order->code }}</span>
    </nav>

    <div class="flex items-start justify-between mb-8">
        <div>
            <h1 class="font-display text-3xl font-semibold">{{ $order->code }}</h1>
            <p class="text-sm text-ink-500 mt-1">Dipesan pada {{ $order->created_at->translatedFormat('d M Y, H:i') }}</p>
        </div>
        <x-ui.status-badge :status="$order->status" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Timeline --}}
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white border border-ink-100 rounded-xl p-6">
                <h2 class="font-display text-xl font-semibold mb-4">Status Pesanan</h2>
                <div class="relative pl-8">
                    <div class="absolute left-3 top-2 bottom-2 w-px bg-ink-200"></div>
                    @foreach ($timeline as $i => $t)
                        <div class="relative pb-5 last:pb-0">
                            <div class="absolute -left-8 w-6 h-6 rounded-full {{ $t['done'] ? 'bg-ink-900 text-white' : 'bg-white border-2 border-ink-200 text-ink-400' }} flex items-center justify-center text-xs font-semibold">
                                @if ($t['done']) ✓ @else {{ $i+1 }} @endif
                            </div>
                            <p class="font-medium {{ $t['done'] ? 'text-ink-900' : 'text-ink-400' }}">{{ $t['label'] }}</p>
                            <p class="text-xs {{ $t['done'] ? 'text-ink-500' : 'text-ink-400' }}">{{ $t['time'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white border border-ink-100 rounded-xl p-6">
                <h2 class="font-display text-xl font-semibold mb-4">Item</h2>
                <div class="space-y-3">
                    @foreach ($order->items as $i)
                        <div class="flex gap-4 items-center">
                            <div class="w-16 h-16 bg-ink-100 rounded-lg flex-shrink-0 overflow-hidden">
                                @if ($i->product?->image)
                                    <img src="{{ asset('storage/'.$i->product->image) }}" class="w-full h-full object-cover" alt="">
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-ink-900">{{ $i->product?->name ?? '—' }}</p>
                                <p class="text-xs text-ink-500">{{ $i->qty }}× · Size {{ $i->size ?? '-' }}</p>
                            </div>
                            <p class="font-display text-lg font-semibold tabular-nums">Rp {{ number_format($i->subtotal, 0, ',', '.') }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-ink-100 mt-4 pt-4 flex justify-between items-baseline">
                    <span class="font-medium">Total</span>
                    <span class="font-display text-2xl font-semibold tabular-nums">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- Sidebar info --}}
        <div class="space-y-6">
            <div class="bg-white border border-ink-100 rounded-xl p-5">
                <h3 class="font-medium mb-2">Pengiriman</h3>
                <p class="text-sm text-ink-600 whitespace-pre-line">{{ $order->shipping_address }}</p>
            </div>

            <div class="bg-white border border-ink-100 rounded-xl p-5">
                <h3 class="font-medium mb-2">Pembayaran</h3>
                <div class="text-sm space-y-1">
                    <p class="text-ink-500">Metode: <span class="text-ink-900 capitalize">{{ $order->payment?->method ?? '-' }}</span></p>
                    <p class="text-ink-500">Status: <x-ui.status-badge :status="$order->payment?->status ?? 'pending'" /></p>
                </div>
                @if (! $order->payment?->proof_image)
                    <form action="{{ route('checkout.proof', $order->id) }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-2">
                        @csrf
                        <input type="file" name="proof" accept="image/*" required class="input text-xs">
                        <button class="btn-primary w-full justify-center text-sm">Upload Bukti</button>
                    </form>
                @else
                    <a href="{{ asset('storage/'.$order->payment->proof_image) }}" target="_blank" class="text-xs text-ink-900 underline mt-2 inline-block">Lihat bukti transfer</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
