@props(['sizes'])
@if ($sizes->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-1 mt-2']) }}>
        @foreach ($sizes->sortBy('id') as $s)
            <span title="{{ $s->stock > 0 ? 'Stok '.$s->stock : 'Stok habis' }}"
                class="inline-flex items-center justify-center min-w-[1.75rem] px-1.5 py-0.5 rounded border text-[11px] font-medium {{ $s->stock > 0 ? 'border-ink-200 text-ink-700' : 'border-ink-100 text-ink-300 line-through' }}">{{ $s->size }}</span>
        @endforeach
    </div>
@endif
