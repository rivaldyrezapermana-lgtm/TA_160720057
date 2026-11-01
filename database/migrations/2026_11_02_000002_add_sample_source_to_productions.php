<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch yang memakai sampel dari batch lain produk yang sama tidak membuat
 * sampel sendiri; kolom ini mencatat batch asal sampelnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $t) {
            $t->foreignId('sample_source_production_id')->nullable()->after('sample_size')
                ->constrained('productions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('sample_source_production_id');
        });
    }
};
