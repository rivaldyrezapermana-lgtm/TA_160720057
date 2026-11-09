<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_stage_machine', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_stage_id')->constrained()->cascadeOnDelete();
            $t->foreignId('production_machine_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['production_stage_id', 'production_machine_id'], 'stage_machine_unique');
        });

        // Mesin yang sudah tercatat pada tiap tahap ikut dipindahkan.
        $now = now();
        DB::table('production_stages')->whereNotNull('production_machine_id')->orderBy('id')
            ->get(['id', 'production_machine_id'])
            ->each(fn ($stage) => DB::table('production_stage_machine')->insert([
                'production_stage_id' => $stage->id,
                'production_machine_id' => $stage->production_machine_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

        // Tabel ini pernah dibuat ulang dengan nama production_stages_new, jadi nama
        // foreign key-nya bukan nama standar dan harus dicari dari skema database.
        $foreignKey = collect(Schema::getForeignKeys('production_stages'))
            ->first(fn (array $key) => $key['columns'] === ['production_machine_id']);

        Schema::table('production_stages', function (Blueprint $t) use ($foreignKey) {
            $t->dropForeign($foreignKey['name'] ?? ['production_machine_id']);
        });

        Schema::table('production_stages', function (Blueprint $t) {
            $t->dropColumn('production_machine_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_stages', function (Blueprint $t) {
            $t->foreignId('production_machine_id')->nullable()->after('output_qty')->constrained()->nullOnDelete();
        });

        // Kolom lama hanya menampung satu mesin, jadi rollback menyimpan mesin pertama tiap tahap.
        DB::table('production_stage_machine')->orderByDesc('id')->get()
            ->each(fn ($row) => DB::table('production_stages')->where('id', $row->production_stage_id)
                ->update(['production_machine_id' => $row->production_machine_id]));

        Schema::dropIfExists('production_stage_machine');
    }
};
