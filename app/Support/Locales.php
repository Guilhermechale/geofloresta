<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Rotas por idioma: os nomes têm o prefixo do idioma ("pt.home", "en.services").
 * O português vive na raiz; francês em /fr e inglês em /en.
 */
class Locales
{
    /** URLs alternativos definidos pelo controlador (ex.: slugs traduzidos de um dataset). */
    protected static ?array $override = null;

    public static function all(): array
    {
        return config('geofloresta.locales');
    }

    public static function default(): string
    {
        return config('geofloresta.default_locale');
    }

    public static function prefix(string $locale): string
    {
        return $locale === self::default() ? '' : $locale;
    }

    /** Nome da rota sem o prefixo de idioma. */
    public static function baseName(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }
        $parts = explode('.', $routeName, 2);

        return in_array($parts[0], self::all(), true) && isset($parts[1]) ? $parts[1] : $routeName;
    }

    public static function route(string $name, array $params = [], ?string $locale = null, bool $absolute = true): string
    {
        $locale ??= app()->getLocale();

        return route("{$locale}.{$name}", $params, $absolute);
    }

    public static function setAlternates(?array $urls): void
    {
        self::$override = $urls;
    }

    /**
     * URL desta mesma página em cada idioma.
     * Sem alternativa definida, usa a mesma rota com os mesmos parâmetros.
     *
     * @return array<string,string>
     */
    public static function alternates(): array
    {
        if (self::$override !== null) {
            return self::$override;
        }

        $current = Route::current();
        $base = self::baseName($current?->getName());
        // Só os parâmetros do URI (os "defaults" internos da rota não entram no URL)
        $params = $current ? array_intersect_key($current->parameters(), array_flip($current->parameterNames())) : [];
        $out = [];

        foreach (self::all() as $locale) {
            $name = "{$locale}.{$base}";
            $out[$locale] = ($base && Route::has($name))
                ? route($name, $params)
                : route("{$locale}.home");
        }

        return $out;
    }

    /** Idioma preferido pelo navegador (Accept-Language), entre os suportados. */
    public static function preferred(Request $request): ?string
    {
        $best = $request->getPreferredLanguage(self::all());

        return $best ? substr($best, 0, 2) : null;
    }

    public static function isActive(string $name): bool
    {
        $base = self::baseName(Route::currentRouteName());

        return $base === $name || str_starts_with((string) $base, $name.'.');
    }
}
