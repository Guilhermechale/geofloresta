<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Pré-visualização de rascunhos: o painel gera o URL público com ?preview=<expira>.<assinatura>.
 * Os controladores públicos mostram o rascunho só se a assinatura for válida (1 hora).
 */
class Preview
{
    public static function sign(string $url, int $minutes = 60): string
    {
        $expires = now()->addMinutes($minutes)->timestamp;
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $sig = hash_hmac('sha256', $path.'|'.$expires, (string) config('app.key'));

        return $url.(str_contains($url, '?') ? '&' : '?').'preview='.$expires.'.'.$sig;
    }

    public static function active(Request $request): bool
    {
        $token = (string) $request->query('preview', '');
        if (! str_contains($token, '.')) {
            return false;
        }
        [$expires, $sig] = explode('.', $token, 2);
        if (! ctype_digit($expires) || (int) $expires < now()->timestamp) {
            return false;
        }
        $expected = hash_hmac('sha256', '/'.ltrim($request->path(), '/').'|'.$expires, (string) config('app.key'));

        return hash_equals($expected, $sig);
    }
}
