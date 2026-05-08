@php
    /* Indonesian date — manual mapping (no Carbon locale dependency) */
    $now = now();
    $dayMap   = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
    $monthMap = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
    $dayName    = $dayMap[$now->format('l')]   ?? $now->format('l');
    $monthShort = mb_substr($monthMap[$now->format('F')] ?? $now->format('M'), 0, 3);
    $dateDetail = $now->format('j').' '.$monthShort.' '.$now->format('Y');

    /* Section kicker derived from the current admin route */
    $r = request()->route()?->getName() ?? '';
    $sectionKicker = match (true) {
        str_starts_with($r, 'admin.dashboard')          => 'Beranda',
        str_starts_with($r, 'admin.categories'),
        str_starts_with($r, 'admin.products'),
        str_starts_with($r, 'admin.materials'),
        str_starts_with($r, 'admin.suppliers'),
        str_starts_with($r, 'admin.users')              => 'Master Data',
        str_starts_with($r, 'admin.purchases'),
        str_starts_with($r, 'admin.productions'),
        str_starts_with($r, 'admin.recommendations')    => 'Operasional',
        str_starts_with($r, 'admin.orders'),
        str_starts_with($r, 'admin.chat')               => 'Penjualan',
        str_starts_with($r, 'admin.reports')            => 'Laporan',
        default                                          => 'Atelier',
    };
@endphp

{{-- Topbar — Atelier (textile-editorial). Self-contained styles, scoped under #admin-topbar. --}}
<style>
    #admin-topbar {
        position: relative;
        background:
            radial-gradient(1px 1px at 92% 28%, rgba(91,117,83,.06) 0, transparent 40%),
            radial-gradient(1px 1px at 8%  78%, rgba(26,26,22,.05) 0, transparent 40%),
            linear-gradient(180deg, #fbfaf7 0%, #f6f5f1 100%);
        border-bottom: 0;
    }

    #admin-topbar .topbar-inner {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 2.5rem;
        padding: 1.55rem 2.25rem 1.35rem 2rem;
        min-height: 92px;
    }

    /* ── Left: kicker + title cluster ─────────────── */
    #admin-topbar .topbar-left {
        display: flex; flex-direction: column; align-items: flex-start;
        gap: .55rem;
        min-width: 0; flex: 1;
        position: relative;
    }
    #admin-topbar .topbar-kicker {
        font-family: 'Fraunces', serif;
        font-style: italic; font-weight: 500;
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.34em;
        color: #6b6b63;
        display: inline-flex; align-items: center; gap: .65rem;
        padding-left: 0;
    }
    #admin-topbar .topbar-kicker::before {
        content: ''; width: 14px; height: 1px;
        background: #5b7553;
        flex-shrink: 0;
    }
    #admin-topbar .topbar-kicker::after {
        content: '';
        width: 4px; height: 4px;
        background: transparent;
        border: 1px solid #5b7553;
        transform: rotate(45deg);
        margin-left: .35rem;
        flex-shrink: 0;
    }

    /* The page yields its own breadcrumb + h1; we restyle them by descendant selector. */
    #admin-topbar .topbar-content { width: 100%; min-width: 0; }
    #admin-topbar .topbar-content h1 {
        font-family: 'Fraunces', serif !important;
        font-optical-sizing: auto;
        font-variation-settings: "opsz" 144;
        font-weight: 500 !important;
        font-size: 1.85rem;
        line-height: 1.05;
        letter-spacing: -0.02em;
        color: #1a1a16 !important;
        margin: 0;
    }
    /* Breadcrumb (yielded by pages) — restyle subtly */
    #admin-topbar .topbar-content nav[aria-label="Breadcrumb"] {
        font-family: 'Inter Tight', sans-serif;
        font-size: 11px;
        font-weight: 500;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: #8a8a82;
        margin-bottom: .55rem;
    }
    #admin-topbar .topbar-content nav[aria-label="Breadcrumb"] a {
        color: #8a8a82; transition: color .15s ease;
    }
    #admin-topbar .topbar-content nav[aria-label="Breadcrumb"] a:hover { color: #1a1a16; }
    #admin-topbar .topbar-content nav[aria-label="Breadcrumb"] span {
        color: #1a1a16;
        font-family: 'Fraunces', serif;
        font-style: italic;
        font-weight: 500;
        text-transform: none;
        letter-spacing: 0;
        font-size: 13px;
    }
    #admin-topbar .topbar-content nav[aria-label="Breadcrumb"] svg {
        width: 9px; height: 9px;
        margin: 0 .55rem;
        color: #d8d8d4;
    }

    /* ── Right: datestamp + divider + logout pill ─── */
    #admin-topbar .topbar-right {
        display: flex; align-items: center; gap: 1.25rem;
        flex-shrink: 0;
    }
    #admin-topbar .datestamp {
        display: flex; flex-direction: column; align-items: flex-end;
        line-height: 1;
        text-align: right;
    }
    #admin-topbar .datestamp .day-row {
        font-family: 'Fraunces', serif;
        font-style: italic; font-weight: 500;
        font-size: 1.05rem;
        color: #1a1a16;
        display: inline-flex; align-items: center; gap: .55rem;
    }
    #admin-topbar .datestamp .moon {
        width: 13px; height: 13px;
        color: #5b7553;
        display: inline-block;
    }
    #admin-topbar .datestamp .date-detail {
        font-family: 'Inter Tight', sans-serif;
        font-size: 10px; font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.22em;
        color: #8a8a82;
        margin-top: 4px;
    }

    #admin-topbar .topbar-divider {
        width: 1px; height: 36px;
        background: linear-gradient(180deg, transparent 0%, #d8d8d4 30%, #d8d8d4 70%, transparent 100%);
    }

    #admin-topbar .logout-form { margin: 0; }
    #admin-topbar .logout-btn {
        display: inline-flex; align-items: center; gap: .55rem;
        padding: .55rem 1rem .55rem .9rem;
        border: 1px solid #d8d8d4;
        border-radius: 999px;
        background: #fff;
        color: #54544c;
        font-family: 'Inter Tight', sans-serif;
        font-size: 10.5px; font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.22em;
        cursor: pointer;
        transition: border-color .2s ease, color .2s ease, background .2s ease, box-shadow .25s ease;
    }
    #admin-topbar .logout-btn svg {
        width: 13px; height: 13px;
        transition: transform .3s cubic-bezier(.2,.8,.2,1);
    }
    #admin-topbar .logout-btn:hover {
        border-color: #1a1a16; color: #1a1a16;
        background: #fbfaf7;
        box-shadow: 0 0 0 4px rgba(26,26,22,.05);
    }
    #admin-topbar .logout-btn:hover svg { transform: translateX(3px); }

    /* ── Selvage: textile-edge hairline punctuated by an octagram ── */
    #admin-topbar .selvage {
        position: relative;
        height: 1px;
        background: linear-gradient(90deg,
            rgba(216,216,212,0) 0%,
            #d8d8d4 28px,
            #d8d8d4 calc(100% - 4%),
            rgba(216,216,212,.25) 100%);
    }
    #admin-topbar .selvage-mark {
        position: absolute;
        left: 2rem;
        top: 50%;
        transform: translateY(-50%);
        background: #fbfaf7;
        padding: 2px 8px;
        display: inline-flex; align-items: center;
        line-height: 0;
    }
    #admin-topbar .selvage-mark svg {
        width: 11px; height: 11px;
        color: #5b7553;
    }
    /* Selvage signature — far right, optional small index counter */
    #admin-topbar .selvage-index {
        position: absolute;
        right: 2.25rem;
        top: 50%;
        transform: translateY(-50%);
        background: #fbfaf7;
        padding: 1px 8px;
        font-family: 'Fraunces', serif;
        font-style: italic;
        font-size: 9.5px;
        color: #8a8a82;
        letter-spacing: 0.04em;
    }

    /* ── Collapsed-sidebar consideration: nothing changes here, topbar is independent. ── */
</style>

<header id="admin-topbar">
    <div class="topbar-inner">
        <div class="topbar-left">
            <span class="topbar-kicker">{{ $sectionKicker }}</span>

            <div class="topbar-content">
                @hasSection('breadcrumb')
                    @yield('breadcrumb')
                @else
                    <h1>@yield('title', 'Beranda')</h1>
                @endif
            </div>
        </div>

        <div class="topbar-right">
            <div class="datestamp" aria-label="Tanggal hari ini">
                <span class="day-row">
                    {{-- crescent (matches the chat badge in the sidebar) --}}
                    <svg class="moon" viewBox="0 0 14 14" fill="currentColor" aria-hidden="true">
                        <path d="M11.5 7.5a4.5 4.5 0 1 1-4.5-4.5 3.5 3.5 0 0 0 4.5 4.5z"/>
                    </svg>
                    {{ $dayName }}
                </span>
                <span class="date-detail">{{ $dateDetail }}</span>
            </div>

            <span class="topbar-divider" aria-hidden="true"></span>

            <form action="{{ route('logout') }}" method="POST" class="logout-form">
                @csrf
                <button type="submit" class="logout-btn" title="Keluar dari akun">
                    keluar
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M17 16l4-4m0 0l-4-4m4 4H7"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>

    {{-- Selvage hairline + octagram label --}}
    <div class="selvage" aria-hidden="true">
        <span class="selvage-mark">
            <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.2">
                <rect x="2" y="2" width="8" height="8"/>
                <rect x="2" y="2" width="8" height="8" transform="rotate(45 6 6)"/>
            </svg>
        </span>
        <span class="selvage-index">no. {{ str_pad((string) (request()->route()?->getName() ? crc32(request()->route()->getName()) % 999 : 1), 3, '0', STR_PAD_LEFT) }}</span>
    </div>
</header>
