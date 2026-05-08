@extends('layouts.admin')
@section('title', 'Pesanan')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Pesanan']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Pesanan</h1>
@endsection

@section('content')
<div class="bg-white border border-ink-100 rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-ink-100">
        <p class="text-sm text-ink-500">Daftar pesanan dari pelanggan.</p>
    </div>
    <div class="p-5">
        <table id="tbl-orders" class="table-clean" style="width:100%">
            <thead><tr><th>Kode</th><th>Pelanggan</th><th>Tanggal</th><th class="text-right">Total</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    $('#tbl-orders').DataTable({
        ajax: '{{ route("admin.datatables.orders") }}',
        columns: [
            { data: 'code' },
            { data: 'customer' },
            { data: 'date' },
            { data: 'total', className: 'text-right tabular-nums' },
            { data: 'status', render: d => {
                const m = { pending: 'badge-amber', paid: 'badge-blue', processing: 'badge-blue', shipped: 'badge-blue', completed: 'badge-green', cancelled: 'badge-red' };
                const l = { pending: 'Pending', paid: 'Dibayar', processing: 'Diproses', shipped: 'Dikirim', completed: 'Selesai', cancelled: 'Dibatalkan' };
                return `<span class="${m[d]}">${l[d]}</span>`;
            }},
            { data: 'id', className: 'text-right', render: id => `<a href="/admin/orders/${id}" class="text-ink-600 hover:text-ink-900 text-sm">Detail</a>` },
        ],
        pageLength: 10, order: [],
        language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_', info: 'Menampilkan _START_-_END_ dari _TOTAL_', zeroRecords: '–', paginate: { previous: '‹', next: '›' } }
    });
});
</script>
@endpush
@endsection
