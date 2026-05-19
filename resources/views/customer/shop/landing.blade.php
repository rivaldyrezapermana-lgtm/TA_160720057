@extends('layouts.customer')
@section('title', 'Toko Labasa — Baju Muslim')

@section('content')
{{-- Hero --}}
<section class="border-b border-ink-100">
    <div class="max-w-7xl mx-auto px-6 py-20 md:py-28 grid md:grid-cols-2 gap-12 items-center">
        <div>
            <p class="text-xs uppercase tracking-[0.2em] text-ink-500 font-semibold mb-4">Koleksi Mei 2026</p>
            <h1 class="font-display text-5xl md:text-6xl font-semibold leading-[1.05] text-ink-900">
                Baju muslim, <br>
                <em class="font-normal">dijahit dengan rapi.</em>
            </h1>
            <p class="text-base text-ink-600 leading-relaxed mt-6 max-w-md">
                Setiap helai dirancang dan diproduksi sendiri di workshop Labasa,
                dengan bahan terpilih dan jahitan yang dapat dipertanggungjawabkan.
            </p>
            <div class="flex items-center gap-3 mt-8">
                <a href="{{ route('shop.index') }}" class="btn-primary">Belanja Sekarang</a>
                <a href="#kategori" class="btn-secondary">Lihat Kategori</a>
            </div>
        </div>
        <div class="relative">
            <div class="aspect-[4/5] bg-ink-100 rounded-2xl flex items-center justify-center">
                <span class="font-display text-7xl text-ink-300 italic">Labasa.</span>
            </div>
            <div class="absolute -bottom-4 -left-4 bg-white border border-ink-100 rounded-xl p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wider text-ink-500 font-semibold">Produksi sendiri</p>
                <p class="font-display text-xl font-semibold mt-0.5">Workshop Surabaya</p>
            </div>
        </div>
    </div>
</section>

{{-- Categories --}}
<section id="kategori" class="border-b border-ink-100">
    <div class="max-w-7xl mx-auto px-6 py-16">
        <div class="flex items-end justify-between mb-8">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-ink-500 font-semibold">Belanja</p>
                <h2 class="font-display text-3xl font-semibold mt-1">Kategori</h2>
            </div>
            <a href="{{ route('shop.index') }}" class="text-sm text-ink-600 hover:text-ink-900">Semua produk →</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($categories as $c)
                <a href="{{ route('shop.index', ['category' => $c->name]) }}" class="group bg-ink-50 rounded-xl p-6 hover:bg-ink-100 transition">
                    <p class="font-display text-2xl font-semibold text-ink-900">{{ $c->name }}</p>
                    <p class="text-sm text-ink-500 mt-1">{{ $c->products_count }} produk →</p>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Featured products --}}
<section>
    <div class="max-w-7xl mx-auto px-6 py-16">
        <div class="flex items-end justify-between mb-8">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-ink-500 font-semibold">Pilihan Editor</p>
                <h2 class="font-display text-3xl font-semibold mt-1">Produk Unggulan</h2>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            @foreach ($featured as $p)
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
                    <p class="font-display text-lg font-semibold mt-1">Rp {{ number_format($p->price, 0, ',', '.') }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Why us --}}
<section class="bg-ink-900 text-white">
    <div class="max-w-7xl mx-auto px-6 py-16 grid md:grid-cols-3 gap-8">
        <div>
            <p class="font-display text-xl font-semibold">Bahan Pilihan</p>
            <p class="text-ink-300 text-sm leading-relaxed mt-2">Katun premium, rayon halus — dipilih sendiri, bukan stok lot.</p>
        </div>
        <div>
            <p class="font-display text-xl font-semibold">Jahitan Rapi</p>
            <p class="text-ink-300 text-sm leading-relaxed mt-2">Quality control 6 tahap, dari design hingga packing.</p>
        </div>
        <div>
            <p class="font-display text-xl font-semibold">Live Chat</p>
            <p class="text-ink-300 text-sm leading-relaxed mt-2">Tanya langsung sebelum order — kami balas selama jam toko.</p>
        </div>
    </div>
</section>
@endsection
