@extends('layouts.admin')
@section('title', 'Live Chat')
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Live Chat']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Live Chat</h1>
@endsection

@section('content')
<div class="bg-white border border-ink-100 rounded-xl overflow-hidden grid grid-cols-1 md:grid-cols-3 min-h-[600px]">
    <div class="border-r border-ink-100">
        <div class="px-5 py-4 border-b border-ink-100">
            <p class="font-semibold text-ink-900">Percakapan</p>
            <p class="text-xs text-ink-500">{{ $threads->where('unread_count', '>', 0)->count() }} belum dibaca</p>
        </div>
        <div class="divide-y divide-ink-100">
            @forelse ($threads as $t)
                @php
                    $name = $t->customer->name ?? 'Pembeli';
                    $last = $t->messages->first();
                    $lastBody = $last?->body ?? '—';
                    $lastAt = $t->last_message_at ? $t->last_message_at->isToday()
                        ? $t->last_message_at->format('H:i')
                        : $t->last_message_at->diffForHumans() : '—';
                @endphp
                <a href="{{ route('admin.chat.show', $t->id) }}" class="block px-5 py-4 hover:bg-ink-50">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-full bg-ink-900 text-white flex items-center justify-center font-semibold text-sm flex-shrink-0">{{ strtoupper(substr($name, 0, 1)) }}</div>
                            <div class="min-w-0">
                                <p class="font-medium text-ink-900 truncate">{{ $name }}</p>
                                <p class="text-xs text-ink-500 truncate">{{ $lastBody }}</p>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-xs text-ink-500">{{ $lastAt }}</p>
                            @if ($t->unread_count > 0)
                                <span class="inline-block bg-red-500 text-white text-[10px] min-w-[16px] h-4 px-1 rounded-full mt-1 leading-4 text-center font-semibold">{{ $t->unread_count }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <p class="px-5 py-10 text-center text-sm text-ink-400">Belum ada percakapan.</p>
            @endforelse
        </div>
    </div>
    <div class="md:col-span-2 flex items-center justify-center text-ink-400">
        <div class="text-center">
            <svg class="w-12 h-12 mx-auto text-ink-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <p class="mt-3 text-sm">Pilih percakapan untuk membalas</p>
        </div>
    </div>
</div>
@endsection
