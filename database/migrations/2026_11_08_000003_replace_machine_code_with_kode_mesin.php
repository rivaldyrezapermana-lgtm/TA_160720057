<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_machines', function (Blueprint $t) {
            $t->string('kode_mesin', 20)->nullable()->after('id');
        });

        // Kode baru mengikuti urutan id, jadi mesin lama mendapat MSN-001 dst.
        DB::table('production_machines')->orderBy('id')->pluck('id')
            ->each(function ($id, $i) {
                DB::table('production_machines')->where('id', $id)
                    ->update(['kode_mesin' => sprintf('MSN-%03d', $i + 1)]);
            });

        Schema::table('production_machines', function (Blueprint $t) {
            $t->string('kode_mesin', 20)->nullable(false)->change();
            $t->unique('kode_mesin');
        });

        Schema::table('production_machines', function (Blueprint $t) {
            $t->dropUnique(['code']);
        });

        Schema::table('production_machines', function (Blueprint $t) {
            $t->dropColumn('code');
        });
    }

    public function down(): void
    {
        Schema::table('production_machines', function (Blueprint $t) {
            $t->string('code')->nullable()->after('name');
        });

        // Kode lama tidak disimpan saat up(), jadi rollback memakai kode MSN.
        DB::table('production_machines')->update(['code' => DB::raw('kode_mesin')]);

        Schema::table('production_machines', function (Blueprint $t) {
            $t->string('code')->nullable(false)->change();
            $t->unique('code');
        });

        Schema::table('production_machines', function (Blueprint $t) {
            $t->dropUnique(['kode_mesin']);
        });

        Schema::table('production_machines', function (Blueprint $t) {
            $t->dropColumn('kode_mesin');
        });
    }
};
