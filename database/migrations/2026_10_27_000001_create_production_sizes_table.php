<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_sizes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained()->cascadeOnDelete();
            $t->string('size', 10);
            $t->unsignedInteger('planned_qty');
            $t->unsignedInteger('actual_qty')->default(0);
            $t->timestamps();
            $t->unique(['production_id', 'size']);
        });

        Schema::table('productions', function (Blueprint $t) {
            // Ukuran potongan sampel; null untuk batch tanpa rincian ukuran.
            $t->string('sample_size', 10)->nullable()->after('sample_revision_count');
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $t) {
            $t->dropColumn('sample_size');
        });

        Schema::dropIfExists('production_sizes');
    }
};
