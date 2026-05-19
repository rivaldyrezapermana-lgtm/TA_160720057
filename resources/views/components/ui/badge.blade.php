@props(['tone' => 'gray'])
@php
    $map = [
        'gray'  => 'bg-slate-100 text-slate-700',
        'green' => 'bg-emerald-50 text-emerald-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'red'   => 'bg-red-50 text-red-700',
        'blue'  => 'bg-blue-50 text-blue-700',
        'dark'  => 'bg-slate-900 text-white',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium '.($map[$tone] ?? $map['gray'])]) }}>
    {{ $slot }}
</span>
