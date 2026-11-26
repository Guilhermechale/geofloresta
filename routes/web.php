<?php

use App\Http\Controllers\ConsentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\FallbackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\TutorialController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\WellKnownController;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

/*
 | Rotas públicas por idioma.
 | pt: raiz (/servicos)   fr: /fr/services   en: /en/services
 | Os slugs vêm de lang/{idioma}/routes.php. Nomes: "pt.services", "en.services"...
 */
foreach (Locales::all() as $locale) {
    $slug = fn (string $key) => trans("routes.{$key}", [], $locale);

    Route::prefix(Locales::prefix($locale))
        ->name("{$locale}.")
        ->middleware("locale:{$locale}")
        ->group(function () use ($slug) {
            Route::get('/', HomeController::class)->name('home');
            Route::get($slug('services'), ServicesController::class)->name('services');
            Route::get($slug('about'), PageController::class)->name('about')->defaults('key', 'about');

            foreach (['privacy', 'cookies', 'terms', 'licences', 'accessibility'] as $legal) {
                Route::get($slug($legal), PageController::class)->name($legal)->defaults('key', $legal);
            }

            Route::get($slug('contact'), [ContactController::class, 'show'])->name('contact');
            Route::post($slug('contact'), [ContactController::class, 'store'])->name('contact.store')->middleware('throttle:10,1');
            Route::get($slug('thanks'), [ContactController::class, 'thanks'])->name('thanks');

            Route::get($slug('search'), SearchController::class)->name('search')->middleware('throttle:60,1');
            Route::get($slug('sitemap'), [SitemapController::class, 'html'])->name('sitemap');

            Route::get($slug('data'), [DatasetController::class, 'index'])->name('data');
            Route::get($slug('data').'/'.$slug('countries'), [CountryController::class, 'index'])->name('countries');
            Route::get($slug('data').'/'.$slug('countries').'/{iso}', [CountryController::class, 'show'])->name('countries.show')->where('iso', '[a-z]{3}');
            Route::get($slug('data').'/{slug}', [DatasetController::class, 'show'])->name('data.show')->where('slug', '[a-z0-9-]+');

            Route::get($slug('maps'), MapController::class)->name('maps');
            Route::get($slug('maps').'/{tool}', [MapController::class, 'tool'])->name('maps.tool')->where('tool', '[a-z-]+');

            // As duas categorias de conteúdo: Tutoriais e Pesquisas
            Route::get($slug('learn'), [TutorialController::class, 'index'])->name('learn');
            Route::get($slug('learn').'/{slug}', [TutorialController::class, 'show'])->name('learn.show')->where('slug', '[a-z0-9-]+');
            Route::get($slug('studies'), [ResearchController::class, 'index'])->name('studies');
            Route::get($slug('studies').'/{slug}', [ResearchController::class, 'show'])->name('studies.show')->where('slug', '[a-z0-9-]+');
        });
}

// Downloads de dados (um só URL para os três idiomas; o nome do ficheiro não se traduz)
Route::get('/descarregar/{file}/{filename}', DownloadController::class)->name('download')
    ->where(['file' => '[0-9]+', 'filename' => '[A-Za-z0-9._-]+'])->middleware('throttle:60,1');

Route::get('/sitemap.xml', [SitemapController::class, 'xml'])->name('sitemap.xml');
Route::get('/robots.txt', [WellKnownController::class, 'robots']);
Route::get('/llms.txt', [WellKnownController::class, 'llms']);
Route::get('/ads.txt', [WellKnownController::class, 'ads']);
Route::get('/.well-known/security.txt', [WellKnownController::class, 'security']);
Route::post('/consent', ConsentController::class)->name('consent')->middleware('throttle:30,1');

Route::fallback(FallbackController::class);
