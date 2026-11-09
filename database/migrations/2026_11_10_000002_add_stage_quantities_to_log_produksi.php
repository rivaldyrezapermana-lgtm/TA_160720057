<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom jumlah kosong pada catatan Pause, terisi pada catatan hasil harian.
        Schema::table('log_produksi', function (Blueprint $t) {
            $t->foreignId('production_stage_id')->nullable()->after('produksi_id')->constrained()->nullOnDelete();
            $t->unsignedInteger('input_qty')->nullable()->after('keterangan');
            $t->unsignedInteger('output_qty')->nullable()->after('input_qty');
            $t->unsignedInteger('sisa_qty')->nullable()->after('output_qty');
        });
    }

    public function down(): void
    {
        Schema::table('log_produksi', function (Blueprint $t) {
            $t->dropConstrainedForeignId('production_stage_id');
            $t->dropColumn(['input_qty', 'output_qty', 'sisa_qty']);
        });
    }
};
