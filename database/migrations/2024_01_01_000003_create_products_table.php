<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('sku')->unique();
            $t->text('description')->nullable();
            $t->decimal('price', 12, 2);
            $t->integer('stock')->default(0);
            $t->string('image')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['category_id','is_active']);
            $t->index('name');
        });
    }
    public function down(): void { Schema::dropIfExists('products'); }
};
