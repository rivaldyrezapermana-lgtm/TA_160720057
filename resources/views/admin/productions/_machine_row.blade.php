{{-- Satu baris mesin pada form tahap: $machines (id => nama), $selectedId (id atau null), $disabled. --}}
<div class="flex items-center gap-2 js-machine-row">
    <select name="production_machine_ids[]" aria-label="Mesin" class="input js-machine-select disabled:bg-ink-50 disabled:cursor-not-allowed" @disabled($disabled)>
        <option value="">Pilih mesin</option>
        @foreach ($machines as $mid => $mname)
            <option value="{{ $mid }}" @selected($selectedId === (int) $mid)>{{ $mname }}</option>
        @endforeach
    </select>
    <button type="button" class="text-sm text-red-700 hover:underline shrink-0 js-machine-remove disabled:opacity-50 disabled:cursor-not-allowed disabled:no-underline" @disabled($disabled)>Hapus</button>
</div>
