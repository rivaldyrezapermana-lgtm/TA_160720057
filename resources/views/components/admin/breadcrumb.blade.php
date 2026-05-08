@props(['items' => []])

<nav class="flex items-center text-sm text-ink-500 mb-1" aria-label="Breadcrumb">
    <a href="{{ route('admin.dashboard') }}" class="hover:text-ink-900">Dashboard</a>
    @foreach ($items as $item)
        <svg class="w-4 h-4 mx-1.5 text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/></svg>
        @if (isset($item['url']) && !$loop->last)
            <a href="{{ $item['url'] }}" class="hover:text-ink-900">{{ $item['label'] }}</a>
        @else
            <span class="text-ink-900 font-medium">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
