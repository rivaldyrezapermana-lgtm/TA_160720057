<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatThread extends Model
{
    protected $fillable = ['customer_id', 'admin_id', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function admin() { return $this->belongsTo(User::class, 'admin_id'); }
    public function messages() { return $this->hasMany(ChatMessage::class, 'thread_id'); }
}
