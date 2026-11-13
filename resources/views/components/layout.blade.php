@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'noindex' => false,
    'type' => 'website',
    'jsonld' => [],
])
@php
    use App\Support\Locales;
    $locale = app()->getLocale();
    $alternates = Locales::alternates();
    $canonical = $alternates[$locale] ?? url()->current();
    $fullTitle = $title ? $title.' · GEOFLORESTA' : __('site.home.title');
    $desc = $description ?? __('site.home.description');
    $ogImage = $image ?? asset('brand/icon-512.png');
    $ogLocales = ['pt' => 'pt_MZ', 'fr' => 'fr_FR', 'en' => 'en_GB'];
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
    $org = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'GEOFLORESTA',
        'url' => url('/'),
        'logo' => asset('brand/icon-512.png'),
        'email' => config('geofloresta.contact_email'),
        'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Beira', 'addressRegion' => 'Sofala', 'addressCountry' => 'MZ'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script nonce="{{ $nonce }}">(function(){var d=document.documentElement,c='auto';try{c=localStorage.getItem('gf-theme')||sessionStorage.getItem('gf-theme')||'auto'}catch(e){}var t=c==='auto'?(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):c;d.setAttribute('data-theme',t);d.setAttribute('data-theme-choice',c);})();</script>
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $desc }}">
    @if ($noindex || app()->environment('staging'))
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="canonical" href="{{ $canonical }}">
    @foreach ($alternates as $lc => $url)
        <link rel="alternate" hreflang="{{ $lc }}" href="{{ $url }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $alternates[Locales::default()] ?? url('/') }}">

    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#F4F1E8" media="(prefers-color-scheme: light)" data-theme-color="light">
    <meta name="theme-color" content="#0B1912" media="(prefers-color-scheme: dark)" data-theme-color="dark">

    <meta property="og:type" content="{{ $type }}">
    <meta property="og:site_name" content="GEOFLORESTA">
    <meta property="og:title" content="{{ $title ?? __('site.home.title') }}">
    <meta property="og:description" content="{{ $desc }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:locale" content="{{ $ogLocales[$locale] }}">
    @foreach ($ogLocales as $lc => $og)
        @if ($lc !== $locale)<meta property="og:locale:alternate" content="{{ $og }}">@endif
    @endforeach
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}

    <script type="application/ld+json" nonce="{{ $nonce }}">{!! json_encode($org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @foreach ($jsonld as $block)
        <script type="application/ld+json" nonce="{{ $nonce }}">{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endforeach
</head>
<body>
    <a class="skip-link" href="#conteudo">{{ __('site.skip') }}</a>

    @if (! empty($langHint) && isset($alternates[$langHint]))
        <div class="lang-hint" data-lang-hint lang="{{ $langHint }}">
            <div class="wrap">
                <p>{{ __('site.lang.hint', [], $langHint) }}</p>
                <p>
                    <a href="{{ $alternates[$langHint] }}" hreflang="{{ $langHint }}" data-set-lang="{{ $langHint }}">{{ __('site.lang.hint_go', [], $langHint) }}</a>
                    <button type="button" class="link-btn muted" data-lang-hint-close>· {{ __('site.lang.hint_close', [], $langHint) }}</button>
                </p>
            </div>
        </div>
    @endif

    @include('partials.header', ['alternates' => $alternates])

    <main id="conteudo" tabindex="-1">
        {{ $slot }}
    </main>

    @include('partials.footer')
</body>
</html>
