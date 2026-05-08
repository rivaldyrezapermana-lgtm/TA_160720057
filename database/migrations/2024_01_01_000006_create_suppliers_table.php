<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('contact_person')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('suppliers'); }
};
