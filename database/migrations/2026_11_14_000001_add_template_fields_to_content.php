<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Modelo único de tutorial e de pesquisa: campos estruturados em vez de blocos soltos no texto. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutorials', function (Blueprint $t) {
            $t->json('objectives')->nullable()->after('prerequisites');   // "O que vai aprender" (uma linha por objectivo)
            $t->json('exercise')->nullable()->after('body');               // Exercício prático (texto rico)
            $t->json('takeaways')->nullable()->after('exercise');          // Pontos-chave (uma linha por ponto)
            $t->text('sources')->nullable()->after('takeaways');           // Fontes: "Título | URL" por linha
        });
        Schema::table('studies', function (Blueprint $t) {
            $t->string('article_type', 20)->default('original')->after('slug'); // original | review
            $t->json('abstract')->nullable()->after('summary');
            $t->json('keywords')->nullable()->after('abstract');
            $t->text('references')->nullable()->after('body');            // uma referência por linha
        });
    }

    public function down(): void
    {
        Schema::table('tutorials', fn (Blueprint $t) => $t->dropColumn(['objectives', 'exercise', 'takeaways', 'sources']));
        Schema::table('studies', fn (Blueprint $t) => $t->dropColumn(['article_type', 'abstract', 'keywords', 'references']));
    }
};
