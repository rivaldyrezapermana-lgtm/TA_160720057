<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian per ukuran di tiap tahap massal (cutting, sewing, QC & packing).
 * Sama seperti total di production_stages: input_qty = sisa yang belum
 * dikerjakan, output_qty = total yang sudah diteruskan ke tahap berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_stage_sizes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_stage_id')->constrained()->cascadeOnDelete();
            $t->string('size', 10);
            $t->unsignedInteger('input_qty')->default(0);
            $t->unsignedInteger('output_qty')->default(0);
            $t->timestamps();
            $t->unique(['production_stage_id', 'size']);
        });

        // Batch berukuran yang tahap massalnya belum mengeluarkan apa pun bisa
        // langsung dirinci: cutting menerima target tiap ukuran. Batch yang sudah
        // berjalan tetap memakai total (pembagian per ukurannya tidak diketahui).
        $productionIds = DB::table('production_sizes')->distinct()->pluck('production_id');

        foreach ($productionIds as $productionId) {
            $stages = DB::table('production_stages')
                ->where('production_id', $productionId)
                ->where('phase', 'mass')
                ->whereIn('stage', ['cutting', 'sewing', 'qc_packing'])
                ->get();

            if ($stages->isEmpty() || $stages->sum('output_qty') > 0) {
                continue;
            }

            $sizes = DB::table('production_sizes')->where('production_id', $productionId)->orderBy('id')->get();

            foreach ($stages as $stage) {
                foreach ($sizes as $size) {
                    DB::table('production_stage_sizes')->insert([
                        'production_stage_id' => $stage->id,
                        'size' => $size->size,
                        'input_qty' => $stage->stage === 'cutting' ? (int) $size->planned_qty : 0,
                        'output_qty' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_stage_sizes');
    }
};
