@extends('layouts.admin')
@section('title', 'Ukuran')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Ukuran']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Ukuran</h1>
@endsection

@section('content')
<div class="bg-white border border-ink-100 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-ink-100">
        <p class="text-sm text-ink-500">Daftar ukuran yang tersedia untuk produk.</p>
        <a href="{{ route('admin.sizes.create') }}" class="btn-primary">+ Tambah Ukuran</a>
    </div>
    <div class="p-5">
        <table id="tbl-sizes" class="table-clean" style="width:100%">
            <thead><tr><th class="text-right">Urutan</th><th>Nama</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    const csrf = @json(csrf_token());
    $('#tbl-sizes').DataTable({
        ajax: '{{ route("admin.datatables.sizes") }}',
        columns: [
            { data: 'sort_order', className: 'text-right tabular-nums' },
            { data: 'name', render: $.fn.dataTable.render.text() },
            { data: 'used', render: d => d ? '<span class="badge-green">Digunakan</span>' : '<span class="badge-gray">Belum Digunakan</span>' },
            { data: 'id', className: 'text-right', orderable: false, render: id => `
                <a href="/admin/sizes/${id}/edit" class="text-ink-600 hover:text-ink-900 text-sm">Ubah</a>
                <form action="/admin/sizes/${id}" method="POST" class="inline" onsubmit="return confirm('Hapus ukuran ini?')">
                    <input type="hidden" name="_token" value="${csrf}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="text-red-600 hover:text-red-700 text-sm ml-3">Hapus</button>
                </form>` },
        ],
        pageLength: 10, order: [],
        language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_', info: 'Menampilkan _START_-_END_ dari _TOTAL_', zeroRecords: 'Tidak ada hasil', emptyTable: 'Tidak ada data', infoEmpty: 'Tidak ada data', infoFiltered: '(disaring dari _MAX_ data)', loadingRecords: 'Memuat...', paginate: { previous: '‹', next: '›' } }
    });
});
</script>
@endpush
@endsection
