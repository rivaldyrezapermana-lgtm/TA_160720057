<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $t->text('body');
            $t->boolean('is_read')->default(false);
            $t->timestamps();
            $t->index(['thread_id','created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_threads');
    }
};
