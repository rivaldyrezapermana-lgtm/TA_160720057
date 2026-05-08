<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Toko Labasa')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter+Tight:wght@400;500;600&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Fraunces', 'serif'],
                        sans: ['Inter Tight', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        ink: { 50:'#f7f7f6', 100:'#eeeeec', 200:'#d8d8d4', 300:'#b4b4ad', 400:'#8a8a82', 500:'#6b6b63', 600:'#54544c', 700:'#42423b', 800:'#2c2c27', 900:'#1a1a16' },
                        accent: { 50:'#f4f6f3', 500:'#5b7553', 600:'#48604a', 700:'#3a4d3d' },
                    },
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Inter Tight', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        .font-display { font-family: 'Fraunces', serif; font-optical-sizing: auto; }
        .btn { @apply inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-medium transition; }
        .btn-primary { @apply btn bg-ink-900 text-white hover:bg-ink-700; }
        .btn-secondary { @apply btn bg-white text-ink-900 border border-ink-200 hover:bg-ink-50; }
        /* ── Forms — Atelier (fountain pen on parchment) ── */
        .field { position: relative; padding-top: 6px; }
        .field + .field { margin-top: 1.4rem; }
        .label {
            display: block;
            font-family: 'Fraunces', serif;
            font-style: italic; font-weight: 500;
            font-size: 10.5px;
            color: #8a8a82;
            text-transform: uppercase;
            letter-spacing: 0.28em;
            margin-bottom: 9px;
        }
        .label .req, .label .text-red-500 { color: #5b7553; margin-left: 3px; font-style: normal; }
        .input,
        input.input,
        select.input,
        textarea.input {
            width: 100%;
            -webkit-appearance: none; appearance: none;
            background: transparent;
            border: 0;
            border-bottom: 1px solid #d8d8d4;
            border-radius: 0;
            padding: 6px 0 10px;
            font-family: 'Inter Tight', sans-serif;
            font-size: 14.5px;
            font-weight: 450;
            color: #1a1a16;
            outline: none; box-shadow: none;
            transition: border-color .25s ease, background .25s ease, box-shadow .3s ease;
        }
        .input::placeholder { color: #8a8a82; font-style: italic; opacity: .55; }
        .input:hover { border-bottom-color: #8a8a82; }
        .input:focus {
            border-bottom-color: #1a1a16;
            background: linear-gradient(180deg, transparent 60%, rgba(244,246,243,.55) 100%);
            box-shadow: 0 1px 0 0 #5b7553;
        }
        .input.has-error,
        .input[aria-invalid="true"] { border-bottom-color: #b91c1c; }
        select.input {
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'><path d='M3 4.5l3 3 3-3' fill='none' stroke='%235b7553' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round'/></svg>");
            background-repeat: no-repeat; background-position: right 2px center; background-size: 12px;
            padding-right: 22px; cursor: pointer;
        }
        textarea.input { min-height: 96px; resize: vertical; padding-top: 10px; line-height: 1.65; }
        .field-help { font-family: 'Fraunces', serif; font-style: italic; font-size: 11.5px; color: #8a8a82; margin-top: 6px; }
        .field-error {
            font-family: 'Fraunces', serif; font-style: italic; font-weight: 500;
            font-size: 12px; color: #b91c1c; margin-top: 6px;
            padding-left: 14px; position: relative;
        }
        .field-error::before { content: '—'; position: absolute; left: 0; top: 0; }
        .badge { @apply inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium; }
        .badge-green { @apply badge bg-emerald-50 text-emerald-700; }
        .badge-amber { @apply badge bg-amber-50 text-amber-700; }
        .badge-blue  { @apply badge bg-blue-50 text-blue-700; }
        .badge-gray  { @apply badge bg-ink-100 text-ink-700; }

        /* ── Site header — Atelier ────────────────── */
        #site-header { transition: background-color .25s ease, box-shadow .25s ease, border-color .25s ease; }
        #site-header.is-scrolled {
            background-color: rgba(251,250,247,.94);
            box-shadow: 0 1px 0 rgba(0,0,0,.04), 0 8px 24px -16px rgba(0,0,0,.1);
        }

        /* Brand — octagram + wordmark */
        .brand-mark { display: inline-flex; align-items: center; gap: .65rem; min-width: 0; }
        .brand-mark .star {
            width: 28px; height: 28px; color: #1a1a16;
            transition: transform .55s cubic-bezier(.2,.8,.2,1);
            flex-shrink: 0;
        }
        .brand-mark:hover .star { transform: rotate(22.5deg); }
        .brand-mark .wordmark {
            font-family: 'Fraunces', serif;
            font-optical-sizing: auto;
            font-variation-settings: "opsz" 144;
            font-weight: 600;
            font-size: 1.5rem;
            line-height: 1;
            letter-spacing: -0.012em;
            color: #1a1a16;
        }
        .brand-mark .wordmark em { font-style: italic; color: #48604a; font-weight: 500; }

        /* Nav links — Fraunces italic on active */
        .nav-link {
            position: relative; padding: .35rem 0;
            font-family: 'Inter Tight', sans-serif;
            font-size: 13px; font-weight: 500;
            color: #54544c;
            transition: color .2s ease;
        }
        .nav-link:hover { color: #1a1a16; }
        .nav-link::after {
            content: ''; position: absolute; left: 0; right: 0; bottom: -3px; height: 1px;
            background: linear-gradient(90deg, #1a1a16, #5b7553);
            transform: scaleX(0); transform-origin: left center;
            transition: transform .3s cubic-bezier(.65,.05,.36,1);
        }
        .nav-link:hover::after { transform: scaleX(1); }
        .nav-link.is-active {
            color: #1a1a16;
            font-family: 'Fraunces', serif;
            font-style: italic; font-weight: 500;
            font-size: 14.5px;
            letter-spacing: -0.005em;
        }
        .nav-link.is-active::after { transform: scaleX(1); }

        /* Cart pill */
        .pill-cart {
            position: relative;
            display: inline-flex; align-items: center; gap: .55rem;
            padding: 7px 14px 7px 12px;
            border: 1px solid #d8d8d4;
            border-radius: 999px;
            background: #fff;
            color: #54544c;
            font-family: 'Inter Tight', sans-serif;
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.18em;
            transition: border-color .2s ease, color .2s ease, box-shadow .25s ease;
        }
        .pill-cart svg { width: 15px; height: 15px; }
        .pill-cart:hover { border-color: #1a1a16; color: #1a1a16; box-shadow: 0 0 0 4px rgba(26,26,22,.04); }
        .cart-badge {
            position: absolute; top: -4px; right: -4px;
            min-width: 18px; height: 18px;
            padding: 0 5px;
            background: #5b7553; color: #fff;
            font-family: 'Inter Tight', sans-serif;
            font-size: 9.5px; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 999px;
            box-shadow: 0 0 0 2px #fbfaf7;
        }
        .cart-badge::before {
            content:''; position: absolute; inset: -2px; border-radius: 999px;
            box-shadow: 0 0 0 0 rgba(91,117,83,.5);
            animation: pingSoft 2.4s ease-out infinite;
        }
        @keyframes pingSoft {
            0%   { box-shadow: 0 0 0 0  rgba(91,117,83,.5); }
            70%  { box-shadow: 0 0 0 8px rgba(91,117,83,0);  }
            100% { box-shadow: 0 0 0 0  rgba(91,117,83,0);  }
        }

        /* Account stamp — clickable card → /profile (textile-tag style) */
        .account-stamp {
            position: relative;
            display: inline-flex; align-items: center; gap: .65rem;
            padding: 5px 12px 5px 5px;
            background: rgba(244,246,243,.5);
            border: 1px solid transparent;
            border-radius: 999px;
            transition: background .2s ease, border-color .2s ease, padding .25s cubic-bezier(.2,.8,.2,1);
        }
        .account-stamp:hover { background: #fff; border-color: #d8d8d4; padding-right: 14px; }
        .account-stamp .avatar-frame {
            position: relative;
            width: 30px; height: 30px;
            flex-shrink: 0;
        }
        .account-stamp .avatar {
            width: 100%; height: 100%;
            background: #fff;
            border: 1px solid #d8d8d4;
            display: inline-flex; align-items: center; justify-content: center;
            font-family: 'Fraunces', serif; font-weight: 600; font-size: 13px;
            color: #1a1a16;
        }
        .account-stamp .avatar-frame::before,
        .account-stamp .avatar-frame::after {
            content:''; position: absolute; width: 5px; height: 5px;
            border: 1px solid #5b7553; pointer-events: none;
        }
        .account-stamp .avatar-frame::before { top: -2px; left: -2px; border-right: 0; border-bottom: 0; }
        .account-stamp .avatar-frame::after  { bottom: -2px; right: -2px; border-left: 0; border-top: 0; }
        .account-stamp .meta {
            display: none; line-height: 1.1;
        }
        @media (min-width: 768px) {
            .account-stamp .meta { display: flex; flex-direction: column; }
        }
        .account-stamp .meta .name {
            font-family: 'Fraunces', serif; font-weight: 600;
            font-size: 13px; color: #1a1a16; max-width: 140px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .account-stamp .meta .role {
            font-family: 'Inter Tight', sans-serif;
            font-size: 9px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.22em;
            color: #6b6b63;
            margin-top: 2px;
        }

        /* Logout pill — always visible, never hidden in a dropdown */
        .exit-form { margin: 0; }
        .exit-pill {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: 8px 14px;
            border: 1px solid #d8d8d4;
            border-radius: 999px;
            background: #fff;
            color: #54544c;
            font-family: 'Inter Tight', sans-serif;
            font-size: 10.5px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.24em;
            cursor: pointer;
            transition: color .2s ease, border-color .2s ease, background .2s ease, box-shadow .25s ease;
        }
        .exit-pill svg { width: 13px; height: 13px; transition: transform .3s cubic-bezier(.2,.8,.2,1); }
        .exit-pill:hover {
            color: #1a1a16; border-color: #1a1a16;
            box-shadow: 0 0 0 4px rgba(26,26,22,.05);
        }
        .exit-pill:hover svg { transform: translateX(3px); }

        /* Guest entry — login link + register pill */
        .guest-link {
            font-family: 'Inter Tight', sans-serif;
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.24em;
            color: #54544c;
            transition: color .2s ease;
        }
        .guest-link:hover { color: #1a1a16; }
        .guest-cta {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: 9px 18px;
            background: #1a1a16; color: #fff;
            border-radius: 999px;
            font-family: 'Inter Tight', sans-serif;
            font-size: 10.5px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.28em;
            transition: box-shadow .25s ease;
        }
        .guest-cta:hover { box-shadow: 0 0 0 5px rgba(26,26,22,.06), 0 1px 0 0 #5b7553; }

        /* Mobile hamburger */
        .icon-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px;
            border: 1px solid #d8d8d4;
            border-radius: 999px;
            background: #fff;
            color: #54544c;
            transition: color .2s ease, border-color .2s ease;
        }
        .icon-btn:hover { color: #1a1a16; border-color: #1a1a16; }
        .icon-btn svg { width: 16px; height: 16px; }

        /* Mobile panel */
        .mobile-panel { max-height: 0; overflow: hidden; transition: max-height .35s ease; }
        .mobile-panel.is-open { max-height: 640px; }
        .mobile-link {
            display: flex; align-items: center; gap: .65rem;
            padding: 11px 14px;
            font-family: 'Inter Tight', sans-serif;
            font-size: 13px; font-weight: 500;
            color: #54544c;
            border-bottom: 1px solid #eeeeec;
            transition: background .15s ease, color .15s ease;
        }
        .mobile-link:hover { color: #1a1a16; background: rgba(244,246,243,.5); }
        .mobile-link.is-active {
            color: #1a1a16;
            font-family: 'Fraunces', serif;
            font-style: italic; font-weight: 500;
        }
        .mobile-link svg { width: 14px; height: 14px; color: #8a8a82; }
        .mobile-link.is-danger { color: #b91c1c; }
        .mobile-link.is-danger svg { color: #b91c1c; }
        .mobile-section {
            padding: 12px 14px 6px;
            font-family: 'Fraunces', serif;
            font-style: italic; font-weight: 500;
            font-size: 10px; text-transform: uppercase; letter-spacing: 0.32em;
            color: #8a8a82;
            display: flex; align-items: center; gap: .5rem;
        }
        .mobile-section::before { content:''; width:10px; height:1px; background:#5b7553; }

        @media (prefers-reduced-motion: reduce) {
            .nav-link::after, #site-header, .mobile-panel, .account-stamp, .exit-pill { transition: none !important; }
            .cart-badge::before { animation: none !important; }
        }
    </style>
    @stack('styles')
</head>
<body class="bg-white text-ink-900 min-h-screen flex flex-col">

@php
    $cartCount = 0; // TODO: replace with real cart count when cart is wired
    $u = auth()->user();
@endphp

<header id="site-header" class="sticky top-0 z-40 bg-white/85 backdrop-blur-md border-b border-ink-100/80">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between gap-6">
        {{-- Logo --}}
        <a href="{{ route('home') }}" class="brand-mark">
            <svg class="star" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                <rect x="6" y="6" width="16" height="16" stroke="currentColor" stroke-width="1.4"/>
                <rect x="6" y="6" width="16" height="16" stroke="currentColor" stroke-width="1.4" transform="rotate(45 14 14)"/>
                <circle cx="14" cy="14" r="2" fill="currentColor"/>
            </svg>
            <span class="wordmark">Labasa<em>.</em></span>
        </a>

        {{-- Desktop nav --}}
        <nav class="hidden md:flex items-center gap-9">
            <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'is-active' : '' }}">Beranda</a>
            <a href="{{ route('shop.index') }}" class="nav-link {{ request()->routeIs('shop.*') ? 'is-active' : '' }}">Belanja</a>
            @auth
                @if($u->isPembeli())
                    <a href="{{ route('customer.orders.index') }}" class="nav-link {{ request()->routeIs('customer.orders.*') ? 'is-active' : '' }}">Pesanan</a>
                    <a href="{{ route('customer.chat.index') }}" class="nav-link {{ request()->routeIs('customer.chat.*') ? 'is-active' : '' }}">Chat</a>
                @else
                    <a href="{{ route('admin.dashboard') }}" class="nav-link">Dashboard Admin</a>
                @endif
            @endauth
        </nav>

        {{-- Right cluster --}}
        <div class="flex items-center gap-2.5">
            @auth
                @if($u->isPembeli())
                    <a href="{{ route('cart.index') }}" class="pill-cart" aria-label="Keranjang">
                        <svg fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.5 5h13"/>
                            <circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>
                        </svg>
                        <span class="hidden sm:inline">keranjang</span>
                        @if($cartCount > 0)
                            <span class="cart-badge">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
                        @endif
                    </a>
                @endif

                {{-- Account stamp — clickable, goes to /profile --}}
                <a href="{{ route('profile.edit') }}" class="account-stamp {{ request()->routeIs('profile.*') ? 'is-active' : '' }}" title="Profil saya">
                    <span class="avatar-frame">
                        <span class="avatar">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                    </span>
                    <span class="meta">
                        <span class="name">{{ $u->name }}</span>
                        <span class="role">{{ $u->role }}</span>
                    </span>
                </a>

                {{-- Logout — always visible, prominent pill --}}
                <form action="{{ route('logout') }}" method="POST" class="exit-form">
                    @csrf
                    <button type="submit" class="exit-pill" title="Keluar dari akun">
                        <span class="hidden sm:inline">keluar</span>
                        <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" viewBox="0 0 24 24">
                            <path d="M17 16l4-4m0 0l-4-4m4 4H7"/>
                        </svg>
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="hidden sm:inline-flex guest-link px-3 py-2">masuk</a>
                <a href="{{ route('register') }}" class="guest-cta">
                    daftar
                    <svg fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            @endauth

            {{-- Mobile hamburger --}}
            <button id="mobile-trigger" type="button" class="icon-btn md:hidden" aria-label="Menu" aria-expanded="false">
                <svg id="mobile-icon-open" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16"/></svg>
                <svg id="mobile-icon-close" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M6 18L18 6"/></svg>
            </button>
        </div>
    </div>

    {{-- Mobile panel --}}
    <div id="mobile-panel" class="mobile-panel md:hidden border-t border-ink-100 bg-white">
        <nav>
            <p class="mobile-section">Toko</p>
            <a href="{{ route('home') }}"      class="mobile-link {{ request()->routeIs('home')   ? 'is-active' : '' }}">Beranda</a>
            <a href="{{ route('shop.index') }}" class="mobile-link {{ request()->routeIs('shop.*') ? 'is-active' : '' }}">Belanja</a>

            @auth
                @if($u->isPembeli())
                    <p class="mobile-section">Akun</p>
                    <a href="{{ route('customer.orders.index') }}" class="mobile-link {{ request()->routeIs('customer.orders.*') ? 'is-active' : '' }}">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Pesanan Saya
                    </a>
                    <a href="{{ route('cart.index') }}" class="mobile-link">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.5 5h13"/></svg>
                        Keranjang
                    </a>
                    <a href="{{ route('customer.chat.index') }}" class="mobile-link {{ request()->routeIs('customer.chat.*') ? 'is-active' : '' }}">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                        Chat dengan Penjual
                    </a>
                    <a href="{{ route('profile.edit') }}" class="mobile-link {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0116 0v1"/></svg>
                        Profil Saya
                    </a>
                @else
                    <p class="mobile-section">Admin</p>
                    <a href="{{ route('admin.dashboard') }}" class="mobile-link">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h4v-6h6v6h4V10"/></svg>
                        Dashboard Admin
                    </a>
                    <a href="{{ route('profile.edit') }}" class="mobile-link {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0116 0v1"/></svg>
                        Profil Saya
                    </a>
                @endif

                <p class="mobile-section">Sesi</p>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="mobile-link is-danger" style="width:100%; text-align:left; background:none; border:0; border-bottom:1px solid #eeeeec;">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Keluar dari Akun
                    </button>
                </form>
            @else
                <p class="mobile-section">Akun</p>
                <a href="{{ route('login') }}" class="mobile-link">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15 12H3M3 12l4-4M3 12l4 4"/><path d="M15 5h4a2 2 0 012 2v10a2 2 0 01-2 2h-4"/></svg>
                    Masuk
                </a>
                <a href="{{ route('register') }}" class="mobile-link" style="color:#1a1a16; font-family:'Fraunces',serif; font-style:italic; font-weight:500;">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    Daftar Akun Baru
                </a>
            @endauth
        </nav>
    </div>
</header>

<main class="flex-1">
    @if (session('success'))
        <div id="toast" class="fixed top-24 right-6 z-50 bg-emerald-600 text-white px-4 py-3 rounded-lg shadow-lg text-sm font-medium">
            ✓ {{ session('success') }}
        </div>
        <script>setTimeout(() => document.getElementById('toast')?.remove(), 3500);</script>
    @endif

    @yield('content')
</main>

<footer class="border-t border-ink-100 mt-20">
    <div class="max-w-7xl mx-auto px-6 py-12 grid md:grid-cols-3 gap-8 text-sm text-ink-600">
        <div>
            <p class="font-display text-xl font-semibold text-ink-900 mb-2">Labasa.</p>
            <p>Baju muslim — dijahit dengan rapi, dipakai dengan bangga.</p>
        </div>
        <div>
            <p class="font-semibold text-ink-900 mb-2">Kontak</p>
            <p>WhatsApp: 0812-0000-0000</p>
            <p>Email: hello@labasa.id</p>
        </div>
        <div>
            <p class="font-semibold text-ink-900 mb-2">Toko</p>
            <p>Surabaya, Jawa Timur</p>
            <p>Senin–Sabtu, 09.00–17.00</p>
        </div>
    </div>
    <div class="border-t border-ink-100 py-4 text-center text-xs text-ink-500">
        © {{ date('Y') }} Toko Labasa. Tugas Akhir Rivaldy Reza Permana — UBAYA.
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    // Header polish: scroll shadow + mobile menu (no more user dropdown — logout lives in the header always-visible)
    (function () {
        const $header = $('#site-header');
        const onScroll = () => $header.toggleClass('is-scrolled', window.scrollY > 4);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        const $mTrigger = $('#mobile-trigger');
        const $mPanel = $('#mobile-panel');
        $mTrigger.on('click', function () {
            const open = $mPanel.toggleClass('is-open').hasClass('is-open');
            $mTrigger.attr('aria-expanded', open ? 'true' : 'false');
            $('#mobile-icon-open').toggleClass('hidden', open);
            $('#mobile-icon-close').toggleClass('hidden', !open);
        });
    })();
</script>
@stack('scripts')

</body>
</html>
