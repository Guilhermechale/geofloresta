<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Consentimento de cookies (implementação própria).
 * Cookie "gf_consent" (não cifrado, lido também pelo JS): "v=<versão>&id=<uuid>&p=0|1&a=0|1&t=<epoch>".
 */
class Consent
{
    public static function version(): string
    {
        return (string) config('geofloresta.consent_version');
    }

    public static function analyticsAvailable(): bool
    {
        return filled(config('geofloresta.ga4_id'));
    }

    /** Categorias que existem de facto (a "Analítica" só com GA4_ID). */
    public static function categories(): array
    {
        return array_values(array_filter(config('cookies.categories'), fn ($c) => $c !== 'analytics' || self::analyticsAvailable()));
    }

    /** Itens do inventário que existem de facto. */
    public static function items(): array
    {
        return array_values(array_filter(config('cookies.items'), fn ($i) => empty($i['requires']) || filled(config($i['requires']))));
    }

    /** @return array{v:string,id:string,p:bool,a:bool,t:int}|null */
    public static function fromRequest(Request $request): ?array
    {
        $raw = $request->cookies->get('gf_consent');
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        parse_str($raw, $c);
        if (! isset($c['v'], $c['id'], $c['t'])) {
            return null;
        }

        return ['v' => (string) $c['v'], 'id' => (string) $c['id'], 'p' => ($c['p'] ?? '0') === '1', 'a' => ($c['a'] ?? '0') === '1', 't' => (int) $c['t']];
    }

    /** Mostrar o banner? Sem escolha, com versão antiga ou com escolha com mais de 6 meses. */
    public static function needsBanner(Request $request): bool
    {
        $c = self::fromRequest($request);
        if (! $c || $c['v'] !== self::version()) {
            return true;
        }

        return $c['t'] < now()->subMonths((int) config('cookies.consent_months'))->timestamp;
    }

    public static function durationLabel(array $d): string
    {
        foreach ($d as $unit => $n) {
            return trans_choice("cookies.duration.{$unit}", (int) $n, ['n' => $n]);
        }

        return '';
    }
}
