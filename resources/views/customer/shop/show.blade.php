@extends('layouts.customer')
@section('title', $product->name)

@section('content')
<div class="max-w-7xl mx-auto px-6 py-10">
    <nav class="text-sm text-ink-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-ink-900">Beranda</a> ·
        <a href="{{ route('shop.index') }}" class="hover:text-ink-900">Belanja</a> ·
        <span class="text-ink-900">{{ $product->name }}</span>
    </nav>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
        {{-- Image --}}
        <div class="relative aspect-[4/5] bg-ink-100 rounded-2xl overflow-hidden">
            @if ($product->image)
                <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
            @else
                <div class="w-full h-full flex items-center justify-center">
                    <span class="font-display text-6xl text-ink-300">{{ substr($product->category->name, 0, 1) }}</span>
                </div>
            @endif
            @if ($product->stock <= 0)
                <div class="absolute inset-0 bg-ink-900/55 flex items-center justify-center">
                    <span class="text-white text-sm font-semibold uppercase tracking-[0.2em] border border-white/70 rounded-full px-4 py-1.5">Stok Habis</span>
                </div>
            @endif
        </div>

        {{-- Info --}}
        <div>
            <p class="text-xs uppercase tracking-[0.18em] text-ink-500 font-semibold">{{ $product->category->name }}</p>
            <h1 class="font-display text-4xl font-semibold mt-2">{{ $product->name }}</h1>
            <p class="font-display text-3xl font-semibold mt-4">Rp {{ number_format($product->price, 0, ',', '.') }}</p>

            <div class="h-px bg-ink-100 my-6"></div>

            <p class="text-ink-700 leading-relaxed">{{ $product->description }}</p>

            <form action="{{ route('cart.add') }}" method="POST" class="mt-8 space-y-4">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if ($product->sizes->isNotEmpty())
                    <div>
                        <label class="label">Pilih Ukuran</label>
                        <div class="grid grid-cols-4 gap-2">
                            @foreach ($product->sizes as $s)
                                <label class="cursor-pointer">
                                    <input type="radio" name="size" value="{{ $s->size }}" class="peer sr-only" {{ $loop->first ? 'checked' : '' }} {{ $s->stock <= 0 ? 'disabled' : '' }}>
                                    <div class="border border-ink-200 rounded-lg py-3 text-center peer-checked:border-ink-900 peer-checked:bg-ink-900 peer-checked:text-white peer-disabled:opacity-40 peer-disabled:cursor-not-allowed transition">
                                        <p class="font-medium">{{ $s->size }}</p>
                                        <p class="text-[10px] opacity-70">{{ $s->stock }} pcs</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <label class="label">Jumlah</label>
                    <input type="number" name="qty" value="1" min="1" max="{{ max($product->stock, 1) }}" class="input w-32">
                </div>

                <div class="flex gap-3 pt-2">
                    @if ($product->stock <= 0)
                        <button type="button" disabled class="btn-secondary flex-1 justify-center opacity-60 cursor-not-allowed">Stok Habis</button>
                    @else
                        @auth
                            <button class="btn-primary flex-1 justify-center">Tambah ke Keranjang</button>
                            @if (auth()->user()->isPembeli())
                                <a href="{{ route('customer.chat.index') }}" class="btn-secondary">Tanya Penjual</a>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="btn-primary flex-1 justify-center">Masuk untuk Membeli</a>
                        @endauth
                    @endif
                </div>
            </form>

            {{-- Size chart --}}
            @if ($product->sizes->isNotEmpty())
                <details class="mt-8 border-t border-ink-100 pt-6">
                    <summary class="cursor-pointer font-medium text-ink-900 list-none flex items-center justify-between">
                        Tabel Ukuran (cm)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <table class="w-full mt-4 text-sm">
                        <thead><tr class="border-b border-ink-100"><th class="text-left py-2 text-xs uppercase tracking-wider text-ink-500 font-semibold">Size</th><th class="text-right py-2 text-xs uppercase tracking-wider text-ink-500 font-semibold">Lingkar Dada</th><th class="text-right py-2 text-xs uppercase tracking-wider text-ink-500 font-semibold">Panjang</th><th class="text-right py-2 text-xs uppercase tracking-wider text-ink-500 font-semibold">Lengan</th></tr></thead>
                        <tbody>
                            @foreach ($product->sizes as $s)
                                <tr class="border-b border-ink-50 last:border-0">
                                    <td class="py-2 font-medium">{{ $s->size }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $s->chest_cm }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $s->length_cm }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $s->sleeve_cm }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            @endif
        </div>
    </div>
</div>
@endsection
