<?php

namespace App\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Países do mundo (Natural Earth 1:50m, domínio público), preparados por `php artisan geofloresta:world`.
 * Cada país: iso (ISO 3166-1 alfa-3), nomes PT/FR/EN, continente, bbox e caminho do contorno público.
 */
class World
{
    public const LIST = 'world/countries.json';
    public const FIRES_SUMMARY = 'world/fires-summary.json';

    public const CONTINENTS = ['Africa', 'Asia', 'Europe', 'North America', 'South America', 'Oceania', 'Antarctica', 'Seven seas (open ocean)'];

    /** @return array<string, array> iso => país */
    public static function all(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::LIST)) {
            return [];
        }

        return Cache::remember('world:'.$disk->lastModified(self::LIST), now()->addDay(), fn () => json_decode($disk->get(self::LIST), true) ?: []);
    }

    public static function find(?string $iso): ?array
    {
        return $iso ? (self::all()[strtoupper($iso)] ?? null) : null;
    }

    public static function name(array $c, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $c['names'][$locale] ?? $c['names']['en'] ?? $c['iso'];
    }

    /** Países ordenados pelo nome no idioma actual, agrupados por continente. */
    public static function byContinent(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $groups = [];
        foreach (self::all() as $c) {
            $groups[$c['continent']][] = $c;
        }
        foreach ($groups as &$list) {
            usort($list, fn ($a, $b) => strcoll(self::sortKey(self::name($a, $locale)), self::sortKey(self::name($b, $locale))));
        }
        uksort($groups, fn ($a, $b) => array_search($a, self::CONTINENTS) <=> array_search($b, self::CONTINENTS));

        return $groups;
    }

    public static function sorted(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $all = array_values(self::all());
        usort($all, fn ($a, $b) => strcmp(self::sortKey(self::name($a, $locale)), self::sortKey(self::name($b, $locale))));

        return $all;
    }

    protected static function sortKey(string $s): string
    {
        return strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s);
    }

    public static function fires(): array
    {
        $disk = Storage::disk('local');

        return $disk->exists(self::FIRES_SUMMARY) ? (json_decode($disk->get(self::FIRES_SUMMARY), true) ?: []) : [];
    }

    public static function firesPath(string $iso, string $ext = 'geojson'): string
    {
        return 'geo/fires/'.strtoupper($iso).'.'.$ext;
    }

    public static function outlinePath(string $iso): string
    {
        return 'geo/world/'.strtoupper($iso).'.geojson';
    }

    /** Região HydroSHEDS (HydroRIVERS/HydroBASINS) de um país. */
    public static function hydroRegions(array $c): array
    {
        return match ($c['iso']) {
            'RUS' => ['eu', 'si'], 'KAZ', 'MNG' => ['as', 'si'], 'GRL' => ['ar'], 'USA' => ['na', 'ar'], 'CAN' => ['na', 'ar'],
            default => match ($c['continent']) {
                'Africa' => ['af'], 'Europe' => ['eu'], 'Asia' => ['as'], 'North America' => ['na'],
                'South America' => ['sa'], 'Oceania' => ['au'], default => [],
            },
        };
    }
}
