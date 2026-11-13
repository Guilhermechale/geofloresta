<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Crédito/atribuição da imagem de destaque (ex.: Sentinel-2 cloudless, CC BY 4.0), mostrado por baixo da capa.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tutorials', 'studies'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'cover_credit')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('cover_credit', 400)->nullable()->after('cover_alt'));
            }
        }
    }

    public function down(): void
    {
        foreach (['tutorials', 'studies'] as $table) {
            if (Schema::hasColumn($table, 'cover_credit')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('cover_credit'));
            }
        }
    }
};
