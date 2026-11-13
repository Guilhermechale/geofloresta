<?php

namespace App\Providers;

use App\Http\Controllers\FallbackController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\TutorialController;
use App\Models\Study;
use App\Models\Tutorial;
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
    }
}
