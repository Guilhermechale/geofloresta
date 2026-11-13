<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PlaceholderController;
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

            // Páginas ainda em construção (substituídas nos marcos M5–M8)
            foreach (['services', 'studies', 'data', 'maps', 'learn', 'courses', 'about', 'contact', 'search',
                'privacy', 'cookies', 'terms', 'licences', 'accessibility', 'sitemap'] as $page) {
                Route::get($slug($page), PlaceholderController::class)->name($page)->defaults('page', $page);
            }
        });
}
