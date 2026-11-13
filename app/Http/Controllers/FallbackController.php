<?php

namespace App\Http\Controllers;

use App\Models\Redirect;
use App\Support\Locales;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pedidos que nenhuma rota tratou:
 *  1) tabela de redirecções (301/302) e conteúdo retirado (410);
 *  2) conteúdo migrado do WordPress na raiz (tutoriais, estudos) — ligado no M8;
 *  3) 404 no idioma do caminho.
 */
class FallbackController extends Controller
{
    /** @var array<int, callable(Request): ?Response> resolvedores extra (registados no M8) */
    protected static array $resolvers = [];

    public static function addResolver(callable $resolver): void
    {
        static::$resolvers[] = $resolver;
    }

    public function __invoke(Request $request): Response
    {
        $path = Redirect::normalize($request->getPathInfo());
        $this->setLocaleFromPath($path);

        if ($r = Redirect::where('from_path', $path)->first()) {
            $r->increment('hits', 1, ['last_hit_at' => now()]);
            if ((int) $r->status_code === 410) {
                abort(410);
            }

            return redirect()->to($r->to_url, (int) $r->status_code);
        }

        foreach (static::$resolvers as $resolver) {
            if ($response = $resolver($request)) {
                return $response;
            }
        }

        abort(404);
    }

    protected function setLocaleFromPath(string $path): void
    {
        $first = explode('/', trim($path, '/'))[0] ?? '';
        $locale = in_array($first, Locales::all(), true) ? $first : Locales::default();
        app()->setLocale($locale);
    }
}
