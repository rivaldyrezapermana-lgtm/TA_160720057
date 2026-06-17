<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_materials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('material_id')->constrained()->cascadeOnDelete();
            $t->integer('qty_required');
            $t->timestamps();
            $t->unique(['product_id', 'material_id']);
        });

        // Best-effort data migration: turn each production's recorded materials
        // into the product's bill of materials. Multiple productions share one
        // product, so we keep the latest (highest production_materials.id) row
        // per (product_id, material_id) pair. Done in PHP for driver portability.
        if (Schema::hasTable('production_materials')) {
            $latest = [];

            DB::table('production_materials as pm')
                ->join('productions as p', 'p.id', '=', 'pm.production_id')
                ->select('pm.id', 'p.product_id', 'pm.material_id', 'pm.qty_used')
                ->orderBy('pm.id')
                ->get()
                ->each(function ($row) use (&$latest) {
                    // Later rows overwrite earlier ones, leaving the latest qty.
                    $latest[$row->product_id.'-'.$row->material_id] = [
                        'product_id' => $row->product_id,
                        'material_id' => $row->material_id,
                        'qty_required' => $row->qty_used,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                });

            if ($latest !== []) {
                DB::table('product_materials')->insert(array_values($latest));
            }
        }

        Schema::dropIfExists('production_materials');
    }

    public function down(): void
    {
        // Recreate the original table shape. Backfilled data is NOT restored —
        // this migration is one-way for data.
        Schema::create('production_materials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained()->cascadeOnDelete();
            $t->foreignId('material_id')->constrained()->cascadeOnDelete();
            $t->integer('qty_used');
            $t->timestamps();
        });

        Schema::dropIfExists('product_materials');
    }
};
