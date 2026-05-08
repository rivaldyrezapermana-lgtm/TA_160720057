@extends('layouts.admin')
@section('title', 'Pembelian')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Pembelian Bahan']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Pembelian Bahan</h1>
@endsection

@section('content')
<div class="bg-white border border-ink-100 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-ink-100">
        <p class="text-sm text-ink-500">PO bahan baku ke supplier.</p>
        <a href="{{ route('admin.purchases.create') }}" class="btn-primary">+ PO Baru</a>
    </div>
    <div class="p-5">
        <table id="tbl-purchases" class="table-clean" style="width:100%">
            <thead><tr><th>Kode</th><th>Supplier</th><th>Tanggal</th><th class="text-right">Total</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    $('#tbl-purchases').DataTable({
        ajax: '{{ route("admin.datatables.purchases") }}',
        columns: [
            { data: 'code' },
            { data: 'supplier' },
            { data: 'date' },
            { data: 'total', className: 'text-right tabular-nums' },
            { data: 'status', render: d => {
                const map = { pending: 'badge-amber', received: 'badge-green', cancelled: 'badge-red' };
                const label = { pending: 'Pending', received: 'Diterima', cancelled: 'Dibatalkan' };
                return `<span class="${map[d]}">${label[d]}</span>`;
            }},
            { data: 'id', className: 'text-right', render: id => `<a href="/admin/purchases/${id}" class="text-ink-600 hover:text-ink-900 text-sm">Detail</a>` },
        ],
        pageLength: 10, order: [],
        language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_', info: 'Menampilkan _START_-_END_ dari _TOTAL_', zeroRecords: '–', paginate: { previous: '‹', next: '›' } }
    });
});
</script>
@endpush
@endsection
