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
                    <th>Ukuran</th>
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
            { data: 'sizes', orderable: false, render: (sizes, type) => {
                if (! sizes.length) return type === 'display' ? '<span class="text-ink-400">—</span>' : '';
                if (type !== 'display') return sizes.map(s => s.size).join(' ');
                return sizes.map(s => `<span class="inline-flex items-center gap-1 mr-1 mb-1 px-1.5 py-0.5 rounded border text-xs ${s.stock > 0 ? 'border-ink-200 text-ink-700' : 'border-ink-100 text-ink-300'}">${$('<span>').text(s.size).html()}<span class="tabular-nums text-ink-500">${s.stock}</span></span>`).join('');
            } },
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
