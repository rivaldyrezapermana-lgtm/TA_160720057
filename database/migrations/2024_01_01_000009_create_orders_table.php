<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('code')->unique();
            $t->decimal('total', 14, 2)->default(0);
            $t->enum('status', ['pending','paid','processing','shipped','completed','cancelled'])->default('pending');
            $t->text('shipping_address');
            $t->timestamps();
            $t->index(['user_id','status']);
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('size', 10)->nullable();
            $t->integer('qty');
            $t->decimal('price', 12, 2);
            $t->decimal('subtotal', 14, 2);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->enum('method', ['transfer','cod','ewallet'])->default('transfer');
            $t->string('proof_image')->nullable();
            $t->decimal('amount', 14, 2);
            $t->enum('status', ['pending','verified','rejected'])->default('pending');
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
