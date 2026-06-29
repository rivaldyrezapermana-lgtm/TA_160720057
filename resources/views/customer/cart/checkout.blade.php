@extends('layouts.customer')
@section('title', 'Checkout')

@section('content')
<div class="max-w-5xl mx-auto px-6 py-10">
    <h1 class="font-display text-4xl font-semibold mb-8">Checkout</h1>

    <form action="{{ route('checkout.store') }}" method="POST">
        @csrf
        <div class="grid md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-6">
                <div class="bg-white border border-ink-100 rounded-xl p-6">
                    <h2 class="font-display text-xl font-semibold mb-4">Alamat Pengiriman</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="label">Nama Penerima</label>
                            <input type="text" name="recipient" value="{{ auth()->user()?->name }}" required class="input">
                        </div>
                        <div>
                            <label class="label">Telepon</label>
                            <input type="tel" name="phone" value="{{ auth()->user()?->phone }}" required class="input">
                        </div>
                        <div>
                            <label class="label">Alamat Lengkap</label>
                            <textarea name="shipping_address" rows="3" required class="input">{{ auth()->user()?->address }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-ink-100 rounded-xl p-6">
                    <h2 class="font-display text-xl font-semibold mb-4">Metode Pembayaran</h2>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 p-3 border border-ink-200 rounded-lg cursor-pointer hover:bg-ink-50">
                            <input type="radio" name="payment_method" value="transfer" checked class="border-ink-300">
                            <div>
                                <p class="font-medium">Transfer Bank BCA</p>
                                <p class="text-xs text-ink-500">{{ \App\Http\Controllers\Customer\CheckoutController::bankInfo() }}</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <div>
                <div class="bg-white border border-ink-100 rounded-xl p-5 sticky top-24">
                    <h2 class="font-display text-xl font-semibold mb-4">Pesanan</h2>
                    <div class="space-y-3 mb-4">
                        @foreach ($items as $i)
                            <div class="flex justify-between text-sm">
                                <div class="flex-1">
                                    <p class="font-medium text-ink-900">{{ $i->product }}</p>
                                    <p class="text-xs text-ink-500">{{ $i->qty }}× · Size {{ $i->size }}</p>
                                </div>
                                <p class="tabular-nums">Rp {{ number_format($i->subtotal, 0, ',', '.') }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="border-t border-ink-100 pt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-ink-500">Subtotal</span><span class="tabular-nums">Rp {{ number_format($total, 0, ',', '.') }}</span></div>
                        <div class="flex justify-between"><span class="text-ink-500">Ongkir</span><span class="tabular-nums">Rp {{ number_format($shipping, 0, ',', '.') }}</span></div>
                    </div>
                    <div class="border-t border-ink-100 mt-3 pt-3 flex justify-between items-baseline">
                        <span class="font-medium">Total</span>
                        <span class="font-display text-2xl font-semibold tabular-nums">Rp {{ number_format($total + $shipping, 0, ',', '.') }}</span>
                    </div>
                    <button type="submit" class="btn-primary w-full justify-center mt-4">Buat Pesanan</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
