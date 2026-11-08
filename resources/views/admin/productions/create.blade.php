@extends('layouts.admin')
@section('title', 'Batch Produksi Baru')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Produksi', 'url' => route('admin.productions.index')], ['label' => 'Baru']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Buat Batch Produksi</h1>
@endsection

@section('content')
<form action="{{ route('admin.productions.store') }}" method="POST" class="max-w-4xl space-y-4">
    @csrf
    <x-ui.card title="Informasi Produksi">
        <div class="grid md:grid-cols-3 gap-4">
            <x-ui.select name="product_id" label="Produk" required :selected="$selectedProduct" :options="$products->pluck('name','id')->toArray()" />
            <div id="planned-qty-field">
                <x-ui.input name="planned_qty" type="number" label="Target Jumlah" min="1" />
            </div>
            <x-ui.input name="code" label="Kode Batch" placeholder="Otomatis" />
            <x-ui.input name="start_date" type="date" label="Tanggal Mulai" required />
            <x-ui.input name="end_date" type="date" label="Estimasi Selesai" />
        </div>
    </x-ui.card>

    <x-ui.card id="sizes-card" title="Target per Ukuran" subtitle="Centang ukuran yang akan diproduksi. Total target batch adalah jumlah ukuran yang dicentang." class="hidden">
        <p id="no-variant-note" class="hidden text-xs text-ink-500 mb-3">Produk ini belum memiliki varian ukuran. Centang ukuran yang akan diproduksi, atau isi Target Jumlah tanpa rincian ukuran.</p>
        <div id="size-inputs" class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-start"></div>
        @error('sizes')<p class="field-error mt-2">{{ $message }}</p>@enderror
        <div class="mt-4 pt-4 border-t border-ink-100 grid sm:grid-cols-2 gap-4 items-end">
            <div>
                <p class="text-xs text-ink-500">Total Target</p>
                <p class="font-display text-2xl font-semibold tabular-nums"><span id="size-total">0</span> <span class="text-sm font-normal text-ink-500">pcs</span></p>
            </div>
            <div id="sample-size-field" class="field !mb-0 hidden">
                <label for="sample_size" class="label">Ukuran Sampel</label>
                <select name="sample_size" id="sample_size" class="input {{ $errors->has('sample_size') ? 'has-error' : '' }}"></select>
                <p class="field-help">Satu potong ukuran ini dibuat dan disetujui sebelum produksi massal.</p>
                @error('sample_size')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Sampel">
        <div class="flex flex-wrap items-start gap-4">
            <button type="button" id="btn-cek-sampel" class="btn-secondary">Cek Sampel</button>
            <div id="sample-result" class="flex-1 min-w-[16rem] text-sm text-ink-600">
                Pilih produk lalu klik Cek Sampel. Jika produk sudah memiliki sampel, batch langsung ke produksi massal.
                Jika belum, batch dimulai dari tahap sampel (1 pcs dibuat dan disetujui terlebih dahulu).
            </div>
        </div>
        <div id="sample-sketches" class="hidden mt-4 grid grid-cols-3 md:grid-cols-6 gap-2"></div>
        <p class="text-xs text-ink-400 mt-3">Target 1 pcs langsung dikerjakan tanpa fase sampel.</p>
    </x-ui.card>

    <x-ui.card>
        <x-ui.textarea name="notes" label="Catatan" />
    </x-ui.card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.productions.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Mulai Produksi</button>
    </div>
</form>

@push('scripts')
<script>
$(function () {
    const productSizes = @json($productSizes);
    const productVariants = @json($productVariants);
    const prefill = @json((object) $prefillSizes);
    const oldSample = @json(old('sample_size'));
    // Produk yang sudah punya sampel tidak perlu memilih ukuran sampel.
    const productsWithSample = @json($productsWithSample);
    let hasSample = false;

    // Isian prefill/old membuat ukurannya tercentang, kecuali yang jumlahnya 0
    // (mis. ukuran tanpa rekomendasi produksi).
    function isPrefilled(size) {
        return Object.prototype.hasOwnProperty.call(prefill, size) && String(prefill[size]) !== '0';
    }

    function renderSizes() {
        const productId = $('#product_id').val();
        const sizes = productSizes[productId] || [];
        const variants = productVariants[productId] || [];
        hasSample = productsWithSample.includes(parseInt(productId, 10));
        const $box = $('#size-inputs').empty();

        $('#sizes-card').toggleClass('hidden', sizes.length === 0);
        $('#no-variant-note').toggleClass('hidden', sizes.length === 0 || variants.length > 0);

        sizes.forEach(function (size) {
            const ticked = isPrefilled(size);
            const $field = $('<div class="border border-ink-100 rounded-lg px-3 py-2">');
            $field.append($('<label class="flex items-center gap-2 text-sm font-medium text-ink-800 cursor-pointer">')
                .append($('<input type="checkbox" class="size-tick">').prop('checked', ticked))
                .append($('<span>').text('Ukuran ' + size)));
            if (!variants.includes(size)) {
                $field.append($('<p class="text-[11px] text-ink-400 mt-0.5">').text('Varian baru, stok dimulai dari 0.'));
            }
            $field.append($('<div class="size-qty-wrap mt-2">').toggleClass('hidden', !ticked)
                .append($('<input type="number" min="1" placeholder="Jumlah pcs" class="input size-qty">')
                    .attr('name', 'sizes[' + size + ']')
                    .attr('data-size', size)
                    .prop('disabled', !ticked)
                    .prop('required', ticked)
                    .val(prefill[size] ?? '')));
            $box.append($field);
        });

        updateTotal();
    }

    function updateTotal() {
        let total = 0;
        let largest = null;
        const $sample = $('#sample_size');
        const current = $sample.val() || oldSample;
        $sample.empty();

        // Target Qty hanya untuk produk tanpa varian yang tidak dirinci per ukuran.
        const productId = $('#product_id').val();
        const useSizes = (productVariants[productId] || []).length > 0 || $('.size-tick:checked').length > 0;
        $('#planned-qty-field').toggleClass('hidden', useSizes);
        $('#planned_qty').prop('disabled', useSizes);

        // Ukuran yang tidak dicentang inputnya nonaktif, jadi tidak ikut dihitung atau terkirim.
        $('.size-qty:enabled').each(function () {
            const qty = parseInt($(this).val(), 10) || 0;
            if (qty <= 0) return;
            total += qty;
            const size = $(this).data('size').toString();
            if (largest === null || qty > largest.qty) largest = { size: size, qty: qty };
            $sample.append($('<option>').val(size).text(size));
        });

        $('#size-total').text(total);
        const needsSampleSize = total > 1 && !hasSample;
        $('#sample-size-field').toggleClass('hidden', !needsSampleSize);
        $sample.prop('disabled', !needsSampleSize);
        if (largest) {
            $sample.val($sample.find('option[value="' + current + '"]').length ? current : largest.size);
        }
    }

    function resetSample() {
        $('#sample-result').attr('class', 'flex-1 min-w-[16rem] text-sm text-ink-600')
            .text('Klik Cek Sampel untuk melihat apakah produk ini sudah memiliki sampel.');
        $('#sample-sketches').addClass('hidden').empty();
    }

    $('#btn-cek-sampel').on('click', function () {
        const productId = $('#product_id').val();
        const $result = $('#sample-result');
        if (!productId) {
            $result.attr('class', 'flex-1 min-w-[16rem] text-sm text-red-600').text('Pilih produk terlebih dahulu.');
            return;
        }

        $result.attr('class', 'flex-1 min-w-[16rem] text-sm text-ink-500').text('Mengecek…');
        $.get(@json(route('admin.productions.sample-status')), { product_id: productId })
            .done(function (res) {
                hasSample = res.has_sample;
                updateTotal();
                const $sketches = $('#sample-sketches').empty();
                if (!res.has_sample) {
                    $result.attr('class', 'flex-1 min-w-[16rem] text-sm rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-ink-800')
                        .html('<span class="font-medium">Belum ada sampel.</span> Batch ini akan dimulai dari <span class="font-medium">Tahap Sampel</span>.');
                    $sketches.addClass('hidden');
                    return;
                }

                const when = res.approved_at ? 'disetujui ' + res.approved_at : 'batch selesai';
                $result.attr('class', 'flex-1 min-w-[16rem] text-sm rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-ink-800')
                    .empty()
                    .append($('<span class="font-medium">').text('Sudah ada sampel dari '))
                    .append($('<a target="_blank" class="font-medium underline">').attr('href', res.url).text(res.code))
                    .append(document.createTextNode(' (' + when + '). Persiapan dan tahap sampel dilewati — batch langsung ke Produksi Massal.'));

                res.sketches.forEach(function (url) {
                    $sketches.append($('<a target="_blank" rel="noopener" class="block border border-ink-100 rounded overflow-hidden">').attr('href', url)
                        .append($('<img class="w-full aspect-square object-contain bg-ink-50">').attr('src', url)));
                });
                $sketches.toggleClass('hidden', res.sketches.length === 0);
            })
            .fail(function () {
                $result.attr('class', 'flex-1 min-w-[16rem] text-sm text-red-600').text('Gagal mengecek sampel. Silakan coba lagi.');
            });
    });

    $('#product_id').on('change', function () { renderSizes(); resetSample(); });
    $('#size-inputs').on('input', '.size-qty', updateTotal);
    $('#size-inputs').on('change', '.size-tick', function () {
        const $wrap = $(this).closest('div').find('.size-qty-wrap').toggleClass('hidden', !this.checked);
        const $qty = $wrap.find('.size-qty').prop('disabled', !this.checked).prop('required', this.checked);
        if (this.checked) $qty.trigger('focus');
        updateTotal();
    });
    renderSizes();
});
</script>
@endpush
@endsection
