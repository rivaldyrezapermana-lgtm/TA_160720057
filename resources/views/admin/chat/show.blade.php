@extends('layouts.admin')
@section('title', 'Chat — '.($thread->customer->name ?? 'Pembeli'))
@section('breadcrumb')
    <x-admin.breadcrumb :items="[['label' => 'Live Chat', 'url' => route('admin.chat.index')], ['label' => $thread->customer->name ?? 'Pembeli']]" />
    <h1 class="font-display text-xl font-semibold text-ink-900">Chat — {{ $thread->customer->name ?? 'Pembeli' }}</h1>
@endsection

@section('content')
<div class="bg-white border border-ink-100 rounded-xl overflow-hidden grid grid-cols-1 md:grid-cols-3 h-[calc(100vh-200px)]">
    {{-- Sidebar --}}
    <div class="border-r border-ink-100 overflow-y-auto">
        <div class="px-5 py-4 border-b border-ink-100">
            <p class="font-semibold text-ink-900">Percakapan</p>
        </div>
        <div class="divide-y divide-ink-100">
            @foreach ($threads as $t)
                @php
                    $name = $t->customer->name ?? 'Pembeli';
                    $last = $t->messages->first();
                @endphp
                <a href="{{ route('admin.chat.show', $t->id) }}" class="block px-5 py-3 hover:bg-ink-50 {{ $t->id == $thread->id ? 'bg-ink-50' : '' }}">
                    <div class="flex justify-between items-start">
                        <div class="min-w-0">
                            <p class="font-medium text-sm">{{ $name }}</p>
                            <p class="text-xs text-ink-500 truncate mt-0.5">{{ $last?->body ?? '—' }}</p>
                        </div>
                        @if ($t->unread_count > 0 && $t->id != $thread->id)
                            <span class="inline-block bg-red-500 text-white text-[10px] min-w-[16px] h-4 px-1 rounded-full leading-4 text-center font-semibold">{{ $t->unread_count }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Chat Window --}}
    <div class="md:col-span-2 flex flex-col">
        <div class="px-5 py-4 border-b border-ink-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-ink-900 text-white flex items-center justify-center font-semibold text-sm">{{ strtoupper(substr($thread->customer->name ?? 'P', 0, 1)) }}</div>
            <div>
                <p class="font-medium">{{ $thread->customer->name ?? 'Pembeli' }}</p>
                <p class="text-xs text-emerald-600">● Online</p>
            </div>
        </div>

        <div id="chat-messages" class="flex-1 overflow-y-auto p-5 space-y-3 bg-ink-50">
            @forelse ($messages as $m)
                @php $mine = $m->sender_id !== $thread->customer_id; @endphp
                <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}" data-id="{{ $m->id }}">
                    <div class="max-w-md">
                        <div class="{{ $mine ? 'bg-ink-900 text-white' : 'bg-white border border-ink-100 text-ink-900' }} px-4 py-2.5 rounded-2xl text-sm whitespace-pre-wrap break-words">{{ $m->body }}</div>
                        <p class="text-xs text-ink-400 mt-1 {{ $mine ? 'text-right' : '' }}">{{ optional($m->created_at)->format('H:i') }}</p>
                    </div>
                </div>
            @empty
                <p id="chat-empty" class="text-center text-sm text-ink-400 py-8">Belum ada pesan di percakapan ini.</p>
            @endforelse
        </div>

        <form id="chat-form" class="border-t border-ink-100 p-4 flex items-center gap-2">
            @csrf
            <input type="text" name="body" id="chat-input" placeholder="Tulis pesan..." class="input flex-1" autocomplete="off" maxlength="2000" required>
            <button class="btn-primary" type="submit">Kirim</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const $messages = $('#chat-messages');
    const sendUrl = @json(route('admin.chat.send', $thread->id));
    const pollUrl = @json(route('admin.chat.poll', $thread->id));
    let lastId = {{ $messages->max('id') ?? 0 }};
    let polling = false;

    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    function bubble(m) {
        const align  = m.mine ? 'justify-end' : 'justify-start';
        const skin   = m.mine ? 'bg-ink-900 text-white' : 'bg-white border border-ink-100 text-ink-900';
        const tAlign = m.mine ? 'text-right' : '';
        return `
            <div class="flex ${align}" data-id="${m.id}">
                <div class="max-w-md">
                    <div class="${skin} px-4 py-2.5 rounded-2xl text-sm whitespace-pre-wrap break-words">${escapeHtml(m.body)}</div>
                    <p class="text-xs text-ink-400 mt-1 ${tAlign}">${m.time ?? ''}</p>
                </div>
            </div>`;
    }

    function append(m) {
        if (m.id <= lastId) return;
        $('#chat-empty').remove();
        $messages.append(bubble(m));
        lastId = m.id;
    }

    function scrollToBottom() {
        $messages.scrollTop($messages[0].scrollHeight);
    }

    $('#chat-form').on('submit', function (e) {
        e.preventDefault();
        const $input = $('#chat-input');
        const body = $input.val().trim();
        if (!body) return;
        $input.prop('disabled', true);

        $.post(sendUrl, { body: body })
            .done(function (res) {
                if (res && res.ok) {
                    append(res.message);
                    scrollToBottom();
                    $input.val('');
                }
            })
            .always(function () {
                $input.prop('disabled', false).focus();
            });
    });

    function poll() {
        if (polling) return;
        polling = true;
        $.get(pollUrl, { after_id: lastId })
            .done(function (res) {
                let added = false;
                (res.messages || []).forEach(function (m) {
                    append(m);
                    added = true;
                });
                if (added) scrollToBottom();
            })
            .always(function () { polling = false; });
    }

    setInterval(poll, 4000);
    scrollToBottom();
})();
</script>
@endpush
@endsection
