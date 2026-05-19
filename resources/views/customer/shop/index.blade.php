@extends('layouts.customer')
@section('title', 'Belanja')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-10">
    <div class="mb-8">
        <h1 class="font-display text-4xl font-semibold">Belanja</h1>
        <p class="text-ink-600 mt-2">Semua produk Toko Labasa.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
        {{-- Filters sidebar --}}
        <aside class="md:col-span-1">
            <form method="GET" class="space-y-6 sticky top-24">
                <div>
                    <label class="label">Cari</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama produk..." class="input">
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-ink-500 font-semibold mb-3">Kategori</p>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="category" value="" {{ !request('category') ? 'checked' : '' }} class="border-ink-300">
                            Semua
                        </label>
                        @foreach ($categories as $c)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="category" value="{{ $c }}" {{ request('category') === $c ? 'checked' : '' }} class="border-ink-300">
                                {{ $c }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <button class="btn-primary w-full">Terapkan</button>
            </form>
        </aside>

        {{-- Products grid --}}
        <div class="md:col-span-3">
            @if ($products->isEmpty())
                <div class="border border-dashed border-ink-200 rounded-xl p-16 text-center">
                    <p class="font-display text-xl text-ink-700">Tidak ada produk ditemukan</p>
                    <p class="text-sm text-ink-500 mt-1">Coba ubah kata kunci atau kategori.</p>
                </div>
            @else
                <p class="text-sm text-ink-500 mb-4">{{ $products->count() }} produk</p>
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($products as $p)
                        <a href="{{ route('shop.show', $p->id) }}" class="group">
                            <div class="relative aspect-[4/5] bg-ink-100 rounded-xl overflow-hidden mb-3">
                                @if ($p->image)
                                    <img src="{{ asset('storage/' . $p->image) }}" alt="{{ $p->name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center">
                                        <span class="font-display text-3xl text-ink-300">{{ substr($p->category->name, 0, 1) }}</span>
                                    </div>
                                @endif
                                @if ($p->stock <= 0)
                                    <div class="absolute inset-0 bg-ink-900/55 flex items-center justify-center">
                                        <span class="text-white text-xs font-semibold uppercase tracking-[0.18em] border border-white/70 rounded-full px-3 py-1">Stok Habis</span>
                                    </div>
                                @endif
                            </div>
                            <p class="text-xs uppercase tracking-wider text-ink-500">{{ $p->category->name }}</p>
                            <p class="font-medium text-ink-900 mt-1 group-hover:underline underline-offset-2">{{ $p->name }}</p>
                            <div class="flex items-center justify-between mt-1">
                                <p class="font-display text-lg font-semibold">Rp {{ number_format($p->price, 0, ',', '.') }}</p>
                                @if ($p->stock > 0 && $p->stock < 10)
                                    <span class="badge-amber">Stok terbatas</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
