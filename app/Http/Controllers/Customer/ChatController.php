<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        $thread = $this->thread();
        $messages = $thread->messages()->orderBy('id')->get();

        ChatMessage::where('thread_id', $thread->id)
            ->where('sender_id', '!=', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return view('customer.chat.index', compact('thread', 'messages'));
    }

    public function send(Request $request)
    {
        $data = $request->validate(['body' => 'required|string|max:2000']);
        $thread = $this->thread();

        $message = $thread->messages()->create([
            'sender_id' => auth()->id(),
            'body' => $data['body'],
            'is_read' => false,
        ]);

        $thread->forceFill(['last_message_at' => $message->created_at])->save();

        return response()->json([
            'ok' => true,
            'message' => $this->format($message),
        ]);
    }

    public function poll(Request $request)
    {
        $thread = $this->thread();
        $afterId = (int) $request->query('after_id', 0);

        $messages = $thread->messages()
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get();

        ChatMessage::where('thread_id', $thread->id)
            ->where('sender_id', '!=', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'messages' => $messages->map(fn ($m) => $this->format($m))->all(),
        ]);
    }

    private function thread(): ChatThread
    {
        return ChatThread::firstOrCreate(
            ['customer_id' => auth()->id()],
            ['last_message_at' => now()]
        );
    }

    private function format(ChatMessage $m): array
    {
        return [
            'id' => $m->id,
            'mine' => $m->sender_id === auth()->id(),
            'body' => $m->body,
            'time' => optional($m->created_at)->format('H:i'),
        ];
    }
}
