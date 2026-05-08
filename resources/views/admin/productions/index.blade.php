@extends('layouts.admin')
@section('title', 'Produksi')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Produksi']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Produksi</h1>
@endsection

@section('content')
<div class="bg-white border border-ink-100 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-ink-100">
        <p class="text-sm text-ink-500">Manajemen batch produksi.</p>
        <a href="{{ route('admin.productions.create') }}" class="btn-primary">+ Batch Baru</a>
    </div>
    <div class="p-5">
        <table id="tbl-productions" class="table-clean" style="width:100%">
            <thead><tr><th>Kode</th><th>Produk</th><th class="text-right">Plan</th><th class="text-right">Aktual</th><th>Mulai</th><th>Selesai</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    $('#tbl-productions').DataTable({
        ajax: '{{ route("admin.datatables.productions") }}',
        columns: [
            { data: 'code' },
            { data: 'product' },
            { data: 'planned', className: 'text-right tabular-nums' },
            { data: 'actual', className: 'text-right tabular-nums' },
            { data: 'start' },
            { data: 'end' },
            { data: 'status', render: d => {
                const m = { planned: 'badge-gray', in_progress: 'badge-blue', qc: 'badge-amber', completed: 'badge-green', cancelled: 'badge-red' };
                const l = { planned: 'Direncanakan', in_progress: 'Berjalan', qc: 'QC', completed: 'Selesai', cancelled: 'Dibatalkan' };
                return `<span class="${m[d]}">${l[d]}</span>`;
            }},
            { data: 'id', className: 'text-right', render: id => `<a href="/admin/productions/${id}" class="text-ink-600 hover:text-ink-900 text-sm">Detail</a>` },
        ],
        pageLength: 10, order: [],
        language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_', info: 'Menampilkan _START_-_END_ dari _TOTAL_', zeroRecords: '–', paginate: { previous: '‹', next: '›' } }
    });
});
</script>
@endpush
@endsection
