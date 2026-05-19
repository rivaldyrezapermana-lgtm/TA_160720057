<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Masuk') — Labasa</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .btn         { display:inline-flex; align-items:center; justify-content:center; gap:.5rem; padding:.5rem 1rem; border-radius:.5rem; font-size:.875rem; font-weight:500; }
        .btn-primary { background:#059669; color:#fff; width:100%; padding:.625rem 1rem; }

        .field       { margin-bottom:1rem; }
        .label       { display:block; font-size:.875rem; font-weight:500; color:#334155; margin-bottom:.25rem; }
        .input       { display:block; width:100%; padding:.5rem .75rem; font-size:.875rem; border:1px solid #cbd5e1; border-radius:.5rem; }
        .input:focus { outline:none; border-color:#10b981; }
        .input.has-error { border-color:#dc2626; }
        .field-error { font-size:.75rem; color:#dc2626; margin-top:.25rem; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">

<div class="w-full max-w-md">
    <div class="text-center mb-6">
        <a href="{{ route('home') }}" class="text-2xl font-semibold text-slate-900">Labasa</a>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-6 sm:p-8">
        @yield('content')
    </div>

    <p class="text-center text-xs text-slate-500 mt-4">
        © {{ date('Y') }} Toko Labasa
    </p>
</div>

</body>
</html>
