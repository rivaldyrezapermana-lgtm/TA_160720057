<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_machines', function (Blueprint $t) {
            $t->foreignId('machine_category_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_machines', function (Blueprint $t) {
            $t->dropConstrainedForeignId('machine_category_id');
        });
    }
};
