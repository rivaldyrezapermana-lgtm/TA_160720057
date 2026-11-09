@extends('layouts.admin')
@section('title', 'Pesanan Pembelian Baru')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Pembelian', 'url' => route('admin.purchases.index')], ['label' => 'Pesanan Pembelian Baru']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Buat Pesanan Pembelian Baru</h1>
@endsection

@section('content')
@php
    // Baris dari old() memakai indeks aslinya agar pesan galat per kolom tetap menempel.
    $oldItems = old('items');
    $rows = is_array($oldItems) ? array_filter($oldItems, 'is_array') : [];
    $rows = $rows ?: [0 => []];
    $nextIndex = max(array_map('intval', array_keys($rows))) + 1;
@endphp
<form action="{{ route('admin.purchases.store') }}" method="POST" class="max-w-5xl space-y-4">
    @csrf
    <x-ui.card title="Informasi Pesanan Pembelian">
        <div class="grid md:grid-cols-3 gap-4">
            <x-ui.select name="supplier_id" label="Pemasok" required :options="$suppliers->pluck('name','id')->toArray()" />
            <x-ui.input name="purchase_date" type="date" label="Tanggal" required />
            <x-ui.input name="code" label="Kode Pesanan Pembelian" placeholder="Otomatis" />
        </div>
    </x-ui.card>

    <x-ui.card title="Item Pembelian" subtitle="Daftar bahan yang dibeli. Harga per unit diisi sesuai harga dari pemasok.">
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th class="w-[32%]">Bahan</th>
                        <th class="w-[14%] !text-right">Jumlah</th>
                        <th>Satuan</th>
                        <th class="w-[20%] !text-right">Harga per Unit</th>
                        <th class="!text-right">Subtotal</th>
                        <th><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody id="purchase-items" data-next-index="{{ $nextIndex }}">
                    @foreach ($rows as $index => $row)
                        @include('admin.purchases._item-row', ['index' => $index, 'row' => $row])
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-ink-200">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right font-semibold">Total</td>
                        <td class="px-4 py-3 text-right font-display text-lg font-semibold tabular-nums whitespace-nowrap" id="purchase-total">Rp 0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @error('items')<p class="field-error">{{ $message }}</p>@enderror
        <div class="mt-3">
            <button type="button" id="purchase-add-item" class="btn btn-secondary">+ Tambah Bahan</button>
        </div>
    </x-ui.card>

    <x-ui.card>
        <x-ui.textarea name="notes" label="Catatan" rows="3" />
    </x-ui.card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.purchases.index') }}" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary">Simpan Pesanan Pembelian</button>
    </div>
</form>

<template id="purchase-item-template">
    @include('admin.purchases._item-row', ['index' => '__INDEX__', 'row' => []])
</template>
@endsection

@push('scripts')
<script>
    $(function () {
        const $body = $('#purchase-items');
        let nextIndex = Number($body.data('next-index'));

        function rupiah(value) {
            const rounded = Math.round(value * 100) / 100;
            const digits = Number.isInteger(rounded) ? 0 : 2;

            return 'Rp ' + rounded.toLocaleString('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: 2 });
        }

        function refresh() {
            let total = 0;

            $body.find('[data-item-row]').each(function () {
                const $row = $(this);
                const unit = $row.find('[data-item-material] option:selected').data('unit');
                const subtotal = (Number($row.find('[data-item-qty]').val()) || 0)
                    * (Number($row.find('[data-item-cost]').val()) || 0);

                $row.find('[data-item-unit]').text(unit || '—');
                $row.find('[data-item-subtotal]').text(rupiah(subtotal));
                total += subtotal;
            });

            $('#purchase-total').text(rupiah(total));
        }

        $('#purchase-add-item').on('click', function () {
            const html = $('#purchase-item-template').html().replaceAll('__INDEX__', nextIndex++);
            const $row = $($.parseHTML(html.trim())).filter('[data-item-row]');

            $body.append($row);
            $row.find('[data-item-material]').trigger('focus');
            refresh();
        });

        $body.on('click', '[data-item-remove]', function () {
            const $row = $(this).closest('[data-item-row]');

            // Baris terakhir dikosongkan saja agar formulir selalu punya satu baris.
            if ($body.find('[data-item-row]').length > 1) {
                $row.remove();
            } else {
                $row.find('select, input').val('');
                $row.find('.field-error').remove();
            }

            refresh();
        });

        $body.on('input change', 'select, input', refresh);

        refresh();
    });
</script>
@endpush
