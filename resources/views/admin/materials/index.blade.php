@extends('layouts.admin')
@section('title', 'Bahan Baku')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Bahan Baku']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Bahan Baku</h1>
@endsection

@section('content')
<div class="bg-white border border-ink-100 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-ink-100">
        <p class="text-sm text-ink-500">Pengelolaan stok bahan baku produksi.</p>
        <a href="{{ route('admin.materials.create') }}" class="btn-primary">+ Tambah Bahan</a>
    </div>
    <div class="p-5">
        <table id="tbl-materials" class="table-clean" style="width:100%">
            <thead><tr><th>Kode</th><th>Nama</th><th>Unit</th><th class="text-right">Stok</th><th class="text-right">Min</th><th class="text-right">Harga/Unit</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    $('#tbl-materials').DataTable({
        ajax: '{{ route("admin.datatables.materials") }}',
        columns: [
            { data: 'code' },
            { data: 'name' },
            { data: 'unit' },
            { data: 'stock', className: 'text-right tabular-nums' },
            { data: 'min_stock', className: 'text-right tabular-nums text-ink-500' },
            { data: 'unit_cost', className: 'text-right tabular-nums', render: d => 'Rp ' + d },
            { data: 'status', render: d => d === 'low'
                ? '<span class="badge-red">Stok Rendah</span>'
                : '<span class="badge-green">Aman</span>' },
            { data: 'id', className: 'text-right', render: id => `<a href="/admin/materials/${id}/edit" class="text-ink-600 hover:text-ink-900 text-sm">Edit</a>` },
        ],
        pageLength: 10, order: [],
        language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_', info: 'Menampilkan _START_-_END_ dari _TOTAL_', zeroRecords: '–', paginate: { previous: '‹', next: '›' } }
    });
});
</script>
@endpush
@endsection
