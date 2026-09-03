<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_sample_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision_no');
            $t->text('notes');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->unique(['production_id', 'revision_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_sample_revisions');
    }
};
