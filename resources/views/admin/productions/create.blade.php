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
                <x-ui.input name="planned_qty" type="number" label="Target Qty" min="1" />
            </div>
            <x-ui.input name="code" label="Kode Batch" placeholder="auto" />
            <x-ui.input name="start_date" type="date" label="Tanggal Mulai" required />
            <x-ui.input name="end_date" type="date" label="Estimasi Selesai" />
        </div>
    </x-ui.card>

    <x-ui.card id="sizes-card" title="Target per Ukuran" subtitle="Total target batch adalah jumlah semua ukuran" class="hidden">
        <div id="size-inputs" class="grid grid-cols-2 sm:grid-cols-4 gap-3"></div>
        @error('sizes')<p class="field-error mt-2">{{ $message }}</p>@enderror
        <div class="mt-4 pt-4 border-t border-ink-100 grid sm:grid-cols-2 gap-4 items-end">
            <div>
                <p class="text-xs text-ink-500">Total target</p>
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

    <x-ui.card>
        <p class="text-sm text-ink-600">
            Target lebih dari 1 pcs akan membuat fase sampel: satu potong dikerjakan dan disetujui dulu
            sebelum cutting massal boleh dimulai. Target 1 pcs langsung dikerjakan tanpa fase sampel.
        </p>
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
    const prefill = @json((object) $prefillSizes);
    const oldSample = @json(old('sample_size'));

    function renderSizes() {
        const sizes = productSizes[$('#product_id').val()] || [];
        const $box = $('#size-inputs').empty();

        $('#sizes-card').toggleClass('hidden', sizes.length === 0);
        $('#planned-qty-field').toggleClass('hidden', sizes.length > 0);

        sizes.forEach(function (size) {
            const $field = $('<div class="field !mb-0">');
            $field.append($('<label class="label">').text('Ukuran ' + size));
            $field.append($('<input type="number" min="0" class="input size-qty">')
                .attr('name', 'sizes[' + size + ']')
                .attr('data-size', size)
                .val(prefill[size] ?? ''));
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

        $('.size-qty').each(function () {
            const qty = parseInt($(this).val(), 10) || 0;
            if (qty <= 0) return;
            total += qty;
            const size = $(this).data('size').toString();
            if (largest === null || qty > largest.qty) largest = { size: size, qty: qty };
            $sample.append($('<option>').val(size).text(size));
        });

        $('#size-total').text(total);
        $('#sample-size-field').toggleClass('hidden', total <= 1);
        if (largest) {
            $sample.val($sample.find('option[value="' + current + '"]').length ? current : largest.size);
        }
    }

    $('#product_id').on('change', renderSizes);
    $('#size-inputs').on('input', '.size-qty', updateTotal);
    renderSizes();
});
</script>
@endpush
@endsection
