<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define o idioma a partir do URL (nunca do cookie: cada idioma tem o seu URL).
 * Na primeira visita (sem cookie gf_lang), se o navegador preferir outro idioma,
 * mostra um aviso discreto com a ligação — sem redireccionar.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next, string $locale): Response
    {
        Locales::setAlternates(null); // estado por pedido
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        $hint = null;
        if (! $request->cookies->has('gf_lang')) {
            $preferred = Locales::preferred($request);
            if ($preferred && $preferred !== $locale) {
                $hint = $preferred;
            }
        }
        View::share('langHint', $hint);

        return $next($request);
    }
}
