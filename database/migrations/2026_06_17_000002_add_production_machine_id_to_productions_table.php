<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $t) {
            $t->foreignId('production_machine_id')
                ->nullable()
                ->after('user_id')
                ->constrained('production_machines')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('production_machine_id');
        });
    }
};
