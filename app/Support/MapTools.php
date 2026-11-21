<?php

namespace App\Support;

/**
 * Ferramentas da secção Mapas: cada uma abre o mesmo mapa só com o painel e as camadas de que precisa.
 * Os endereços são traduzidos (/mapas/incendios, /fr/cartes/incendies, /en/maps/fires).
 */
class MapTools
{
    /** chave => [slugs por língua, imagem do cartão, camadas ligadas por defeito, painéis visíveis] */
    public const TOOLS = [
        'lulc' => ['slug' => ['pt' => 'uso-do-solo', 'fr' => 'occupation-du-sol', 'en' => 'land-cover'], 'image' => 'img/cards/uso-do-solo.webp', 'layers' => ['boundaries']],
        'fires' => ['slug' => ['pt' => 'incendios', 'fr' => 'incendies', 'en' => 'fires'], 'image' => 'img/cards/incendios.webp', 'layers' => ['fires', 'boundaries']],
        'ndvi' => ['slug' => ['pt' => 'vegetacao-ndvi', 'fr' => 'vegetation-ndvi', 'en' => 'vegetation-ndvi'], 'image' => 'img/cards/ndvi.webp', 'layers' => ['ndvi', 'boundaries']],
        'relief' => ['slug' => ['pt' => 'relevo', 'fr' => 'relief', 'en' => 'terrain'], 'image' => 'img/cards/relevo.webp', 'layers' => ['hillshade', 'relief', 'boundaries']],
        'satellite' => ['slug' => ['pt' => 'imagem-de-satelite', 'fr' => 'image-satellite', 'en' => 'satellite-image'], 'image' => 'img/cards/satelite.webp', 'layers' => ['truecolor', 'boundaries']],
    ];

    public static function keys(): array
    {
        return array_keys(self::TOOLS);
    }

    public static function fromSlug(string $slug, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        foreach (self::TOOLS as $key => $t) {
            if (($t['slug'][$locale] ?? null) === $slug) {
                return $key;
            }
        }

        return null;
    }

    public static function url(string $key, ?string $locale = null, array $query = []): string
    {
        $locale ??= app()->getLocale();

        return Locales::route('maps.tool', ['tool' => self::TOOLS[$key]['slug'][$locale]] + $query, $locale);
    }

    public static function on(string $key, string $layer): bool
    {
        return in_array($layer, self::TOOLS[$key]['layers'], true);
    }
}
