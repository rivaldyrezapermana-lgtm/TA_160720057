<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('machine_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->enum('stage', ['design', 'sample', 'cutting', 'sewing', 'qc', 'packing'])->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_categories');
    }
};
