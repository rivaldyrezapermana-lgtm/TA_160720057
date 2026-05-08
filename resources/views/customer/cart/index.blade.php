@extends('layouts.customer')
@section('title', 'Keranjang')

@section('content')
<div class="max-w-5xl mx-auto px-6 py-10">
    <h1 class="font-display text-4xl font-semibold mb-8">Keranjang</h1>

    @if ($items->isEmpty())
        <div class="border border-dashed border-ink-200 rounded-xl p-16 text-center">
            <p class="font-display text-xl text-ink-700">Keranjang kosong</p>
            <p class="text-sm text-ink-500 mt-1 mb-4">Yuk pilih produk dulu.</p>
            <a href="{{ route('shop.index') }}" class="btn-primary">Belanja Sekarang</a>
        </div>
    @else
        <div class="grid md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-3">
                @foreach ($items as $item)
                    <div class="bg-white border border-ink-100 rounded-xl p-4 flex gap-4 items-center">
                        <div class="w-20 h-20 bg-ink-100 rounded-lg flex-shrink-0 flex items-center justify-center font-display text-2xl text-ink-300">B</div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-ink-900">{{ $item->product }}</p>
                            <p class="text-xs text-ink-500">Size: {{ $item->size }}</p>
                            <div class="flex items-center gap-3 mt-2">
                                <form action="{{ route('cart.update', $item->id) }}" method="POST" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <input type="number" name="qty" value="{{ $item->qty }}" min="1" class="input w-20 py-1 text-sm">
                                    <button class="text-xs text-ink-600 hover:text-ink-900">Update</button>
                                </form>
                                <form action="{{ route('cart.remove', $item->id) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:text-red-700">Hapus</button>
                                </form>
                            </div>
                        </div>
                        <p class="font-display text-lg font-semibold tabular-nums">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>

            <div>
                <div class="bg-white border border-ink-100 rounded-xl p-5 sticky top-24">
                    <h2 class="font-display text-xl font-semibold mb-4">Ringkasan</h2>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-ink-500">Subtotal ({{ $items->count() }} item)</span><span class="tabular-nums">Rp {{ number_format($total, 0, ',', '.') }}</span></div>
                        <div class="flex justify-between"><span class="text-ink-500">Ongkir</span><span class="text-ink-500 text-xs">dihitung di checkout</span></div>
                    </div>
                    <div class="border-t border-ink-100 mt-4 pt-4 flex justify-between items-baseline">
                        <span class="font-medium">Total</span>
                        <span class="font-display text-2xl font-semibold tabular-nums">Rp {{ number_format($total, 0, ',', '.') }}</span>
                    </div>
                    <a href="{{ route('checkout.index') }}" class="btn-primary w-full justify-center mt-4">Checkout</a>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
