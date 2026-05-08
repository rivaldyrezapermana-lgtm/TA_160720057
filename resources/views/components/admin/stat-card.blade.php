@props(['label', 'value', 'sub' => null, 'icon' => null, 'tone' => 'default'])

@php
    $tones = [
        'default' => 'bg-ink-50 text-ink-700',
        'green'   => 'bg-emerald-50 text-emerald-700',
        'amber'   => 'bg-amber-50 text-amber-700',
        'red'     => 'bg-red-50 text-red-700',
        'blue'    => 'bg-blue-50 text-blue-700',
    ];
    $iconClass = $tones[$tone] ?? $tones['default'];
@endphp

<div class="stat-card">
    <div class="flex items-start justify-between">
        <div>
            <p class="text-xs uppercase tracking-wider text-ink-500 font-semibold">{{ $label }}</p>
            <p class="font-display text-3xl font-semibold text-ink-900 mt-2">{{ $value }}</p>
            @if ($sub)
                <p class="text-xs text-ink-500 mt-1">{{ $sub }}</p>
            @endif
        </div>
        @if ($icon)
            <div class="w-10 h-10 rounded-lg flex items-center justify-center {{ $iconClass }}">
                {!! $icon !!}
            </div>
        @endif
    </div>
</div>
