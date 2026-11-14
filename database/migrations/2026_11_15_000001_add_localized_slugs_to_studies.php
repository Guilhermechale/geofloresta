<?php

use App\Models\Study;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Endereços próprios em FR/EN para as pesquisas (o PT mantém o slug do WordPress).
// Os endereços antigos (/fr/recherches/<slug-pt>) redireccionam com 301 para os novos.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('studies', 'slugs')) {
            Schema::table('studies', fn (Blueprint $t) => $t->json('slugs')->nullable()->after('slug'));
        }

        $used = [];
        foreach (DB::table('studies')->orderBy('id')->get(['id', 'title', 'slugs']) as $row) {
            $titles = json_decode((string) $row->title, true) ?: [];
            $slugs = json_decode((string) $row->slugs, true) ?: [];
            foreach (Study::LOCALISED as $l) {
                if (empty($slugs[$l]) && ! empty($titles[$l])) {
                    $slugs[$l] = Study::uniqueSlug(Study::makeSlug($titles[$l]), $l, $used);
                }
                if (! empty($slugs[$l])) {
                    $used[$l][] = $slugs[$l];
                }
            }
            DB::table('studies')->where('id', $row->id)->update(['slugs' => $slugs ? json_encode($slugs) : null]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('studies', 'slugs')) {
            Schema::table('studies', fn (Blueprint $t) => $t->dropColumn('slugs'));
        }
    }
};
