@php
    $labels = [
        'services' => 'site.nav.services', 'data' => 'site.nav.data', 'maps' => 'site.nav.maps',
        'learn' => 'site.nav.learn', 'about' => 'site.nav.about', 'contact' => 'site.nav.contact',
        'search' => 'site.nav.search', 'privacy' => 'site.footer.privacy', 'cookies' => 'site.footer.cookies',
        'terms' => 'site.footer.terms', 'licences' => 'site.footer.licences', 'accessibility' => 'site.footer.accessibility',
        'sitemap' => 'site.footer.sitemap', 'studies' => 'site.nav.services', 'courses' => 'site.nav.learn',
    ];
    $title = __($labels[$page] ?? 'site.placeholder.title');
@endphp
<x-layout :title="$title" :noindex="true">
    <header class="page-head">
        <div class="wrap">
            <p class="meta">{{ __('site.status.preparing') }}</p>
            <h1>{{ $title }}</h1>
            <p class="lede muted">{{ __('site.placeholder.text') }}</p>
        </div>
    </header>
</x-layout>
