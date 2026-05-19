<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Labasa</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">

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
        /* make button rounded */
        .btn           { display:inline-flex; align-items:center; gap:.5rem; padding:.5rem 1rem; border-radius:.5rem; font-size:.875rem; font-weight:500; }
        .btn-primary   { background:#059669; color:#fff; }
        .btn-secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
        .btn-danger    { background:#dc2626; color:#fff; }

        .field         { margin-bottom:1rem; }
        .label         { display:block; font-size:.875rem; font-weight:500; color:#334155; margin-bottom:.25rem; }
        .input         { display:block; width:100%; padding:.5rem .75rem; font-size:.875rem; border:1px solid #cbd5e1; border-radius:.5rem; }
        .input:focus   { outline:none; border-color:#10b981; }
        .field-help    { font-size:.75rem; color:#64748b; margin-top:.25rem; }
        .field-error   { font-size:.75rem; color:#dc2626; margin-top:.25rem; }

        .badge         { display:inline-flex; padding:.125rem .5rem; border-radius:.5rem; font-size:.75rem; font-weight:500; background:#f1f5f9; color:#334155; }
        .badge-green   { background:#ecfdf5; color:#047857; }
        .badge-amber   { background:#fffbeb; color:#b45309; }
        .badge-red     { background:#fef2f2; color:#b91c1c; }
        .badge-blue    { background:#eff6ff; color:#1d4ed8; }
        .badge-gray    { background:#f1f5f9; color:#64748b; }

        .card,
        .stat-card     { background:#fff; border:1px solid #e2e8f0; border-radius:.5rem; padding:1.25rem; }

        .table-clean   { width:100%; font-size:.875rem; }
        .table-clean th { text-align:left; padding:.625rem 1rem; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-weight:600; color:#475569; }
        .table-clean td { padding:.625rem 1rem; border-bottom:1px solid #f1f5f9; }

        #admin-aside[data-collapsed="true"] { width:4rem; }
        #admin-aside[data-collapsed="true"] .sidebar-label,
        #admin-aside[data-collapsed="true"] .sidebar-section,
        #admin-aside[data-collapsed="true"] .sidebar-brand-text,
        #admin-aside[data-collapsed="true"] .sidebar-user-meta,
        #admin-aside[data-collapsed="true"] .sidebar-badge { display:none; }
        #admin-aside[data-collapsed="true"] .sidebar-brandbar,
        #admin-aside[data-collapsed="true"] .sidebar-link,
        #admin-aside[data-collapsed="true"] .sidebar-user { justify-content:center; }
        #admin-aside[data-collapsed="true"] #sidebar-toggle svg { transform:rotate(180deg); }
    </style>
    @stack('styles')
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">

<div class="flex min-h-screen">
    <x-admin.sidebar />

    <div class="flex-1 flex flex-col">
        <x-admin.topbar />

        <main class="flex-1 px-6 py-6">
            @if (session('success'))
                <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
                    <p class="font-medium mb-1">Periksa kembali:</p>
                    <ul class="list-disc list-inside">
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
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    // Sidebar collapse (remembers your choice)
    $('#sidebar-toggle').on('click', function () {
        const $aside = $('#admin-aside');
        if ($aside.attr('data-collapsed') === 'true') {
            $aside.removeAttr('data-collapsed');
            localStorage.removeItem('sidebar-collapsed');
        } else {
            $aside.attr('data-collapsed', 'true');
            localStorage.setItem('sidebar-collapsed', '1');
        }
    });
    if (localStorage.getItem('sidebar-collapsed') === '1') {
        $('#admin-aside').attr('data-collapsed', 'true');
    }
</script>
@stack('scripts')

</body>
</html>
