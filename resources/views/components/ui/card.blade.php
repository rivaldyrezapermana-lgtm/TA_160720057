@props(['title' => null, 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'bg-white border border-slate-200 rounded-lg']) }}>
    @if ($title)
        <div class="px-5 py-3 border-b border-slate-200">
            <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
            @if ($subtitle)<p class="text-sm text-slate-500 mt-0.5">{{ $subtitle }}</p>@endif
        </div>
    @endif
    <div class="p-5">{{ $slot }}</div>
</div>
