<?php
// Cria (ou actualiza) o tutorial «Classificação supervisionada com Random Forest no Google Earth Engine».
// Uso no servidor: php criar-random-forest.php <pasta da app> <random-forest.php> <pasta com as imagens (assets-originais/tutoriais/random-forest)>
[$_, $app, $contentFile, $imgDir] = $argv;
require $app.'/vendor/autoload.php';
$laravel = require $app.'/bootstrap/app.php';
$laravel->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tutorial;
use Illuminate\Support\Facades\Storage;

$c = require $contentFile;
$disk = Storage::disk('public');
foreach (['pt', 'fr', 'en'] as $l) {
    foreach ($c['images'] as $f) {
        $disk->put("tutoriais/random-forest/{$l}/{$f}", file_get_contents("{$imgDir}/{$l}/{$f}"));
    }
    $disk->put("tutoriais/random-forest/random-forest-gee-{$l}.js", $c['scripts'][$l]);
}
$disk->put('capas/tutorial-random-forest.webp', file_get_contents("{$imgDir}/random-forest.webp"));

$t = Tutorial::query()->where('slug', 'like', '%"pt":"'.$c['slug']['pt'].'"%')->first() ?? new Tutorial();
$t->fill([
    'slug' => $c['slug'], 'title' => $c['title'], 'summary' => $c['summary'], 'body' => $c['body'],
    'objectives' => $c['objectives'], 'prerequisites' => $c['prerequisites'], 'exercise' => $c['exercise'],
    'takeaways' => $c['takeaways'], 'sources' => $c['sources'], 'seo_title' => $c['seo_title'], 'seo_description' => $c['seo_description'],
    'platform' => 'gee', 'level' => 'intermediate', 'duration_minutes' => 45, 'original_locale' => 'pt',
    'cover_image' => 'capas/tutorial-random-forest.webp', 'cover_alt' => $c['cover_alt'], 'cover_credit' => $c['cover_credit'],
    'content_updated_at' => now()->toDateString(), 'status' => 'published',
]);
if (! $t->published_at) {
    $t->published_at = now();
}
$t->save();
echo "tutorial {$t->id} ".($t->wasRecentlyCreated ? 'criado' : 'actualizado').PHP_EOL;
foreach (['pt', 'fr', 'en'] as $l) {
    echo "$l: ".$t->url($l).PHP_EOL;
}
