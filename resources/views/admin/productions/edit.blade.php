@extends('layouts.admin')
@section('title', 'Ubah '.$production->code)
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Produksi', 'url' => route('admin.productions.index')], ['label' => $production->code, 'url' => route('admin.productions.show', $production->id)], ['label' => 'Ubah']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Ubah {{ $production->code }}</h1>
@endsection

@section('content')
<form action="{{ route('admin.productions.update', $production->id) }}" method="POST" class="max-w-4xl space-y-4">
    @csrf @method('PUT')
    <x-ui.card title="Informasi Produksi">
        <div class="grid md:grid-cols-3 gap-4">
            <x-ui.select name="production_machine_id" label="Mesin Produksi" :options="$machines->toArray()" :selected="$production->production_machine_id" placeholder="— Tanpa mesin —" />
            @if ($production->sizes->isEmpty())
                <x-ui.input name="planned_qty" type="number" label="Target Jumlah" required :value="$production->planned_qty" />
            @endif
            <x-ui.input name="actual_qty" type="number" label="Jumlah Aktual" :value="$production->actual_qty" />
            <x-ui.select name="status" label="Status" required :options="collect(\App\Models\Production::STATUSES)->mapWithKeys(fn ($s) => [$s => ['planned' => 'Direncanakan', 'in_progress' => 'Berjalan', 'qc' => 'Pemeriksaan Kualitas', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$s] ?? $s])->toArray()" :selected="$production->status" />
            <x-ui.input name="start_date" type="date" label="Tanggal Mulai" required :value="$production->start_date?->toDateString()" />
            <x-ui.input name="end_date" type="date" label="Estimasi Selesai" :value="$production->end_date?->toDateString()" />
        </div>
    </x-ui.card>

    @if ($production->sizes->isNotEmpty())
        @php
            $planned = $production->sizes->pluck('planned_qty', 'size');
            $variants = $production->product->sizes->pluck('size')->all();
            $oldSizes = old('sizes');
            // Setelah gagal validasi, centang mengikuti isian terakhir; ukuran yang sudah dipotong selalu tercentang.
            $isTicked = fn ($size) => ($cutBySize[$size] ?? 0) > 0
                || (is_array($oldSizes) ? array_key_exists($size, $oldSizes) : $planned->has($size));
            $tickedSizes = collect($sizeOptions)->filter($isTicked)->values();
        @endphp
        <x-ui.card title="Target per Ukuran" subtitle="Centang ukuran yang akan diproduksi. Target batch dihitung ulang dari jumlah ukuran yang dicentang ({{ $production->planned_qty }} pcs saat ini).">
            <div id="size-inputs" class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-start">
                @foreach ($sizeOptions as $size)
                    @php
                        $ticked = $isTicked($size);
                        $cut = $cutBySize[$size] ?? 0;
                    @endphp
                    <div class="border border-ink-100 rounded-lg px-3 py-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-ink-800 cursor-pointer">
                            <input type="checkbox" class="size-tick" @checked($ticked) @disabled($cut > 0)>
                            <span>Ukuran {{ $size }}</span>
                        </label>
                        @if ($cut > 0)
                            <p class="text-[11px] text-ink-400 mt-0.5">Sudah dipotong {{ $cut }} pcs</p>
                        @elseif (! in_array($size, $variants, true))
                            <p class="text-[11px] text-ink-400 mt-0.5">Varian baru, stok dimulai dari 0.</p>
                        @endif
                        <div class="size-qty-wrap mt-2 {{ $ticked ? '' : 'hidden' }}">
                            <input type="number" min="{{ max(1, $cut) }}" placeholder="Jumlah pcs" name="sizes[{{ $size }}]" data-size="{{ $size }}"
                                value="{{ old('sizes.'.$size, $planned[$size] ?? '') }}" class="input size-qty" @disabled(! $ticked) @required($ticked)>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('sizes')<p class="field-error mt-2">{{ $message }}</p>@enderror
            @error('planned_qty')<p class="field-error mt-2">{{ $message }}</p>@enderror
            <div class="mt-4 pt-4 border-t border-ink-100 grid sm:grid-cols-2 gap-4 items-end">
                <div>
                    <p class="text-xs text-ink-500">Total Target</p>
                    <p class="font-display text-2xl font-semibold tabular-nums"><span id="size-total">{{ $production->planned_qty }}</span> <span class="text-sm font-normal text-ink-500">pcs</span></p>
                </div>
                @if ($production->makesOwnSample())
                    <x-ui.select name="sample_size" label="Ukuran Sampel" :selected="$production->sample_size"
                        :options="$tickedSizes->mapWithKeys(fn ($s) => [$s => $s])->toArray()" placeholder="— Ukuran terbanyak —" />
                @endif
            </div>
        </x-ui.card>

        @push('scripts')
        <script>
        $(function () {
            function updateTotal() {
                let total = 0;
                const $sample = $('#sample_size');
                const current = $sample.val();
                $sample.find('option').not(':first').remove();

                // Ukuran yang tidak dicentang inputnya nonaktif, jadi tidak ikut dihitung atau terkirim.
                $('.size-qty:enabled').each(function () {
                    const qty = parseInt($(this).val(), 10) || 0;
                    if (qty <= 0) return;
                    total += qty;
                    const size = $(this).data('size').toString();
                    $sample.append($('<option>').val(size).text(size));
                });

                $('#size-total').text(total);
                $sample.val($sample.find('option[value="' + current + '"]').length ? current : '');
            }

            $('#size-inputs').on('input', '.size-qty', updateTotal);
            $('#size-inputs').on('change', '.size-tick', function () {
                const $wrap = $(this).closest('div').find('.size-qty-wrap').toggleClass('hidden', !this.checked);
                const $qty = $wrap.find('.size-qty').prop('disabled', !this.checked).prop('required', this.checked);
                if (this.checked) $qty.trigger('focus');
                updateTotal();
            });
            updateTotal();
        });
        </script>
        @endpush
    @endif

    <x-ui.card>
        <x-ui.textarea name="notes" label="Catatan" :value="$production->notes" />
    </x-ui.card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.productions.show', $production->id) }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan Perubahan</button>
    </div>
</form>
@endsection
