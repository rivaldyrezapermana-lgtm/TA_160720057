<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->string('unit', 20);
            $t->integer('stock')->default(0);
            $t->integer('min_stock')->default(0);
            $t->decimal('unit_cost', 12, 2)->default(0);
            $t->timestamps();
            $t->index('stock');
        });
    }
    public function down(): void { Schema::dropIfExists('materials'); }
};
