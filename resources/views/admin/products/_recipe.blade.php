{{-- Resep per ukuran dan HPP langsung, dipakai form tambah dan ubah produk: $sizes, $materials, $bomRows. --}}
@php
    // Setelah validasi gagal, tampilkan isian terakhir walau semua baris sudah dihapus.
    $rows = array_filter(
        session()->hasOldInput() ? (array) old('materials', []) : $bomRows,
        'is_array'
    );
    $nextIndex = collect(array_keys($rows))->map(fn ($key) => (int) $key)->max() + 1;
@endphp

<x-ui.card title="Bahan Baku (Resep)" subtitle="Pilih bahan lalu isi jumlah yang dibutuhkan untuk membuat 1 unit pada tiap ukuran.">
    @error('materials')<p class="field-error mb-3">{{ $message }}</p>@enderror
    <div class="overflow-x-auto">
        <table class="table-clean">
            <thead>
                <tr>
                    <th>Bahan</th>
                    @foreach ($sizes as $sz)
                        <th class="!text-right !px-1">{{ $sz }}</th>
                    @endforeach
                    <th></th>
                </tr>
            </thead>
            <tbody id="bom-rows">
                @foreach ($rows as $index => $row)
                    @include('admin.products._bom_row', ['index' => $index, 'row' => $row])
                @endforeach
            </tbody>
        </table>
    </div>
    <p id="bom-empty" class="text-sm text-ink-500 mt-3 {{ $rows === [] ? '' : 'hidden' }}">Belum ada bahan pada resep ini.</p>
    <button type="button" id="bom-add" class="btn btn-secondary mt-3">+ Tambah Bahan</button>
    <template id="bom-row-template">
        @include('admin.products._bom_row', ['index' => '__INDEX__', 'row' => []])
    </template>
</x-ui.card>

<x-ui.card title="HPP (Harga Pokok)" subtitle="Total biaya bahan per unit, dihitung otomatis dari resep pada tiap ukuran.">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach ($sizes as $col => $sz)
            <div class="border border-ink-100 rounded-lg px-3 py-2">
                <p class="text-xs uppercase tracking-wider text-ink-500 font-semibold">{{ $sz }}</p>
                <p class="font-display text-lg font-semibold tabular-nums js-hpp-size" data-col="{{ $col }}">Rp 0</p>
            </div>
        @endforeach
    </div>
    <p id="hpp-warning" class="hidden mt-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
        ⚠ Harga jual di bawah HPP ukuran <span id="hpp-warning-sizes"></span>. Periksa kembali margin.
    </p>
</x-ui.card>

@push('scripts')
<script>
$(function () {
    const sizes = @json($sizes->values());
    let nextIndex = {{ $nextIndex }};

    function fmt(n) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n)); }

    function recalc() {
        const totals = sizes.map(() => 0);
        $('.js-bom-row').each(function () {
            const cost = parseFloat($(this).find('.js-bom-material option:selected').data('cost')) || 0;
            $(this).find('.js-bom-qty').each(function () {
                const qty = parseFloat($(this).val()) || 0;
                if (qty > 0) totals[$(this).data('col')] += qty * cost;
            });
        });

        $('.js-hpp-size').each(function () {
            $(this).text(fmt(totals[$(this).data('col')]));
        });

        const price = parseFloat($('#price').val()) || 0;
        const under = sizes.filter((size, col) => totals[col] > 0 && price < totals[col]);
        $('#hpp-warning-sizes').text(under.join(', '));
        $('#hpp-warning').toggleClass('hidden', under.length === 0);
        $('#bom-empty').toggleClass('hidden', $('.js-bom-row').length > 0);
    }

    $('#bom-add').on('click', function () {
        $('#bom-rows').append($('#bom-row-template').html().replace(/__INDEX__/g, nextIndex++));
        recalc();
    });
    $(document).on('click', '.js-bom-remove', function () {
        $(this).closest('.js-bom-row').remove();
        recalc();
    });
    $(document).on('input change', '.js-bom-qty, .js-bom-material, #price', recalc);
    recalc();
});
</script>
@endpush
