<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('productions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('code')->unique();
            $t->integer('planned_qty');
            $t->integer('actual_qty')->default(0);
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->enum('status', ['planned','in_progress','qc','completed','cancelled'])->default('planned');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['status','start_date']);
        });

        Schema::create('production_materials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained()->cascadeOnDelete();
            $t->foreignId('material_id')->constrained()->cascadeOnDelete();
            $t->integer('qty_used');
        });

        Schema::create('production_stages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained()->cascadeOnDelete();
            $t->enum('stage', ['design','sample','cutting','sewing','qc','packing']);
            $t->enum('status', ['pending','in_progress','completed'])->default('pending');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->text('notes')->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('production_stages');
        Schema::dropIfExists('production_materials');
        Schema::dropIfExists('productions');
    }
};
