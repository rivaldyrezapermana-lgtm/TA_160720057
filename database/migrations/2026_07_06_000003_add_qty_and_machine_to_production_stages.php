<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_stages', function (Blueprint $t) {
            $t->integer('input_qty')->default(0)->after('status');
            $t->integer('output_qty')->default(0)->after('input_qty');
            $t->foreignId('production_machine_id')->nullable()->after('output_qty')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_stages', function (Blueprint $t) {
            $t->dropConstrainedForeignId('production_machine_id');
            $t->dropColumn(['input_qty', 'output_qty']);
        });
    }
};
