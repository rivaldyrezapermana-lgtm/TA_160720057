@extends('layouts.customer')
@section('title', 'Chat dengan Penjual')

@section('content')
<div class="max-w-3xl mx-auto px-6 py-10">
    <h1 class="font-display text-3xl font-semibold mb-2">Chat dengan Penjual</h1>
    <p class="text-sm text-ink-500 mb-8">Tanyakan apa saja seputar produk atau pesanan Anda.</p>

    <div class="bg-white border border-ink-100 rounded-2xl overflow-hidden flex flex-col h-[500px]">
        <div class="px-5 py-3 border-b border-ink-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-ink-900 text-white flex items-center justify-center text-xs font-semibold">L</div>
            <div>
                <p class="text-sm font-medium">Toko Labasa</p>
                <p class="text-xs text-emerald-600">● Online</p>
            </div>
        </div>

        <div id="chat-messages" class="flex-1 overflow-y-auto p-5 space-y-3 bg-ink-50">
            @forelse ($messages as $m)
                @php $mine = $m->sender_id === auth()->id(); @endphp
                <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}" data-id="{{ $m->id }}">
                    <div class="max-w-md">
                        <div class="{{ $mine ? 'bg-ink-900 text-white' : 'bg-white border border-ink-100' }} px-4 py-2.5 rounded-2xl text-sm whitespace-pre-wrap break-words">{{ $m->body }}</div>
                        <p class="text-xs text-ink-400 mt-1 {{ $mine ? 'text-right' : '' }}">{{ optional($m->created_at)->format('H:i') }}</p>
                    </div>
                </div>
            @empty
                <p id="chat-empty" class="text-center text-sm text-ink-400 py-8">Belum ada pesan. Sapa penjual untuk memulai.</p>
            @endforelse
        </div>

        <form id="chat-form" class="border-t border-ink-100 p-3 flex gap-2">
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
    const sendUrl = @json(route('customer.chat.send'));
    const pollUrl = @json(route('customer.chat.poll'));
    let lastId = {{ $messages->max('id') ?? 0 }};
    let polling = false;

    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    function bubble(m) {
        const align = m.mine ? 'justify-end' : 'justify-start';
        const skin  = m.mine ? 'bg-ink-900 text-white' : 'bg-white border border-ink-100';
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
