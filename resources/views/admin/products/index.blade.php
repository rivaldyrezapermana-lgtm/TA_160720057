@extends('layouts.admin')
@section('title', 'Produk')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Produk']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Produk</h1>
@endsection

@section('content')
<div class="bg-white border border-ink-100 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-ink-100">
        <p class="text-sm text-ink-500">Daftar seluruh produk yang tersedia di toko.</p>
        <a href="{{ route('admin.products.create') }}" class="btn-primary">+ Tambah Produk</a>
    </div>
    <div class="p-5">
        <table id="tbl-products" class="table-clean" style="width:100%">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Stok</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
$(function () {
    $('#tbl-products').DataTable({
        ajax: '{{ route("admin.datatables.products") }}',
        columns: [
            { data: 'sku' },
            { data: 'name' },
            { data: 'category' },
            { data: 'price', className: 'text-right tabular-nums', render: d => 'Rp ' + d },
            { data: 'stock', className: 'text-right tabular-nums' },
            { data: 'status', render: d => d === 'active'
                ? '<span class="badge-green">Aktif</span>'
                : '<span class="badge-gray">Nonaktif</span>'
            },
            { data: 'id', className: 'text-right', render: id =>
                `<a href="/admin/products/${id}" class="text-ink-600 hover:text-ink-900 text-sm">Detail</a> ·
                 <a href="/admin/products/${id}/edit" class="text-ink-600 hover:text-ink-900 text-sm">Edit</a>`
            },
        ],
        pageLength: 10,
        order: [],
        language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_', info: 'Menampilkan _START_-_END_ dari _TOTAL_', infoEmpty: '0', zeroRecords: 'Tidak ada hasil', paginate: { previous: '‹', next: '›' } }
    });
});
</script>
@endpush
@endsection
