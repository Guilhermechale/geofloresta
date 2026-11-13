<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Conteúdo do site. Campos traduzíveis (spatie/laravel-translatable) guardados como texto JSON
 | {"pt": "...", "fr": "...", "en": "..."} em colunas TEXT/LONGTEXT.
 | "search_text" junta todos os idiomas e tem índice FULLTEXT (MySQL não indexa colunas JSON).
 */
return new class extends Migration
{
    protected function publishing(Blueprint $t): void
    {
        $t->string('status', 16)->default('draft')->index(); // draft | published
        $t->timestamp('published_at')->nullable();
    }

    protected function seo(Blueprint $t): void
    {
        $t->text('seo_title')->nullable();
        $t->text('seo_description')->nullable();
        $t->string('og_image')->nullable();
    }

    public function up(): void
    {
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique(); // about, privacy, cookies, terms, licences, accessibility, home...
            $t->text('title');
            $t->text('summary')->nullable();
            $t->longText('body')->nullable();
            $t->date('content_updated_at')->nullable(); // "última actualização" visível
            $this->seo($t);
            $this->publishing($t);
            $t->timestamps();
        });

        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique();
            $t->text('title');
            $t->text('summary')->nullable();
            $t->longText('body')->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('needs_review')->default(false); // "Para o utilizador confirmar"
            $this->seo($t);
            $this->publishing($t);
            $t->timestamps();
        });

        Schema::create('studies', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique(); // slug do WordPress, mantém o URL
            $t->text('title');
            $t->text('summary')->nullable();
            $t->longText('body')->nullable();
            $t->string('authors')->nullable();
            $t->string('venue')->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->string('doi')->nullable();
            $t->string('external_url')->nullable();
            $t->char('original_locale', 2)->default('pt');
            $t->string('cover_image')->nullable();
            $t->string('cover_alt')->nullable();
            $t->longText('search_text')->nullable();
            $this->seo($t);
            $this->publishing($t);
            $t->timestamps();
        });

        Schema::create('datasets', function (Blueprint $t) {
            $t->id();
            $t->text('slug');                       // traduzível
            $t->text('title');
            $t->text('summary')->nullable();
            $t->longText('description')->nullable();
            $t->string('theme', 32)->index();      // land-cover, forest-loss, fire, hydrology, biomass...
            $t->string('region', 64)->index();     // mozambique, sofala, ...
            $t->unsignedSmallInteger('year_start')->nullable();
            $t->unsignedSmallInteger('year_end')->nullable();
            $t->string('resolution', 64)->nullable();
            $t->string('crs', 64)->nullable();
            $t->string('source_name');
            $t->string('source_url');
            $t->string('licence_spdx', 64);         // CC-BY-4.0, ODbL-1.0, LicenseRef-...
            $t->string('licence_url')->nullable();
            $t->text('attribution');                // texto exacto exigido pela fonte (não se traduz)
            $t->text('citation')->nullable();       // não se traduz
            $t->string('version', 64)->nullable();
            $t->date('accessed_at')->nullable();
            $t->longText('processing')->nullable(); // traduzível
            $t->longText('limitations')->nullable(); // traduzível
            $t->string('hosting', 16)->default('hosted'); // hosted | external
            $t->string('update_frequency', 32)->default('static'); // static | periodic | near-real-time
            $t->longText('preview_geojson')->nullable();
            $t->boolean('featured')->default(false);
            $t->longText('search_text')->nullable();
            $this->seo($t);
            $this->publishing($t);
            $t->timestamps();
        });

        Schema::create('dataset_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $t->string('format', 16);               // cog, geojson, gpkg, shp, csv
            $t->text('label')->nullable();          // traduzível (ex.: "Sofala")
            $t->string('disk', 32)->default('datasets');
            $t->string('path');
            $t->string('filename');                 // nome do ficheiro descarregado (não se traduz)
            $t->unsignedBigInteger('size_bytes')->default(0);
            $t->char('sha256', 64)->nullable();
            $t->unsignedInteger('download_count')->default(0);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('tutorials', function (Blueprint $t) {
            $t->id();
            $t->text('slug');                       // traduzível
            $t->string('legacy_slug')->nullable()->unique(); // URL do WordPress (raiz)
            $t->string('platform', 16)->index();   // r, colab, gee, qgis, other
            $t->string('level', 16)->default('beginner'); // beginner, intermediate, advanced
            $t->text('title');
            $t->text('summary')->nullable();
            $t->text('prerequisites')->nullable();
            $t->unsignedSmallInteger('duration_minutes')->nullable();
            $t->longText('body')->nullable();
            $t->string('colab_url')->nullable();
            $t->string('gee_url')->nullable();
            $t->char('original_locale', 2)->default('pt');
            $t->string('cover_image')->nullable();
            $t->string('cover_alt')->nullable();
            $t->date('content_updated_at')->nullable();
            $t->boolean('featured')->default(false);
            $t->longText('search_text')->nullable();
            $this->seo($t);
            $this->publishing($t);
            $t->timestamps();
        });

        Schema::create('tutorial_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tutorial_id')->constrained()->cascadeOnDelete();
            $t->text('label');
            $t->string('path');
            $t->string('filename');
            $t->unsignedBigInteger('size_bytes')->default(0);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->text('slug');
            $t->text('title');
            $t->text('summary')->nullable();
            $t->string('platform', 16)->nullable();
            $t->string('level', 16)->default('beginner');
            $t->string('availability', 16)->default('preparing'); // available | preparing
            $t->unsignedSmallInteger('sort')->default(0);
            $this->seo($t);
            $this->publishing($t);
            $t->timestamps();
        });

        Schema::create('course_modules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->text('title');
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('course_lessons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_module_id')->constrained()->cascadeOnDelete();
            $t->text('title');
            $t->foreignId('tutorial_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('organisation')->nullable();
            $t->string('subject')->nullable();
            $t->text('message');
            $t->char('locale', 2)->default('pt');
            $t->char('ip_hash', 64)->nullable();   // hash com sal, nunca o IP em claro
            $t->timestamp('emailed_at')->nullable();
            $t->timestamp('confirmation_sent_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        Schema::create('redirects', function (Blueprint $t) {
            $t->id();
            $t->string('from_path')->unique();      // sem domínio, ex.: /noticia-antiga
            $t->string('to_url')->nullable();       // vazio quando 410
            $t->unsignedSmallInteger('status_code')->default(301); // 301 | 302 | 410
            $t->string('source', 16)->default('manual'); // manual | slug | migration
            $t->unsignedInteger('hits')->default(0);
            $t->timestamp('last_hit_at')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 64)->unique();
            $t->longText('value')->nullable();      // JSON; textos com {"pt","fr","en"}
            $t->timestamps();
        });

        Schema::create('consent_logs', function (Blueprint $t) {
            $t->id();
            $t->uuid('consent_id')->index();        // identificador aleatório guardado no cookie
            $t->string('version', 32);
            $t->json('categories');
            $t->char('locale', 2);
            $t->timestamp('created_at')->useCurrent();
        });

        // Pesquisa interna (FULLTEXT só existe em MySQL/MariaDB; os testes correm em SQLite)
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('datasets', fn (Blueprint $t) => $t->fullText('search_text'));
            Schema::table('tutorials', fn (Blueprint $t) => $t->fullText('search_text'));
            Schema::table('studies', fn (Blueprint $t) => $t->fullText('search_text'));
        }
    }

    public function down(): void
    {
        foreach (['consent_logs', 'settings', 'redirects', 'contact_messages', 'course_lessons', 'course_modules',
            'courses', 'tutorial_attachments', 'tutorials', 'dataset_files', 'datasets', 'studies', 'services', 'pages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
