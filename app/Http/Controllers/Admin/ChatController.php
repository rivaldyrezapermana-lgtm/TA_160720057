<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        $threads = $this->threadList();

        return view('admin.chat.index', compact('threads'));
    }

    public function show(ChatThread $thread)
    {
        $thread->load('customer');

        ChatMessage::where('thread_id', $thread->id)
            ->where('sender_id', $thread->customer_id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $threads = $this->threadList();
        $messages = $thread->messages()->orderBy('id')->get();

        return view('admin.chat.show', [
            'thread' => $thread,
            'threads' => $threads,
            'messages' => $messages,
        ]);
    }

    public function send(Request $request, ChatThread $thread)
    {
        $data = $request->validate(['body' => 'required|string|max:2000']);

        $message = $thread->messages()->create([
            'sender_id' => auth()->id(),
            'body' => $data['body'],
            'is_read' => false,
        ]);

        $thread->forceFill([
            'admin_id' => auth()->id(),
            'last_message_at' => $message->created_at,
        ])->save();

        return response()->json([
            'ok' => true,
            'message' => $this->format($message, $thread),
        ]);
    }

    public function poll(Request $request, ChatThread $thread)
    {
        $afterId = (int) $request->query('after_id', 0);

        $messages = $thread->messages()
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get();

        ChatMessage::where('thread_id', $thread->id)
            ->where('sender_id', $thread->customer_id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'messages' => $messages->map(fn ($m) => $this->format($m, $thread))->all(),
        ]);
    }

    private function threadList()
    {
        return ChatThread::with('customer')
            ->withCount(['messages as unread_count' => function ($q) {
                $q->where('is_read', false)
                  ->whereColumn('chat_messages.sender_id', 'chat_threads.customer_id');
            }])
            ->with(['messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->orderByDesc('last_message_at')
            ->get();
    }

    private function format(ChatMessage $m, ChatThread $thread): array
    {
        return [
            'id' => $m->id,
            'mine' => $m->sender_id !== $thread->customer_id,
            'body' => $m->body,
            'time' => optional($m->created_at)->format('H:i'),
        ];
    }
}
