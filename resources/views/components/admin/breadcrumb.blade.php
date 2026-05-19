@props(['items' => []])

<nav class="flex items-center text-sm text-slate-500 mb-1" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-900">Dashboard</a>
    @foreach ($items as $item)
        <span class="mx-2 text-slate-300">/</span>
        @if (isset($item['url']) && !$loop->last)
            <a href="{{ $item['url'] }}" class="hover:text-slate-900">{{ $item['label'] }}</a>
        @else
            <span class="text-slate-900 font-medium">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
