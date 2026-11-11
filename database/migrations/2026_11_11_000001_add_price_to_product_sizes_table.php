<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sizes', function (Blueprint $t) {
            // Kosong berarti ukuran ini mengikuti harga utama produk.
            $t->decimal('price', 12, 2)->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('product_sizes', function (Blueprint $t) {
            $t->dropColumn('price');
        });
    }
};
