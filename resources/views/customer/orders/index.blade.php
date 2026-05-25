@extends('layouts.customer')
@section('title', 'Pesanan Saya')

@section('content')
<div class="max-w-5xl mx-auto px-6 py-10">
    <h1 class="font-display text-4xl font-semibold mb-8">Pesanan Saya</h1>

    @if ($orders->isEmpty())
        <div class="border border-dashed border-ink-200 rounded-xl p-16 text-center">
            <p class="font-display text-xl text-ink-700">Belum ada pesanan</p>
            <a href="{{ route('shop.index') }}" class="btn-primary mt-4 inline-flex">Belanja Sekarang</a>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($orders as $o)
                <a href="{{ route('customer.orders.show', $o->id) }}" class="block bg-white border border-ink-100 rounded-xl p-5 hover:border-ink-300 transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-ink-900">{{ $o->code }}</p>
                            <p class="text-sm text-ink-500">{{ $o->created_at->translatedFormat('d M Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-display text-xl font-semibold tabular-nums">Rp {{ number_format($o->total, 0, ',', '.') }}</p>
                            <x-ui.status-badge :status="$o->status" />
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
