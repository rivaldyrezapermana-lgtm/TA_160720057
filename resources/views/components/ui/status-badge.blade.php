@props(['status'])
@php
    $map = [
        'active' => ['green','Aktif'], 'inactive' => ['gray','Nonaktif'],
        'ok' => ['green','Aman'], 'low' => ['red','Stok Rendah'],
        'planned' => ['gray','Direncanakan'], 'in_progress' => ['blue','Berjalan'],
        'qc' => ['amber','Quality Check'], 'completed' => ['green','Selesai'],
        'cancelled' => ['red','Dibatalkan'],
        'pending' => ['amber','Pending'], 'paid' => ['blue','Dibayar'],
        'processing' => ['blue','Diproses'], 'shipped' => ['blue','Dikirim'],
        'received' => ['green','Diterima'], 'verified' => ['green','Terverifikasi'],
        'rejected' => ['red','Ditolak'],
    ];
    [$tone, $label] = $map[$status] ?? ['gray', ucfirst($status)];
@endphp
<x-ui.badge :tone="$tone">{{ $label }}</x-ui.badge>
