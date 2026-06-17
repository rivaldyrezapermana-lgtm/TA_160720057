@php
    $u = auth()->user();
    $isAdmin = $u?->isAdmin() ?? false;

    // Active vs. inactive link styling.
    $linkClass = fn ($pattern) => request()->routeIs($pattern)
        ? 'bg-emerald-50 text-emerald-700 font-medium'
        : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900';

    $adminUnread = \App\Models\ChatMessage::query()
        ->where('is_read', false)
        ->whereExists(function ($q) {
            $q->from('chat_threads')
              ->whereColumn('chat_threads.id', 'chat_messages.thread_id')
              ->whereColumn('chat_threads.customer_id', 'chat_messages.sender_id');
        })
        ->count();
@endphp

<aside id="admin-aside" class="w-64 flex-shrink-0 flex flex-col h-screen sticky top-0 bg-white border-r border-slate-200">
    {{-- Brand --}}
    <div class="sidebar-brandbar h-16 flex-shrink-0 flex items-center justify-between gap-2 px-4 border-b border-slate-200">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand-text text-lg font-semibold text-slate-900">
            Labasa <span class="font-normal text-slate-400">Admin</span>
        </a>
        <button id="sidebar-toggle" type="button" title="Lipat sidebar" aria-label="Lipat sidebar"
                class="p-1.5 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-0.5">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.dashboard') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h4v-6h6v6h4V10"/></svg>
            <span class="sidebar-label">Dashboard</span>
        </a>

        <p class="sidebar-section px-3 pt-5 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Master Data</p>

        <a href="{{ route('admin.categories.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.categories.*') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><circle cx="7" cy="7" r="1.2" fill="currentColor"/></svg>
            <span class="sidebar-label">Kategori</span>
        </a>

        <a href="{{ route('admin.products.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.products.*') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
            <span class="sidebar-label">Produk</span>
        </a>

        <a href="{{ route('admin.materials.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.materials.*') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 7l10 5 10-5-10-5z"/><path d="M2 12l10 5 10-5"/><path d="M2 17l10 5 10-5"/></svg>
            <span class="sidebar-label">Bahan Baku</span>
        </a>

        <a href="{{ route('admin.suppliers.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.suppliers.*') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 18V6a2 2 0 00-2-2H4a2 2 0 00-2 2v11a1 1 0 001 1h2"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/><path d="M15 18h-2M19 18h2a1 1 0 001-1v-3.65a1 1 0 00-.22-.62l-3.48-4.35A1 1 0 0017.52 8H14"/></svg>
            <span class="sidebar-label">Supplier</span>
        </a>

        @if ($isAdmin)
            <a href="{{ route('admin.users.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.users.*') }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                <span class="sidebar-label">User</span>
            </a>
        @endif

        <p class="sidebar-section px-3 pt-5 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Operasional</p>

        <a href="{{ route('admin.purchases.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.purchases.*') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            <span class="sidebar-label">Pembelian Bahan</span>
        </a>

        <a href="{{ route('admin.productions.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.productions.*') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M10 8.5l5 3.5-5 3.5z" fill="currentColor" stroke="none"/></svg>
            <span class="sidebar-label">Produksi</span>
        </a>

        <a href="{{ route('admin.production-machines.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.production-machines.*') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            <span class="sidebar-label">Mesin Produksi</span>
        </a>

        <a href="{{ route('admin.recommendations.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.recommendations.*') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 18h6"/><path d="M10 21h4"/><path d="M12 3a6 6 0 00-3 11.13c.37.27.66.62.83 1.04.19.59.56.83.96.83h2.42c.4 0 .77-.24.96-.83.17-.42.46-.77.83-1.04A6 6 0 0012 3z"/></svg>
            <span class="sidebar-label">Rekomendasi</span>
            <span class="sidebar-badge ml-auto text-xs px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700">Fuzzy</span>
        </a>

        <p class="sidebar-section px-3 pt-5 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Penjualan</p>

        <a href="{{ route('admin.orders.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.orders.*') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            <span class="sidebar-label">Pesanan</span>
        </a>

        <a href="{{ route('admin.chat.index') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.chat.*') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
            <span class="sidebar-label">Live Chat</span>
            @if ($adminUnread > 0)
                <span class="sidebar-badge ml-auto text-xs font-medium px-1.5 py-0.5 rounded-full bg-emerald-600 text-white">{{ $adminUnread > 99 ? '99+' : $adminUnread }}</span>
            @endif
        </a>

        <p class="sidebar-section px-3 pt-5 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Laporan</p>

        <a href="{{ route('admin.reports.production') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.reports.production') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 20v-4M12 20V10M18 20V4"/></svg>
            <span class="sidebar-label">Lap. Produksi</span>
        </a>

        <a href="{{ route('admin.reports.sales') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.reports.sales') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 6l-9.5 9.5-5-5L1 18"/><path d="M16 6h6v6"/></svg>
            <span class="sidebar-label">Lap. Penjualan</span>
        </a>

        <a href="{{ route('admin.reports.inventory') }}" class="sidebar-link flex items-center gap-3 px-3 py-2 rounded-md text-sm {{ $linkClass('admin.reports.inventory') }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12.89 1.45l8 4A2 2 0 0122 7.24v9.53a2 2 0 01-1.11 1.79l-8 4a2 2 0 01-1.78 0l-8-4a2 2 0 01-1.11-1.79V7.24a2 2 0 011.11-1.79l8-4a2 2 0 011.78 0z"/><path d="M2.32 6.16L12 11l9.68-4.84M12 22.76V11"/></svg>
            <span class="sidebar-label">Lap. Inventori</span>
        </a>
    </nav>

    {{-- User --}}
    <div class="sidebar-user flex-shrink-0 flex items-center gap-3 p-3 border-t border-slate-200">
        <span class="w-9 h-9 flex-shrink-0 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm font-semibold">
            {{ strtoupper(mb_substr($u?->name ?? 'A', 0, 1)) }}
        </span>
        <div class="sidebar-user-meta min-w-0">
            <p class="text-sm font-medium text-slate-900 truncate">{{ $u?->name ?? 'Admin' }}</p>
            <p class="text-xs text-slate-500 capitalize">{{ $u?->role ?? 'admin' }}</p>
        </div>
    </div>
</aside>
