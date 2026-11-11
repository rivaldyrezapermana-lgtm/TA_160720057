@extends('layouts.admin')
@section('title', 'Ukuran')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Ukuran']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Ukuran</h1>
@endsection

@section('content')
<div class="bg-white border border-ink-100 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-ink-100">
        <p class="text-sm text-ink-500">Daftar ukuran yang tersedia untuk produk. Gunakan tombol ↑ ↓ untuk mengatur urutan tampil.</p>
        <a href="{{ route('admin.sizes.create') }}" class="btn-primary">+ Tambah Ukuran</a>
    </div>
    <div class="p-5">
        <table id="tbl-sizes" class="table-clean" style="width:100%">
            <thead><tr><th>Nama</th><th>Status</th><th class="!text-right">Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    const csrf = @json(csrf_token());
    // Urutan diatur dengan tombol panah, tanpa menampilkan angka urutan.
    const move = (id, direction, label, title, disabled) => `
        <form action="/admin/sizes/${id}/move" method="POST" class="inline">
            <input type="hidden" name="_token" value="${csrf}">
            <input type="hidden" name="_method" value="PATCH">
            <input type="hidden" name="direction" value="${direction}">
            <button title="${title}" aria-label="${title}" class="px-1.5 text-ink-600 hover:text-ink-900 disabled:opacity-30 disabled:cursor-not-allowed" ${disabled ? 'disabled' : ''}>${label}</button>
        </form>`;
    $('#tbl-sizes').DataTable({
        ajax: '{{ route("admin.datatables.sizes") }}',
        columns: [
            { data: 'name', orderable: false, render: $.fn.dataTable.render.text() },
            { data: 'used', orderable: false, render: d => d ? '<span class="badge-green">Digunakan</span>' : '<span class="badge-gray">Belum Digunakan</span>' },
            { data: 'id', className: 'text-right whitespace-nowrap', orderable: false, render: (id, type, row) => `
                ${move(id, 'up', '↑', 'Pindah ke atas', row.first)}${move(id, 'down', '↓', 'Pindah ke bawah', row.last)}
                <a href="/admin/sizes/${id}/edit" class="ml-3 text-ink-600 hover:text-ink-900 text-sm">Ubah</a>
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
