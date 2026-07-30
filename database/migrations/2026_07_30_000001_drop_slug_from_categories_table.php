<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'slug')) {
            return;
        }

        // SQLite refuses to drop a column that is still part of an index.
        Schema::table('categories', function (Blueprint $t) {
            $t->dropUnique('categories_slug_unique');
        });

        Schema::table('categories', function (Blueprint $t) {
            $t->dropColumn('slug');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('categories', 'slug')) {
            Schema::table('categories', function (Blueprint $t) {
                $t->string('slug')->nullable()->after('name');
            });
        }
    }
};
