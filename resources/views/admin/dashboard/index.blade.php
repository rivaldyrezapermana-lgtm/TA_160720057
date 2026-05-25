@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
<div class="mb-6">
    <h1 class="font-display text-2xl font-semibold text-ink-900">Selamat datang kembali</h1>
    <p class="text-sm text-ink-500 mt-1">Ringkasan operasional Toko Labasa hari ini.</p>
</div>

{{-- Stats grid --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <x-admin.stat-card
        label="Total Produk"
        :value="number_format($stats['total_products'])"
        sub="produk aktif"
        tone="default"
        icon='<svg class=&quot;w-5 h-5&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; viewBox=&quot;0 0 24 24&quot;><path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;1.5&quot; d=&quot;M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10&quot;/></svg>'
    />
    <x-admin.stat-card
        label="Bahan Stok Rendah"
        :value="$stats['low_stock_materials']"
        sub="perlu re-stock"
        tone="red"
        icon='<svg class=&quot;w-5 h-5&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; viewBox=&quot;0 0 24 24&quot;><path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;1.5&quot; d=&quot;M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z&quot;/></svg>'
    />
    <x-admin.stat-card
        label="Produksi Aktif"
        :value="$stats['productions_running']"
        sub="batch berjalan"
        tone="blue"
        icon='<svg class=&quot;w-5 h-5&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; viewBox=&quot;0 0 24 24&quot;><path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;1.5&quot; d=&quot;M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z&quot;/></svg>'
    />
    <x-admin.stat-card
        label="Pesanan Pending"
        :value="$stats['orders_pending']"
        sub="menunggu verifikasi"
        tone="amber"
        icon='<svg class=&quot;w-5 h-5&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; viewBox=&quot;0 0 24 24&quot;><path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;1.5&quot; d=&quot;M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z&quot;/></svg>'
    />
    <x-admin.stat-card
        label="Penjualan Bulan Ini"
        :value="'Rp '.number_format($stats['sales_this_month']/1000000, 1).'jt'"
        sub="bruto"
        tone="green"
        icon='<svg class=&quot;w-5 h-5&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; viewBox=&quot;0 0 24 24&quot;><path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;1.5&quot; d=&quot;M13 7h8m0 0v8m0-8l-8 8-4-4-6 6&quot;/></svg>'
    />
</div>

{{-- Two-column: Running productions + Best sellers --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="lg:col-span-2">
        <x-ui.card title="Produksi Sedang Berjalan" subtitle="Batch yang aktif diproses minggu ini">
            <div class="space-y-4">
                @foreach ($runningProductions as $p)
                    <div class="flex items-center gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-ink-900 truncate">{{ $p['product'] }}</p>
                                <x-ui.badge tone="blue">{{ $p['stage'] }}</x-ui.badge>
                            </div>
                            <p class="text-xs text-ink-500 mt-0.5">{{ $p['code'] }} · {{ $p['actual'] }}/{{ $p['planned'] }} unit</p>
                            <div class="mt-2 h-1.5 bg-ink-100 rounded-full overflow-hidden">
                                <div class="h-full bg-ink-900 rounded-full" style="width: {{ $p['progress'] }}%"></div>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-ink-900 tabular-nums">{{ $p['progress'] }}%</span>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </div>
    <div>
        <x-ui.card title="Best Seller" subtitle="30 hari terakhir">
            <div class="space-y-3">
                @foreach ($bestSellers as $i => $b)
                    <div class="flex items-center gap-3">
                        <span class="font-display text-lg font-semibold text-ink-300 w-6">{{ str_pad($i+1, 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-ink-900 truncate">{{ $b['name'] }}</p>
                            <p class="text-xs text-ink-500">{{ $b['sold'] }} terjual</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </div>
</div>

{{-- Low stock + Recent orders --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <x-ui.card title="Stok Bahan Rendah" subtitle="Segera lakukan pembelian">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] uppercase tracking-wider text-ink-500 border-b border-ink-100">
                    <th class="pb-2 font-semibold">Bahan</th>
                    <th class="pb-2 font-semibold text-right">Stok</th>
                    <th class="pb-2 font-semibold text-right">Min</th>
                    <th class="pb-2 font-semibold"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lowStock as $m)
                    <tr class="border-b border-ink-100 last:border-0">
                        <td class="py-3 font-medium text-ink-900">{{ $m['name'] }}</td>
                        <td class="py-3 text-right tabular-nums text-red-700 font-semibold">{{ $m['stock'] }} {{ $m['unit'] }}</td>
                        <td class="py-3 text-right tabular-nums text-ink-500">{{ $m['min'] }}</td>
                        <td class="py-3 text-right"><x-ui.badge tone="red">Rendah</x-ui.badge></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <a href="{{ route('admin.materials.index') }}" class="text-sm text-ink-600 hover:text-ink-900 mt-3 inline-block">Lihat semua bahan baku →</a>
    </x-ui.card>

    <x-ui.card title="Pesanan Terbaru" subtitle="3 pesanan masuk terakhir">
        <div class="space-y-3">
            @foreach ($recentOrders as $o)
                <a href="{{ route('admin.orders.show', $o['id']) }}" class="flex items-center gap-3 -mx-2 px-2 py-2 rounded-lg hover:bg-ink-50">
                    <div class="w-9 h-9 rounded-full bg-ink-100 flex items-center justify-center text-ink-600 font-semibold text-sm flex-shrink-0">
                        {{ strtoupper(substr($o['customer'], 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-ink-900">{{ $o['customer'] }}</p>
                        <p class="text-xs text-ink-500">{{ $o['code'] }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-ink-900">Rp {{ number_format($o['total'], 0, ',', '.') }}</p>
                        <x-ui.status-badge :status="$o['status']" />
                    </div>
                </a>
            @endforeach
        </div>
        <a href="{{ route('admin.orders.index') }}" class="text-sm text-ink-600 hover:text-ink-900 mt-3 inline-block">Lihat semua pesanan →</a>
    </x-ui.card>
</div>
@endsection
