<?php

namespace App\Providers;

use App\Http\Controllers\FallbackController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\TutorialController;
use App\Models\Study;
use App\Models\Tutorial;
use App\Support\Locales;
use App\Support\Preview;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // URLs antigos do WordPress na raiz (/slug): tutoriais e pesquisas mantêm o endereço em português.
        FallbackController::addResolver(function (Request $request) {
            $slug = trim($request->getPathInfo(), '/');
            if ($slug === '' || str_contains($slug, '/') || ! preg_match('/^[a-z0-9-]+$/', $slug)) {
                return null;
            }
            app()->setLocale('pt');
            $preview = Preview::active($request);
            $t = Tutorial::where('legacy_slug', $slug)->when(! $preview, fn ($q) => $q->published())->first();
            if ($t) {
                return response(TutorialController::render($t));
            }
            $s = Study::where('slug', $slug)->when(! $preview, fn ($q) => $q->published())->first();

            return $s ? response(ResearchController::render($s)) : null;
        });

        // Outros URLs antigos do WordPress que o Google ainda mostra (301 para o conteúdo equivalente):
        //  /en|fr/{slug-pt}   -> tutorial ou pesquisa traduzida
        //  /en|fr/sobre       -> página «Sobre» nessa língua
        //  /[en|fr/]tag/{x}   -> pesquisa no site por esse tema
        //  /[en|fr/]category/{x} -> lista de tutoriais
        //  /[en|fr/]author/{x}   -> página «Sobre»
        FallbackController::addResolver(function (Request $request) {
            $path = trim($request->getPathInfo(), '/');
            if (! preg_match('#^(?:(en|fr)/)?([a-z0-9/-]+)$#', $path, $m)) {
                return null;
            }
            $locale = $m[1] ?: 'pt';
            $parts = explode('/', $m[2]);
            $to = null;
            if ($parts[0] === 'tag' && isset($parts[1])) {
                $to = Locales::route('search', ['q' => str_replace('-', ' ', $parts[1])], $locale);
            } elseif ($parts[0] === 'category') {
                $to = Locales::route('learn', [], $locale);
            } elseif ($parts[0] === 'author' || ($m[1] && $m[2] === 'sobre')) {
                $to = Locales::route('about', [], $locale);
            } elseif ($m[1] && count($parts) === 1) {
                $c = Tutorial::where('legacy_slug', $parts[0])->published()->first()
                    ?? Study::where('slug', $parts[0])->published()->first();
                $to = $c?->url($locale);
            }

            return $to ? redirect()->to($to, 301) : null;
        });
    }
}
