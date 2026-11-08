<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_produksi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('produksi_id')->constrained('productions')->cascadeOnDelete();
            $t->dateTime('waktu');
            $t->string('keterangan', 500)->nullable();
            // Log tetap ada walaupun akun pencatatnya dihapus.
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['produksi_id', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_produksi');
    }
};
