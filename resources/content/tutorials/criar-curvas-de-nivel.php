// Uso no servidor: php criar-curvas-de-nivel.php <pasta da app> <curvas-de-nivel.php> <pasta com as imagens (assets-originais/tutoriais/curvas-de-nivel)>
<?php
// Cria (ou actualiza) o tutorial «Curvas de nível em 2 minutos». Uso: php create.php <app> <content.php> <pasta-imagens>
[$_, $app, $contentFile, $imgDir] = $argv;
require $app.'/vendor/autoload.php';
$laravel = require $app.'/bootstrap/app.php';
$laravel->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tutorial;
use Illuminate\Support\Facades\Storage;

$c = require $contentFile;
$disk = Storage::disk('public');
foreach (['fig1-mapa-relevo.webp', 'fig2-painel-relevo.webp', 'fig3-curvas-qgis.webp'] as $f) {
    $disk->put("tutoriais/curvas-de-nivel/{$f}", file_get_contents("{$imgDir}/{$f}"));
}
$disk->put('capas/tutorial-curvas-de-nivel.webp', file_get_contents("{$imgDir}/curvas-de-nivel.webp"));

$t = Tutorial::query()->where('slug', 'like', '%"pt":"'.$c['slug']['pt'].'"%')->first() ?? new Tutorial();
$t->fill([
    'slug' => $c['slug'], 'title' => $c['title'], 'summary' => $c['summary'], 'body' => $c['body'],
    'objectives' => $c['objectives'], 'prerequisites' => $c['prerequisites'], 'exercise' => $c['exercise'],
    'takeaways' => $c['takeaways'], 'sources' => $c['sources'], 'seo_title' => $c['seo_title'], 'seo_description' => $c['seo_description'],
    'platform' => 'web', 'level' => 'beginner', 'duration_minutes' => 10, 'original_locale' => 'pt',
    'cover_image' => 'capas/tutorial-curvas-de-nivel.webp', 'cover_alt' => $c['cover_alt'], 'cover_credit' => $c['cover_credit'],
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
