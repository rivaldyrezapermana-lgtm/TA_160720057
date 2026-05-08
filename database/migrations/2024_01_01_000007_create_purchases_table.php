<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $t->string('code')->unique();
            $t->date('purchase_date');
            $t->decimal('total', 14, 2)->default(0);
            $t->enum('status', ['pending','received','cancelled'])->default('pending');
            $t->timestamps();
            $t->index(['status','purchase_date']);
        });

        Schema::create('purchase_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('material_id')->constrained()->cascadeOnDelete();
            $t->integer('qty');
            $t->decimal('unit_cost', 12, 2);
            $t->decimal('subtotal', 14, 2);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
