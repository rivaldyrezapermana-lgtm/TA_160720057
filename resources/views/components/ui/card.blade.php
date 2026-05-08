@props(['title' => null, 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'bg-white border border-ink-100 rounded-xl']) }}>
    @if ($title)
        <div class="px-5 py-4 border-b border-ink-100">
            <h3 class="font-display text-lg font-semibold text-ink-900">{{ $title }}</h3>
            @if ($subtitle)<p class="text-sm text-ink-500 mt-0.5">{{ $subtitle }}</p>@endif
        </div>
    @endif
    <div class="p-5">{{ $slot }}</div>
</div>
