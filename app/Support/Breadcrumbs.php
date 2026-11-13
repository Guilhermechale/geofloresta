<?php

namespace App\Support;

/** Trilho de navegação: [['label' => ..., 'url' => ...], ...] → JSON-LD BreadcrumbList. */
class Breadcrumbs
{
    public static function make(array $trail): array
    {
        return array_merge([['label' => __('site.nav.home'), 'url' => lroute('home')]], $trail);
    }

    public static function jsonld(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($c, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['label'], 'item' => $c['url'] ?? url()->current(),
            ], $crumbs, array_keys($crumbs)),
        ];
    }
}
