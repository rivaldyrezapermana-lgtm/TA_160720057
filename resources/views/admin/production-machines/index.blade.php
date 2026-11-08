@extends('layouts.admin')
@section('title', 'Mesin Produksi')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Mesin Produksi']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Mesin Produksi</h1>
@endsection

@section('content')
<div class="bg-white border border-ink-100 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-ink-100">
        <p class="text-sm text-ink-500">Pengelolaan mesin yang digunakan pada batch produksi.</p>
        <a href="{{ route('admin.production-machines.create') }}" class="btn-primary">+ Tambah Mesin</a>
    </div>
    <div class="p-5">
        <table id="tbl-machines" class="table-clean" style="width:100%">
            <thead><tr><th>ID Mesin</th><th>Nama</th><th class="text-right">Kapasitas</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    $('#tbl-machines').DataTable({
        ajax: '{{ route("admin.datatables.production-machines") }}',
        columns: [
            { data: 'kode_mesin' },
            { data: 'name' },
            { data: 'capacity', className: 'text-right tabular-nums' },
            { data: 'status', render: d => {
                const map = { active: ['badge-green','Aktif'], maintenance: ['badge-amber','Perawatan'], inactive: ['badge-red','Nonaktif'] };
                const [cls, label] = map[d] || ['badge-gray', d];
                return `<span class="${cls}">${label}</span>`;
            } },
            { data: 'id', className: 'text-right', render: id => `<a href="/admin/production-machines/${id}/edit" class="text-ink-600 hover:text-ink-900 text-sm">Ubah</a>` },
        ],
        pageLength: 10, order: [[0, 'asc']],
        language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_', info: 'Menampilkan _START_-_END_ dari _TOTAL_', zeroRecords: 'Tidak ada hasil', emptyTable: 'Tidak ada data', infoEmpty: 'Tidak ada data', infoFiltered: '(disaring dari _MAX_ data)', loadingRecords: 'Memuat...', paginate: { previous: '‹', next: '›' } }
    });
});
</script>
@endpush
@endsection
