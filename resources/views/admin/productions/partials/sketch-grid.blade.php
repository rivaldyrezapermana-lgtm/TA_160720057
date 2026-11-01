@if ($sketches->isNotEmpty())
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-[50vh] overflow-y-auto">
        @foreach ($sketches as $sketch)
            <a href="{{ Storage::disk('public')->url($sketch->path) }}" target="_blank" rel="noopener" class="block border border-ink-100 rounded-lg overflow-hidden bg-white">
                <img src="{{ Storage::disk('public')->url($sketch->path) }}" alt="{{ $sketch->original_name }}" class="w-full aspect-square object-contain bg-ink-50">
                <p class="px-2 py-1 text-[11px] text-ink-500 truncate">{{ $sketch->original_name }}</p>
            </a>
        @endforeach
    </div>
@else
    <p class="text-xs text-ink-400">Belum ada foto desain.</p>
@endif
