<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = ['thread_id', 'sender_id', 'body', 'is_read'];

    protected $casts = ['is_read' => 'boolean'];

    public function thread() { return $this->belongsTo(ChatThread::class, 'thread_id'); }
    public function sender() { return $this->belongsTo(User::class, 'sender_id'); }
}
