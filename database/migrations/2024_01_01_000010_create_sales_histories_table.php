<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->smallInteger('year');
            $t->tinyInteger('month');
            $t->integer('demand')->default(0);
            $t->integer('stock_end')->default(0);
            $t->integer('produced')->default(0);
            $t->timestamps();
            $t->unique(['product_id','year','month']);
        });
    }
    public function down(): void { Schema::dropIfExists('sales_histories'); }
};
