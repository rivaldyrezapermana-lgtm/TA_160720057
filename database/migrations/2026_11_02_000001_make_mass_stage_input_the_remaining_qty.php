<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tahap massal dulu menyimpan input sebagai jumlah yang diterima (diketik manual).
 * Sekarang input adalah sisa yang belum dikerjakan: output tahap sebelumnya
 * mengalir otomatis jadi input tahap berikutnya dan berkurang saat dikerjakan.
 * Cutting menerima seluruh target; tahap lain menerima output tahap sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $productions = DB::table('productions')->pluck('planned_qty', 'id');

        foreach ($productions as $productionId => $planned) {
            $stages = DB::table('production_stages')
                ->where('production_id', $productionId)
                ->where('phase', 'mass')
                ->whereIn('stage', ['cutting', 'sewing', 'qc_packing'])
                ->orderBy('sort_order')
                ->get();

            $received = (int) $planned;
            foreach ($stages as $stage) {
                DB::table('production_stages')->where('id', $stage->id)->update([
                    'input_qty' => max(0, $received - (int) $stage->output_qty),
                ]);
                $received = (int) $stage->output_qty;
            }
        }
    }

    public function down(): void
    {
        DB::table('production_stages')
            ->where('phase', 'mass')
            ->whereIn('stage', ['cutting', 'sewing', 'qc_packing'])
            ->update(['input_qty' => DB::raw('input_qty + output_qty')]);
    }
};
