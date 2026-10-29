<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_design_sketches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_design_sketches');
    }
};
