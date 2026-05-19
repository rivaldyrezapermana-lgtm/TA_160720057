<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Toko Labasa')</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        // Keep old "ink-*" / "accent-*" / "font-display" working in existing pages.
        tailwind.config = {
            theme: { extend: {
                fontFamily: { display: ['system-ui', 'sans-serif'] },
                colors: {
                    ink:    { 50:'#f8fafc',100:'#f1f5f9',200:'#e2e8f0',300:'#cbd5e1',400:'#94a3b8',500:'#64748b',600:'#475569',700:'#334155',800:'#1e293b',900:'#0f172a' },
                    accent: { 50:'#ecfdf5',100:'#d1fae5',500:'#10b981',600:'#059669',700:'#047857' },
                },
            } }
        }
    </script>

    <style>
        .btn         { display:inline-flex; align-items:center; gap:.5rem; padding:.5rem 1rem; border-radius:.5rem; font-size:.875rem; font-weight:500; }
        .btn-primary   { background:#059669; color:#fff; }
        .btn-secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }

        .field       { margin-bottom:1rem; }
        .label       { display:block; font-size:.875rem; font-weight:500; color:#334155; margin-bottom:.25rem; }
        .input       { display:block; width:100%; padding:.5rem .75rem; font-size:.875rem; border:1px solid #cbd5e1; border-radius:.5rem; }
        .input:focus { outline:none; border-color:#10b981; }
        .field-help  { font-size:.75rem; color:#64748b; margin-top:.25rem; }
        .field-error { font-size:.75rem; color:#dc2626; margin-top:.25rem; }

        .badge       { display:inline-flex; padding:.125rem .5rem; border-radius:.5rem; font-size:.75rem; font-weight:500; background:#f1f5f9; color:#334155; }
        .badge-green { background:#ecfdf5; color:#047857; }
        .badge-amber { background:#fffbeb; color:#b45309; }
        .badge-blue  { background:#eff6ff; color:#1d4ed8; }
    </style>
    @stack('styles')
</head>
<body class="bg-white text-slate-900 min-h-screen flex flex-col">

@php $u = auth()->user(); @endphp

<header class="bg-white border-b border-slate-200 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between gap-6">
        <a href="{{ route('home') }}" class="text-xl font-semibold text-slate-900">Labasa</a>

        <nav class="hidden md:flex items-center gap-6 text-sm">
            <a href="{{ route('home') }}"        class="{{ request()->routeIs('home')   ? 'text-emerald-700 font-medium' : 'text-slate-600 hover:text-slate-900' }}">Beranda</a>
            <a href="{{ route('shop.index') }}"  class="{{ request()->routeIs('shop.*') ? 'text-emerald-700 font-medium' : 'text-slate-600 hover:text-slate-900' }}">Belanja</a>
            @auth
                @if ($u->isPembeli())
                    <a href="{{ route('customer.orders.index') }}" class="{{ request()->routeIs('customer.orders.*') ? 'text-emerald-700 font-medium' : 'text-slate-600 hover:text-slate-900' }}">Pesanan</a>
                    <a href="{{ route('customer.chat.index') }}"  class="{{ request()->routeIs('customer.chat.*')   ? 'text-emerald-700 font-medium' : 'text-slate-600 hover:text-slate-900' }}">Chat</a>
                @else
                    <a href="{{ route('admin.dashboard') }}" class="text-slate-600 hover:text-slate-900">Admin</a>
                @endif
            @endauth
        </nav>

        <div class="flex items-center gap-3 text-sm">
            @auth
                @if ($u->isPembeli())
                    <a href="{{ route('cart.index') }}" class="text-slate-600 hover:text-slate-900" title="Keranjang">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.5 5h13"/>
                            <circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>
                        </svg>
                    </a>
                @endif
                <a href="{{ route('profile.edit') }}" class="text-slate-600 hover:text-slate-900 hidden sm:inline">{{ $u->name }}</a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Keluar</button>
                </form>
            @else
                <a href="{{ route('login') }}"    class="text-slate-600 hover:text-slate-900">Masuk</a>
                <a href="{{ route('register') }}" class="btn btn-primary">Daftar</a>
            @endauth

            <button id="mobile-trigger" type="button" class="md:hidden text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>

    <div id="mobile-panel" class="md:hidden hidden border-t border-slate-200">
        <nav class="px-6 py-3 space-y-2 text-sm">
            <a href="{{ route('home') }}"       class="block py-1 text-slate-700">Beranda</a>
            <a href="{{ route('shop.index') }}" class="block py-1 text-slate-700">Belanja</a>
            @auth
                @if ($u->isPembeli())
                    <a href="{{ route('customer.orders.index') }}" class="block py-1 text-slate-700">Pesanan Saya</a>
                    <a href="{{ route('cart.index') }}"            class="block py-1 text-slate-700">Keranjang</a>
                    <a href="{{ route('customer.chat.index') }}"   class="block py-1 text-slate-700">Chat</a>
                    <a href="{{ route('profile.edit') }}"          class="block py-1 text-slate-700">Profil</a>
                @else
                    <a href="{{ route('admin.dashboard') }}" class="block py-1 text-slate-700">Dashboard Admin</a>
                    <a href="{{ route('profile.edit') }}"    class="block py-1 text-slate-700">Profil</a>
                @endif
            @else
                <a href="{{ route('login') }}"    class="block py-1 text-slate-700">Masuk</a>
                <a href="{{ route('register') }}" class="block py-1 text-emerald-700 font-medium">Daftar</a>
            @endauth
        </nav>
    </div>
</header>

<main class="flex-1">
    @if (session('success'))
        <div class="max-w-7xl mx-auto px-6 mt-4">
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @yield('content')
</main>

<footer class="border-t border-slate-200 mt-16">
    <div class="max-w-7xl mx-auto px-6 py-8 grid md:grid-cols-3 gap-6 text-sm text-slate-600">
        <div>
            <p class="font-semibold text-slate-900 mb-1">Labasa</p>
            <p>Baju muslim — dijahit dengan rapi, dipakai dengan bangga.</p>
        </div>
        <div>
            <p class="font-semibold text-slate-900 mb-1">Kontak</p>
            <p>WhatsApp: 0812-0000-0000</p>
            <p>Email: hello@labasa.id</p>
        </div>
        <div>
            <p class="font-semibold text-slate-900 mb-1">Toko</p>
            <p>Surabaya, Jawa Timur</p>
            <p>Senin–Sabtu, 09.00–17.00</p>
        </div>
    </div>
    <div class="border-t border-slate-200 py-3 text-center text-xs text-slate-500">
        © {{ date('Y') }} Toko Labasa. Tugas Akhir Rivaldy Reza Permana — UBAYA.
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    $('#mobile-trigger').on('click', function () {
        $('#mobile-panel').toggleClass('hidden');
    });
</script>
@stack('scripts')

</body>
</html>
