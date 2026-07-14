<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('production_materials')) {
            Schema::create('production_materials', function (Blueprint $t) {
                $t->id();
                $t->foreignId('production_id')->constrained()->cascadeOnDelete();
                $t->foreignId('material_id')->constrained()->cascadeOnDelete();
                $t->integer('qty_used');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_materials');
    }
};
