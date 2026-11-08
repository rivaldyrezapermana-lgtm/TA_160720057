<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sizes', function (Blueprint $t) {
            $t->id();
            $t->string('name', 10)->unique();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        // Selain ukuran baku, ambil semua ukuran yang sudah tersimpan supaya data lama tetap dikenali.
        $used = collect(['product_sizes', 'production_sizes', 'order_items'])
            ->flatMap(fn (string $table) => DB::table($table)->whereNotNull('size')->distinct()->pluck('size'))
            ->map(fn (string $size) => trim($size))
            ->filter()
            ->sort()
            ->values();

        $now = now();
        collect(['S', 'M', 'L', 'XL'])
            ->concat($used)
            ->unique(fn (string $size) => mb_strtolower($size))
            ->values()
            ->each(fn (string $size, int $i) => DB::table('sizes')->insert([
                'name' => $size,
                'sort_order' => ($i + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('sizes');
    }
};
