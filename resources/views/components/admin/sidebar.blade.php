@php
    $u = auth()->user();
    $isAdmin = $u?->isAdmin() ?? false;
    $isOn = fn ($pattern) => request()->routeIs($pattern) ? 'is-active' : '';

    $adminUnread = \App\Models\ChatMessage::query()
        ->where('is_read', false)
        ->whereExists(function ($q) {
            $q->from('chat_threads')
              ->whereColumn('chat_threads.id', 'chat_messages.thread_id')
              ->whereColumn('chat_threads.customer_id', 'chat_messages.sender_id');
        })
        ->count();
@endphp

{{-- Sidebar — Atelier (textile-editorial). Self-contained styles, scoped under #admin-aside, win over the layout's <style> block by ID specificity. --}}
<style>
    #admin-aside {
        background:
            radial-gradient(1px 1px at 22% 28%, rgba(91,117,83,.06) 0, transparent 40%),
            radial-gradient(1px 1px at 78% 72%, rgba(26,26,22,.05) 0, transparent 40%),
            linear-gradient(180deg, #fbfaf7 0%, #f6f5f1 100%);
        border-right: 1px solid #eeeeec;
    }

    /* ── Brand block ──────────────────────────────────── */
    #admin-aside .brand-block {
        position: relative;
        padding: 1.5rem 1rem 1.25rem;
        border-bottom: 1px solid #eeeeec;
        background: linear-gradient(180deg, rgba(244,246,243,.55), transparent 92%);
        overflow: hidden;
        gap: .75rem;
    }
    #admin-aside .brand-watermark {
        position: absolute; top: -22px; right: -22px;
        width: 130px; height: 130px;
        color: #5b7553;
        opacity: .09;
        pointer-events: none;
        transition: transform .8s cubic-bezier(.2,.8,.2,1), opacity .35s ease;
    }
    #admin-aside .brand-block:hover .brand-watermark { transform: rotate(22.5deg) scale(1.04); opacity: .14; }

    #admin-aside .brand-mark { position: relative; z-index: 2; }
    #admin-aside .brand-mark .star {
        width: 2.25rem; height: 2.25rem; flex-shrink: 0; color: #1a1a16;
        transition: transform .5s cubic-bezier(.2,.8,.2,1);
    }
    #admin-aside .brand-mark:hover .star { transform: rotate(22.5deg); }

    #admin-aside .wordmark {
        font-family: 'Fraunces', serif;
        font-optical-sizing: auto;
        font-variation-settings: "opsz" 144;
        font-weight: 600;
        font-size: 1.55rem;
        line-height: 1;
        letter-spacing: -0.012em;
        color: #1a1a16;
    }
    #admin-aside .wordmark em { font-style: italic; color: #48604a; font-weight: 500; }
    #admin-aside .brand-caption {
        font-family: 'Inter Tight', sans-serif;
        font-size: 9.5px;
        text-transform: uppercase;
        letter-spacing: 0.24em;
        color: #8a8a82;
        margin-top: 7px;
        font-weight: 500;
    }
    #admin-aside .brand-caption .dot {
        display: inline-block; width: 3px; height: 3px;
        background: #5b7553; border-radius: 50%;
        vertical-align: middle; margin: 0 7px;
    }

    /* Collapse toggle */
    #admin-aside .collapse-btn {
        position: relative; z-index: 2;
        width: 1.85rem; height: 1.85rem;
        border: 1px solid #d8d8d4; border-radius: 6px;
        background: #fff; color: #6b6b63;
        display: inline-flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        transition: border-color .15s ease, color .15s ease, box-shadow .2s ease;
    }
    #admin-aside .collapse-btn:hover {
        border-color: #1a1a16; color: #1a1a16;
        box-shadow: 0 0 0 4px rgba(26,26,22,.05);
    }
    #admin-aside .collapse-btn svg { transition: transform .25s ease; }
    #admin-aside[data-collapsed="true"] .collapse-btn svg { transform: rotate(180deg); }

    /* ── Nav ──────────────────────────────────────────── */
    #admin-aside nav { padding: .85rem .75rem 1rem; }

    #admin-aside .nav-section {
        font-family: 'Fraunces', serif;
        font-size: 10.5px;
        font-style: italic;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.32em;
        color: #6b6b63;
        padding: 1.1rem .5rem .55rem;
        margin: 0;
        display: flex; align-items: center; gap: .65rem;
    }
    #admin-aside .nav-section::before {
        content: ''; width: 12px; height: 1px; background: #5b7553; flex-shrink: 0;
    }
    #admin-aside .nav-section::after {
        content: ''; flex: 1; height: 1px;
        background: linear-gradient(90deg, #d8d8d4, transparent);
    }

    /* Override layout .nav-link */
    #admin-aside .nav-link {
        position: relative;
        display: flex; align-items: center; gap: .8rem;
        padding: .5rem .65rem;
        border-radius: 0;
        font-family: 'Inter Tight', sans-serif;
        font-size: 13.5px;
        font-weight: 450;
        color: #54544c;
        background: transparent;
        transition: color .2s ease, background .2s ease;
    }
    #admin-aside .nav-link::before { display: none; }   /* kill layout's gradient rail */

    #admin-aside .nav-link .nav-icon {
        width: 18px; height: 18px;
        background: transparent;
        border-radius: 0;
        color: #8a8a82;
        flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        transition: color .2s ease, transform .25s cubic-bezier(.2,.8,.2,1);
    }
    #admin-aside .nav-link .nav-icon svg { width: 18px; height: 18px; }

    #admin-aside .nav-link .label-text {
        flex: 1;
        transition: transform .25s cubic-bezier(.2,.8,.2,1);
        will-change: transform;
        min-width: 0;
    }

    /* Hover */
    #admin-aside .nav-link:hover { color: #1a1a16; background: rgba(255,255,255,.55); }
    #admin-aside .nav-link:hover .nav-icon { color: #48604a; background: transparent; }
    #admin-aside .nav-link:hover .label-text { transform: translateX(2px); }

    /* Active — "ribbon" hanging into the gutter, label switches to Fraunces italic */
    #admin-aside .nav-link.is-active {
        background: linear-gradient(90deg, rgba(244,246,243,1) 0%, rgba(244,246,243,.35) 95%, transparent 100%);
        color: #1a1a16;
        font-weight: 500;
    }
    #admin-aside .nav-link.is-active::after {
        content: '';
        position: absolute;
        left: -.75rem; top: 50%;
        width: 4px; height: 24px;
        margin-top: -12px;
        background: #1a1a16;
        border-radius: 0 2px 2px 0;
    }
    #admin-aside .nav-link.is-active .nav-icon { color: #1a1a16; background: transparent; }
    #admin-aside .nav-link.is-active .label-text {
        font-family: 'Fraunces', serif;
        font-style: italic;
        font-weight: 500;
        font-size: 14.75px;
        letter-spacing: -0.005em;
    }

    /* ── Badges ───────────────────────────────────────── */
    #admin-aside .badge-pill { margin-left: auto; flex-shrink: 0; }

    #admin-aside .badge-fuzzy {
        font-family: 'Fraunces', serif;
        font-style: italic; font-weight: 500;
        font-size: 10.5px;
        letter-spacing: 0.02em;
        color: #3a4d3d;
        background: transparent;
        border: 1px solid rgba(91,117,83,.32);
        padding: 1px 8px;
        border-radius: 999px;
        ring: 0;
    }
    #admin-aside .nav-link:hover .badge-fuzzy { border-color: #5b7553; background: rgba(244,246,243,.6); }

    #admin-aside .badge-chat {
        display: inline-flex; align-items: center; gap: 4px;
        font-family: 'Inter Tight', sans-serif;
        font-size: 10.5px; font-weight: 600;
        color: #fff; background: #1a1a16;
        padding: 2px 8px 2px 6px; border-radius: 999px;
    }
    #admin-aside .badge-chat .crescent { width: 9px; height: 9px; color: #c0d4b0; }

    /* ── Tooltip (collapsed-only, layout already shows it) ── */
    #admin-aside .label-tooltip {
        font-family: 'Inter Tight', sans-serif;
        font-size: 11.5px; font-weight: 500;
        letter-spacing: 0.01em;
        background: #1a1a16;
    }

    /* ── User card ─────────────────────────────────────── */
    #admin-aside .user-card {
        position: relative;
        border-top: 1px solid #eeeeec;
        padding: 1rem 1rem 1.1rem;
        display: flex; align-items: center; gap: .85rem;
        background: rgba(244,246,243,.35);
    }
    #admin-aside .user-card .avatar-frame { position: relative; width: 2.5rem; height: 2.5rem; flex-shrink: 0; }
    #admin-aside .user-card .avatar {
        width: 100%; height: 100%;
        background: #fff;
        border: 1px solid #d8d8d4;
        border-radius: 0;
        display: inline-flex; align-items: center; justify-content: center;
        font-family: 'Fraunces', serif; font-weight: 600; font-size: 1.05rem;
        color: #1a1a16;
    }
    /* textile-tag corner ticks */
    #admin-aside .user-card .avatar-frame::before,
    #admin-aside .user-card .avatar-frame::after {
        content: '';
        position: absolute;
        width: 7px; height: 7px;
        border: 1px solid #5b7553;
        pointer-events: none;
    }
    #admin-aside .user-card .avatar-frame::before { top: -3px; left: -3px; border-right: 0; border-bottom: 0; }
    #admin-aside .user-card .avatar-frame::after  { bottom: -3px; right: -3px; border-left: 0; border-top: 0; }

    #admin-aside .user-card .user-name {
        font-family: 'Fraunces', serif; font-weight: 600; font-size: 13.5px;
        color: #1a1a16; line-height: 1.2; margin: 0;
    }
    #admin-aside .user-card .role-chip {
        font-family: 'Inter Tight', sans-serif;
        font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.16em;
        color: #3a4d3d; font-weight: 600; background: none; padding: 0;
    }
    #admin-aside .user-card .meta-link {
        font-family: 'Inter Tight', sans-serif;
        font-size: 10.5px; color: #6b6b63;
        display: inline-flex; align-items: center; gap: 3px;
        transition: color .15s ease;
    }
    #admin-aside .user-card .meta-link:hover { color: #1a1a16; }

    /* ── Collapsed-state extras ──────────────────────── */
    #admin-aside[data-collapsed="true"] .brand-watermark,
    #admin-aside[data-collapsed="true"] .brand-caption { opacity: 0; }
    #admin-aside[data-collapsed="true"] .nav-link.is-active::after { left: 0; }
    #admin-aside[data-collapsed="true"] .nav-link { padding-left: 0; padding-right: 0; }
    #admin-aside[data-collapsed="true"] .nav-link.is-active .label-text { font-size: 0; }
    #admin-aside[data-collapsed="true"] .brand-block { padding-left: .75rem; padding-right: .75rem; }
    #admin-aside[data-collapsed="true"] .user-card { padding-left: .75rem; padding-right: .75rem; }
</style>

<aside id="admin-aside" class="w-64 flex flex-col flex-shrink-0">
    {{-- Brand block --}}
    <div class="brand-block flex items-center">
        {{-- Geometric watermark — overlapping squares = octagram (8-point Islamic star) --}}
        <svg class="brand-watermark" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
            <rect x="20" y="20" width="60" height="60"/>
            <rect x="20" y="20" width="60" height="60" transform="rotate(45 50 50)"/>
            <circle cx="50" cy="50" r="22"/>
            <circle cx="50" cy="50" r="6"/>
        </svg>

        <a href="{{ route('admin.dashboard') }}" class="brand-mark group flex items-center gap-3 min-w-0 flex-1">
            {{-- 8-point star monogram (two overlapping squares) --}}
            <svg class="star" viewBox="0 0 36 36" fill="none" aria-hidden="true">
                <rect x="7" y="7" width="22" height="22" stroke="currentColor" stroke-width="1.4"/>
                <rect x="7" y="7" width="22" height="22" stroke="currentColor" stroke-width="1.4" transform="rotate(45 18 18)"/>
                <circle cx="18" cy="18" r="2.6" fill="currentColor"/>
            </svg>
            <span class="brand-text flex flex-col min-w-0">
                <span class="wordmark truncate">Labasa<em>.</em></span>
                <span class="brand-caption truncate">Atelier<span class="dot"></span>Production</span>
            </span>
        </a>

        <button id="sidebar-toggle" type="button" class="collapse-btn" aria-label="Lipat sidebar" title="Lipat sidebar">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15 19l-7-7 7-7"/></svg>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto">
        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ $isOn('admin.dashboard') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h4v-6h6v6h4V10"/></svg>
            </span>
            <span class="label-text">Dashboard</span>
            <span class="label-tooltip">Dashboard</span>
        </a>

        <p class="nav-section"><span>Master Data</span></p>

        <a href="{{ route('admin.categories.index') }}" class="nav-link {{ $isOn('admin.categories.*') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><circle cx="7" cy="7" r="1.2" fill="currentColor"/></svg>
            </span>
            <span class="label-text">Kategori</span>
            <span class="label-tooltip">Kategori</span>
        </a>

        <a href="{{ route('admin.products.index') }}" class="nav-link {{ $isOn('admin.products.*') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
            </span>
            <span class="label-text">Produk</span>
            <span class="label-tooltip">Produk</span>
        </a>

        <a href="{{ route('admin.materials.index') }}" class="nav-link {{ $isOn('admin.materials.*') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 7l10 5 10-5-10-5z"/><path d="M2 12l10 5 10-5"/><path d="M2 17l10 5 10-5"/></svg>
            </span>
            <span class="label-text">Bahan Baku</span>
            <span class="label-tooltip">Bahan Baku</span>
        </a>

        <a href="{{ route('admin.suppliers.index') }}" class="nav-link {{ $isOn('admin.suppliers.*') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 18V6a2 2 0 00-2-2H4a2 2 0 00-2 2v11a1 1 0 001 1h2"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/><path d="M15 18h-2M19 18h2a1 1 0 001-1v-3.65a1 1 0 00-.22-.62l-3.48-4.35A1 1 0 0017.52 8H14"/></svg>
            </span>
            <span class="label-text">Supplier</span>
            <span class="label-tooltip">Supplier</span>
        </a>

        @if ($isAdmin)
            <a href="{{ route('admin.users.index') }}" class="nav-link {{ $isOn('admin.users.*') }}">
                <span class="nav-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                </span>
                <span class="label-text">User</span>
                <span class="label-tooltip">User</span>
            </a>
        @endif

        <p class="nav-section"><span>Operasional</span></p>

        <a href="{{ route('admin.purchases.index') }}" class="nav-link {{ $isOn('admin.purchases.*') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            </span>
            <span class="label-text">Pembelian Bahan</span>
            <span class="label-tooltip">Pembelian Bahan</span>
        </a>

        <a href="{{ route('admin.productions.index') }}" class="nav-link {{ $isOn('admin.productions.*') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M10 8.5l5 3.5-5 3.5z" fill="currentColor" stroke="none"/></svg>
            </span>
            <span class="label-text">Produksi</span>
            <span class="label-tooltip">Produksi</span>
        </a>

        <a href="{{ route('admin.recommendations.index') }}" class="nav-link {{ $isOn('admin.recommendations.*') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 18h6"/><path d="M10 21h4"/><path d="M12 3a6 6 0 00-3 11.13c.37.27.66.62.83 1.04.19.59.56.83.96.83h2.42c.4 0 .77-.24.96-.83.17-.42.46-.77.83-1.04A6 6 0 0012 3z"/></svg>
            </span>
            <span class="label-text">Rekomendasi</span>
            <span class="badge-pill badge-fuzzy">Fuzzy</span>
            <span class="label-tooltip">Rekomendasi (Fuzzy)</span>
        </a>

        <p class="nav-section"><span>Penjualan</span></p>

        <a href="{{ route('admin.orders.index') }}" class="nav-link {{ $isOn('admin.orders.*') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            </span>
            <span class="label-text">Pesanan</span>
            <span class="label-tooltip">Pesanan</span>
        </a>

        <a href="{{ route('admin.chat.index') }}" class="nav-link {{ $isOn('admin.chat.*') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
            </span>
            <span class="label-text">Live Chat</span>
            @if ($adminUnread > 0)
                <span class="badge-pill badge-chat">
                    <svg class="crescent" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M10 6.5a4.5 4.5 0 1 1-4.5-4.5 3.5 3.5 0 0 0 4.5 4.5z"/></svg>
                    {{ $adminUnread > 99 ? '99+' : $adminUnread }}
                </span>
            @endif
            <span class="label-tooltip">Live Chat</span>
        </a>

        <p class="nav-section"><span>Laporan</span></p>

        <a href="{{ route('admin.reports.production') }}" class="nav-link {{ $isOn('admin.reports.production') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 20v-4M12 20V10M18 20V4"/></svg>
            </span>
            <span class="label-text">Lap. Produksi</span>
            <span class="label-tooltip">Lap. Produksi</span>
        </a>

        <a href="{{ route('admin.reports.sales') }}" class="nav-link {{ $isOn('admin.reports.sales') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 6l-9.5 9.5-5-5L1 18"/><path d="M16 6h6v6"/></svg>
            </span>
            <span class="label-text">Lap. Penjualan</span>
            <span class="label-tooltip">Lap. Penjualan</span>
        </a>

        <a href="{{ route('admin.reports.inventory') }}" class="nav-link {{ $isOn('admin.reports.inventory') }}">
            <span class="nav-icon">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12.89 1.45l8 4A2 2 0 0122 7.24v9.53a2 2 0 01-1.11 1.79l-8 4a2 2 0 01-1.78 0l-8-4a2 2 0 01-1.11-1.79V7.24a2 2 0 011.11-1.79l8-4a2 2 0 011.78 0z"/><path d="M2.32 6.16L12 11l9.68-4.84M12 22.76V11"/></svg>
            </span>
            <span class="label-text">Lap. Inventori</span>
            <span class="label-tooltip">Lap. Inventori</span>
        </a>
    </nav>

    {{-- User card --}}
    <div class="user-card">
        <div class="avatar-frame">
            <span class="avatar">{{ strtoupper(mb_substr($u?->name ?? 'A', 0, 1)) }}</span>
        </div>
        <div class="user-meta min-w-0 flex-1">
            <p class="user-name truncate">{{ $u?->name ?? 'Admin' }}</p>
            <div class="flex items-center gap-2.5 mt-1">
                <span class="role-chip">{{ $u?->role ?? 'admin' }}</span>
                <span class="text-ink-300">·</span>
                <a href="{{ route('home') }}" target="_blank" rel="noopener" class="meta-link" title="Buka storefront">
                    storefront
                    <svg width="9" height="9" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>
        </div>
    </div>
</aside>
