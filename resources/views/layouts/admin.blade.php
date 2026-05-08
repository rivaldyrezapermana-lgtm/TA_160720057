<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Labasa</title>

    {{-- Tailwind via CDN for skeleton; replace with build pipeline in production --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Inter+Tight:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- DataTables --}}
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">

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
        /* ── Sidebar ──────────────────────────────────── */
        #admin-aside { transition: width .25s ease; }
        #admin-aside[data-collapsed="true"] { width: 4rem; }
        #admin-aside[data-collapsed="true"] .label-text,
        #admin-aside[data-collapsed="true"] .nav-section,
        #admin-aside[data-collapsed="true"] .badge-pill,
        #admin-aside[data-collapsed="true"] .user-card .user-meta,
        #admin-aside[data-collapsed="true"] .brand-text { opacity:0; visibility:hidden; width:0; pointer-events:none; }
        #admin-aside[data-collapsed="true"] .brand-block { justify-content: center; }
        #admin-aside[data-collapsed="true"] .nav-link { justify-content: center; }
        #admin-aside[data-collapsed="true"] .user-card { justify-content: center; }
        #admin-aside:not([data-collapsed="true"]) .label-tooltip { display: none; }

        .brand-block { padding: 1.25rem 1rem; border-bottom: 1px solid theme('colors.ink.100'); display: flex; align-items: center; gap: .75rem; background: linear-gradient(180deg, rgba(244,246,243,.5), rgba(255,255,255,1)); }
        .brand-text { transition: opacity .2s ease; }
        .collapse-btn { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: .375rem; color: theme('colors.ink.500'); transition: background-color .15s ease, color .15s ease; flex-shrink: 0; }
        .collapse-btn:hover { color: theme('colors.ink.900'); background-color: theme('colors.ink.100'); }
        .collapse-btn svg { transition: transform .25s ease; }
        #admin-aside[data-collapsed="true"] .collapse-btn svg { transform: rotate(180deg); }

        .nav-link { position: relative; @apply flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-ink-700 transition; }
        .nav-link:hover { @apply bg-ink-50 text-ink-900; }
        .nav-link::before {
            content:''; position:absolute; left:0; top:.5rem; bottom:.5rem; width:3px; border-radius:0 3px 3px 0;
            background: linear-gradient(180deg, theme('colors.ink.900'), theme('colors.accent.600'));
            opacity:0; transform: scaleY(.3);
            transition: opacity .2s ease, transform .25s cubic-bezier(.65,.05,.36,1);
        }
        .nav-link.is-active { @apply bg-ink-50 text-ink-900 font-semibold; }
        .nav-link.is-active::before { opacity:1; transform: scaleY(1); }

        .nav-icon { @apply w-9 h-9 rounded-lg flex items-center justify-center bg-ink-50 text-ink-500 flex-shrink-0 transition; }
        .nav-icon svg { @apply w-[18px] h-[18px]; }
        .nav-link:hover .nav-icon { @apply bg-accent-50 text-accent-600; }
        .nav-link.is-active .nav-icon { @apply bg-ink-900 text-white; }

        .label-text { @apply truncate; transition: opacity .2s ease; }

        .nav-section { @apply text-[10px] uppercase tracking-[0.18em] text-ink-400 font-semibold px-3 mb-2 mt-5 flex items-center gap-2 transition; }
        .nav-section span { @apply flex-shrink-0; }
        .nav-section::after { content:''; flex:1; height:1px; background: theme('colors.ink.100'); }

        .badge-pill  { @apply ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[10px] font-semibold transition flex-shrink-0; }
        .badge-chat  { background: linear-gradient(135deg, #ef4444, theme('colors.ink.900')); @apply text-white shadow-sm; }
        .badge-fuzzy { @apply bg-accent-50 text-accent-700 ring-1 ring-accent-500/20; }

        .label-tooltip {
            position: absolute; left: calc(100% + 10px); top: 50%; transform: translate(-4px, -50%);
            background: theme('colors.ink.900'); color: #fff; padding: .3rem .6rem; border-radius: .375rem;
            font-size: 12px; font-weight: 500; white-space: nowrap; opacity: 0; pointer-events: none;
            transition: opacity .15s ease, transform .15s ease; z-index: 60;
            box-shadow: 0 4px 12px -4px rgba(0,0,0,.25);
        }
        #admin-aside[data-collapsed="true"] .nav-link:hover .label-tooltip { opacity: 1; transform: translate(0, -50%); }

        .user-card { @apply border-t border-ink-100 p-3 flex items-center gap-3; }
        .user-card .avatar { @apply w-10 h-10 rounded-full bg-gradient-to-br from-ink-800 to-accent-700 text-white flex items-center justify-center font-semibold text-sm ring-2 ring-white shadow-sm flex-shrink-0; }
        .user-card .user-meta { @apply min-w-0 flex-1; transition: opacity .2s ease; }
        .user-card .role-chip { @apply inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-accent-50 text-accent-700 capitalize; }
        .user-card .meta-link { @apply text-[11px] text-ink-500 hover:text-ink-900 inline-flex items-center gap-1 transition; }
        .stat-card { @apply bg-white border border-ink-100 rounded-xl p-5; }
        .btn { @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition; }
        .btn-primary { @apply btn bg-ink-900 text-white hover:bg-ink-700; }
        .btn-secondary { @apply btn bg-white text-ink-700 border border-ink-200 hover:bg-ink-50; }
        .btn-danger { @apply btn bg-red-600 text-white hover:bg-red-700; }
        /* ── Forms — Atelier (fountain pen on parchment) ── */
        .field { position: relative; padding-top: 6px; }
        .field + .field { margin-top: 1.25rem; }
        .label {
            display: block;
            font-family: 'Fraunces', serif;
            font-style: italic; font-weight: 500;
            font-size: 10.5px;
            color: #8a8a82;
            text-transform: uppercase;
            letter-spacing: 0.26em;
            margin-bottom: 8px;
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
            padding: 6px 0 9px;
            font-family: 'Inter Tight', sans-serif;
            font-size: 14px;
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
        .input.has-error:focus { box-shadow: 0 1px 0 0 #b91c1c; }
        select.input {
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'><path d='M3 4.5l3 3 3-3' fill='none' stroke='%235b7553' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round'/></svg>");
            background-repeat: no-repeat; background-position: right 2px center; background-size: 12px;
            padding-right: 22px; cursor: pointer;
        }
        textarea.input { min-height: 88px; resize: vertical; padding-top: 10px; line-height: 1.65; }
        .field-help {
            font-family: 'Fraunces', serif; font-style: italic;
            font-size: 11.5px; color: #8a8a82; margin-top: 6px;
        }
        .field-error {
            font-family: 'Fraunces', serif; font-style: italic; font-weight: 500;
            font-size: 12px; color: #b91c1c; margin-top: 6px;
            padding-left: 14px; position: relative;
        }
        .field-error::before { content: '—'; position: absolute; left: 0; top: 0; }
        .badge { @apply inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium; }
        .badge-green { @apply badge bg-emerald-50 text-emerald-700; }
        .badge-amber { @apply badge bg-amber-50 text-amber-700; }
        .badge-red   { @apply badge bg-red-50 text-red-700; }
        .badge-gray  { @apply badge bg-ink-100 text-ink-700; }
        .badge-blue  { @apply badge bg-blue-50 text-blue-700; }
        .table-clean { @apply w-full text-sm; }
        .table-clean th { @apply text-left text-[11px] font-semibold uppercase tracking-wider text-ink-500 px-4 py-3 border-b border-ink-100; }
        .table-clean td { @apply px-4 py-3 border-b border-ink-100 text-ink-800; }
        .table-clean tbody tr:hover { @apply bg-ink-50; }

        /* DataTables overrides */
        .dataTables_wrapper .dataTables_filter input { @apply input !w-64 !inline-block; }
        .dataTables_wrapper .dataTables_length select { @apply input !w-auto !inline-block; }
        .dataTables_wrapper .dataTables_paginate .paginate_button { @apply !px-3 !py-1 !rounded-md !border !border-ink-200 !text-ink-700 !text-sm; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { @apply !bg-ink-900 !text-white !border-ink-900; }
    </style>
    @stack('styles')
</head>
<body class="bg-ink-50 text-ink-900 min-h-screen">

<div class="flex min-h-screen">
    <x-admin.sidebar />

    <div class="flex-1 flex flex-col">
        <x-admin.topbar />

        <main class="flex-1 px-8 py-6">
            @if (session('success'))
                <div id="toast" class="fixed top-6 right-6 z-50 bg-emerald-600 text-white px-4 py-3 rounded-lg shadow-lg text-sm font-medium animate-pulse">
                    ✓ {{ session('success') }}
                </div>
                <script>setTimeout(() => document.getElementById('toast')?.remove(), 3500);</script>
            @endif

            @if ($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 text-sm">
                    <p class="font-semibold mb-1">Periksa kembali:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script>
    // CSRF for AJAX
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    // Sidebar collapse toggle (persisted)
    (function () {
        const KEY = 'labasa.adminSidebar.collapsed';
        const $aside = $('#admin-aside');
        if (!$aside.length) return;
        if (localStorage.getItem(KEY) === '1') $aside.attr('data-collapsed', 'true');
        $('#sidebar-toggle').on('click', function () {
            if ($aside.attr('data-collapsed') === 'true') {
                $aside.removeAttr('data-collapsed');
                localStorage.removeItem(KEY);
            } else {
                $aside.attr('data-collapsed', 'true');
                localStorage.setItem(KEY, '1');
            }
        });
    })();
</script>
@stack('scripts')

</body>
</html>
