@props(['id', 'columns', 'ajax' => null, 'createUrl' => null, 'createLabel' => 'Tambah Baru'])

<div class="bg-white border border-ink-100 rounded-xl overflow-hidden">
    @if ($createUrl)
        <div class="flex items-center justify-end gap-3 px-5 py-4 border-b border-ink-100">
            <a href="{{ $createUrl }}" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ $createLabel }}
            </a>
        </div>
    @endif

    <div class="p-5">
        <table id="{{ $id }}" class="table-clean w-full" style="width:100%">
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th>{{ is_array($col) ? $col['label'] : $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
$(function () {
    $('#{{ $id }}').DataTable({
        ajax: '{{ $ajax }}',
        columns: @json(collect($columns)->map(fn($c) => is_array($c) ? ['data' => $c['data']] : ['data' => $c])->all()),
        pageLength: 10,
        order: [],
        language: {
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_',
            info: 'Menampilkan _START_-_END_ dari _TOTAL_',
            infoEmpty: 'Tidak ada data',
            zeroRecords: 'Tidak ada hasil',
            paginate: { previous: '‹', next: '›' }
        }
    });
});
</script>
@endpush
