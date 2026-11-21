<?php

namespace App\Support;

/** Blocos JSON-LD (schema.org) das páginas principais. */
class Schema
{
    private static function org(): array
    {
        return ['@type' => 'Organization', 'name' => 'GEOFLORESTA', 'url' => url('/')];
    }

    /** Página inicial: o site (com a pesquisa interna) e a lista das secções em cartões. */
    public static function home(): array
    {
        $locale = app()->getLocale();
        $items = [];
        foreach (['lulc', 'fires', 'ndvi', 'relief'] as $k) {
            $items[] = [__('map.tools.'.$k.'.title'), MapTools::url($k)];
        }
        foreach (['data', 'learn', 'studies', 'services'] as $r) {
            $items[] = [__("site.home2.cards.$r.title"), Locales::route($r)];
        }

        return [
            [
                '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'GEOFLORESTA', 'url' => Locales::route('home'),
                'inLanguage' => $locale, 'publisher' => self::org(),
                'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => Locales::route('search').'?q={search_term_string}'], 'query-input' => 'required name=search_term_string'],
            ],
            [
                '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => __('site.home2.find'),
                'itemListElement' => array_map(fn ($it, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $it[0], 'url' => $it[1]], $items, array_keys($items)),
            ],
        ];
    }

    /** Serviços de consultoria. */
    public static function services($services): array
    {
        $loc = app()->getLocale();

        return [[
            '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => __('pages.services.title'),
            'itemListElement' => $services->values()->map(fn ($s, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1,
                'item' => [
                    '@type' => 'Service', 'name' => $s->getTranslation('title', $s->displayLocale($loc)),
                    'description' => (string) $s->getTranslation('summary', $s->displayLocale($loc), false),
                    'provider' => self::org(), 'areaServed' => 'Worldwide', 'url' => Locales::route('services').'#'.$s->key,
                ],
            ])->all(),
        ]];
    }

    /** Página de escolha das ferramentas de mapas. */
    public static function mapsHub(): array
    {
        return [[
            '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => __('map.hub_title'),
            'itemListElement' => array_map(fn ($k, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => __('map.tools.'.$k.'.title'), 'url' => MapTools::url($k)], MapTools::keys(), array_keys(MapTools::keys())),
        ]];
    }

    /** Cada ferramenta de mapas: aplicação web gratuita. */
    public static function mapTool(string $tool): array
    {
        return [[
            '@context' => 'https://schema.org', '@type' => 'WebApplication',
            'name' => __('map.tools.'.$tool.'.title').' · GEOFLORESTA', 'description' => __('map.tools.'.$tool.'.text'),
            'url' => MapTools::url($tool), 'applicationCategory' => 'EducationalApplication', 'applicationSubCategory' => 'GIS',
            'operatingSystem' => 'Any (web browser)', 'browserRequirements' => 'Requires JavaScript and WebGL',
            'isAccessibleForFree' => true, 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'inLanguage' => app()->getLocale(), 'image' => asset(MapTools::TOOLS[$tool]['image']), 'provider' => self::org(),
        ]];
    }
}
