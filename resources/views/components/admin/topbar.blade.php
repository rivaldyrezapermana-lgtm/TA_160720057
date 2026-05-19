<header class="h-16 bg-white border-b border-slate-200 px-6 flex items-center justify-between">
    <div>
        @hasSection('breadcrumb')
            @yield('breadcrumb')
        @else
            <h1 class="text-lg font-semibold text-slate-900">@yield('title', 'Beranda')</h1>
        @endif
    </div>

    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7"/>
            </svg>
            Keluar
        </button>
    </form>
</header>
