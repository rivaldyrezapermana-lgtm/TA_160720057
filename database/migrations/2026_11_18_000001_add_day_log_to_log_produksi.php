<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // jenis: mulai (awal hari), hasil (pcs dari Simpan), selesai_hari (ringkasan
        // Selesai Hari Ini), atau pause. rincian menyimpan pcs per ukuran, dan pada
        // ringkasan juga per tahap.
        Schema::table('log_produksi', function (Blueprint $t) {
            $t->string('jenis', 20)->nullable()->after('produksi_id');
            $t->json('rincian')->nullable()->after('sisa_qty');
        });

        DB::table('log_produksi')->whereNotNull('output_qty')->update(['jenis' => 'hasil']);
        DB::table('log_produksi')->whereNull('output_qty')->update(['jenis' => 'pause']);
    }

    public function down(): void
    {
        DB::table('log_produksi')->whereIn('jenis', ['mulai', 'selesai_hari'])->delete();

        Schema::table('log_produksi', function (Blueprint $t) {
            $t->dropColumn(['jenis', 'rincian']);
        });
    }
};
