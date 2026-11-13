<?php

namespace App\Http\Controllers;

use App\Models\ConsentLog;
use App\Support\Consent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Regista a escolha de cookies (prova anónima). O cookie gf_consent é escrito pelo próprio JS;
 * aqui só se grava: identificador aleatório, versão, categorias, idioma e data. Sem IP.
 */
class ConsentController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'id' => ['required', 'uuid'],
            'preferences' => ['required', 'boolean'],
            'analytics' => ['required', 'boolean'],
            'locale' => ['required', 'in:'.implode(',', config('geofloresta.locales'))],
        ]);

        ConsentLog::create([
            'consent_id' => Str::lower($data['id']),
            'version' => Consent::version(),
            'categories' => [
                'necessary' => true,
                'preferences' => (bool) $data['preferences'],
                'analytics' => Consent::analyticsAvailable() && (bool) $data['analytics'],
            ],
            'locale' => $data['locale'],
        ]);

        return response()->noContent();
    }
}
