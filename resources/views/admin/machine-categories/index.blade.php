@extends('layouts.admin')
@section('title', 'Kategori Mesin')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Kategori Mesin']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Kategori Mesin</h1>
@endsection

@section('content')
<div class="bg-white border border-ink-100 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-ink-100">
        <p class="text-sm text-ink-500">Kelompok mesin menurut fungsi/tahap produksi.</p>
        <a href="{{ route('admin.machine-categories.create') }}" class="btn-primary">+ Tambah Kategori</a>
    </div>
    <div class="p-5">
        <table id="tbl-cats" class="table-clean" style="width:100%">
            <thead><tr><th>Kode</th><th>Nama</th><th>Tahap</th><th class="text-right">Jml Mesin</th><th class="text-right">Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    const labels = @json(collect(\App\Models\ProductionStage::STAGES)->mapWithKeys(fn ($s) => [$s => \App\Models\ProductionStage::stageLabel($s)]));
    $('#tbl-cats').DataTable({
        ajax: '{{ route("admin.datatables.machine-categories") }}',
        columns: [
            { data: 'code' },
            { data: 'name' },
            { data: 'stage', render: d => labels[d] || d },
            { data: 'machines', className: 'text-right tabular-nums' },
            { data: 'id', className: 'text-right', render: id => `<a href="/admin/machine-categories/${id}/edit" class="text-ink-600 hover:text-ink-900 text-sm">Edit</a>` },
        ],
        pageLength: 10, order: [],
        language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_', info: 'Menampilkan _START_-_END_ dari _TOTAL_', zeroRecords: '–', paginate: { previous: '‹', next: '›' } }
    });
});
</script>
@endpush
@endsection
