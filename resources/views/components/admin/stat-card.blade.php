@props(['label', 'value', 'sub' => null])

<div class="stat-card">
    <p class="text-xs uppercase text-slate-500 font-semibold">{{ $label }}</p>
    <p class="text-2xl font-semibold text-slate-900 mt-1">{{ $value }}</p>
    @if ($sub)
        <p class="text-xs text-slate-500 mt-1">{{ $sub }}</p>
    @endif
</div>
