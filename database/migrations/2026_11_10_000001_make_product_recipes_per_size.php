<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Selisih bahan antar ukuran biasanya pecahan (2,25 m dan 2,5 m), jadi
        // jumlah resep, pemakaian, dan stok bahan tidak lagi bilangan bulat.
        Schema::table('materials', function (Blueprint $t) {
            $t->decimal('stock', 12, 2)->default(0)->change();
        });
        Schema::table('production_materials', function (Blueprint $t) {
            $t->decimal('qty_used', 12, 2)->change();
        });

        Schema::table('product_materials', function (Blueprint $t) {
            $t->decimal('qty_required', 10, 2)->change();
            $t->string('size', 10)->nullable()->after('material_id');
        });
        // Index baru dibuat lebih dulu: MySQL memakai index unik lama untuk foreign
        // key product_id, jadi index itu baru boleh dihapus setelah ada penggantinya.
        Schema::table('product_materials', function (Blueprint $t) {
            $t->unique(['product_id', 'material_id', 'size'], 'product_material_size_unique');
        });
        Schema::table('product_materials', function (Blueprint $t) {
            $t->dropUnique(['product_id', 'material_id']);
        });

        // Resep lama berlaku untuk semua ukuran, jadi disalin ke tiap ukuran produk
        // supaya HPP tidak berubah. Produk tanpa ukuran tetap memakai baris tanpa ukuran.
        $sizes = DB::table('product_sizes')->get(['product_id', 'size'])->groupBy('product_id');
        $now = now();

        DB::table('product_materials')->whereNull('size')->orderBy('id')->get()
            ->each(function ($line) use ($sizes, $now) {
                $productSizes = $sizes->get($line->product_id);
                if ($productSizes === null) {
                    return;
                }

                foreach ($productSizes as $row) {
                    DB::table('product_materials')->insert([
                        'product_id' => $line->product_id,
                        'material_id' => $line->material_id,
                        'size' => $row->size,
                        'qty_required' => $line->qty_required,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('product_materials')->where('id', $line->id)->delete();
            });
    }

    public function down(): void
    {
        // Kolom lama hanya menampung satu jumlah per bahan, jadi rollback
        // menyimpan jumlah terbesar dan membulatkannya ke atas.
        $now = now();
        $lines = DB::table('product_materials')->get()
            ->groupBy(fn ($line) => $line->product_id.'-'.$line->material_id)
            ->map(fn ($rows) => [
                'product_id' => $rows->first()->product_id,
                'material_id' => $rows->first()->material_id,
                'qty_required' => (int) ceil((float) $rows->max('qty_required')),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        DB::table('product_materials')->delete();

        Schema::table('product_materials', function (Blueprint $t) {
            $t->unique(['product_id', 'material_id']);
        });
        Schema::table('product_materials', function (Blueprint $t) {
            $t->dropUnique('product_material_size_unique');
        });
        Schema::table('product_materials', function (Blueprint $t) {
            $t->dropColumn('size');
            $t->integer('qty_required')->change();
        });

        foreach ($lines as $line) {
            DB::table('product_materials')->insert($line);
        }

        Schema::table('production_materials', function (Blueprint $t) {
            $t->integer('qty_used')->change();
        });
        Schema::table('materials', function (Blueprint $t) {
            $t->integer('stock')->default(0)->change();
        });
    }
};
