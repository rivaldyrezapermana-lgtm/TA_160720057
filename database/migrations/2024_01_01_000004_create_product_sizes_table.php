<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_sizes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('size', 10);
            $t->integer('chest_cm')->nullable();
            $t->integer('length_cm')->nullable();
            $t->integer('sleeve_cm')->nullable();
            $t->integer('stock')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
    public function down(): void { Schema::dropIfExists('product_sizes'); }
};
