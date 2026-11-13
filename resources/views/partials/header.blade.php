@php
    $items = [
        'home' => 'site.nav.home',
        'services' => 'site.nav.services',
        'data' => 'site.nav.data',
        'maps' => 'site.nav.maps',
        'learn' => 'site.nav.learn',
        'studies' => 'site.nav.studies',
        'about' => 'site.nav.about',
        'contact' => 'site.nav.contact',
    ];
    $themeLabels = [
        'auto' => __('site.theme.auto'), 'light' => __('site.theme.light'),
        'dark' => __('site.theme.dark'), 'switchTo' => __('site.theme.switchTo'),
    ];
@endphp
<header class="site-header">
    <div class="wrap site-header__bar">
        <a class="brand" href="{{ lroute('home') }}" @if (nav_active('home')) aria-current="page" @endif>
            <img class="logo-light" src="{{ asset('brand/gf-logo.webp') }}" width="36" height="36" alt="">
            <img class="logo-dark" src="{{ asset('brand/gf-logo-dark.webp') }}" width="36" height="36" alt="">
            <span>
                <span class="brand__name">GEOFLORESTA</span>
                <span class="brand__sub">{{ __('site.brand_sub') }}</span>
            </span>
        </a>

        <nav class="nav" aria-label="{{ __('site.nav.label') }}">
            <ul>
                @foreach ($items as $route => $label)
                    <li><a href="{{ lroute($route) }}" @if (nav_active($route)) aria-current="page" @endif>{{ __($label) }}</a></li>
                @endforeach
            </ul>
        </nav>

        <div class="tools">
            <a class="icon-btn search-btn" href="{{ lroute('search') }}" aria-label="{{ __('site.nav.search') }}" title="{{ __('site.nav.search') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg>
            </a>
            <button type="button" class="icon-btn" data-theme-toggle data-labels='@json($themeLabels)' aria-label="{{ __('site.theme.auto') }}">
                <svg data-theme-icon="light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                <svg data-theme-icon="dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z"/></svg>
                <svg data-theme-icon="auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 4a8 8 0 0 1 0 16Z" fill="currentColor"/></svg>
            </button>
            <div class="lang" role="group" aria-label="{{ __('site.lang.label') }}">
                @include('partials.lang-links', ['alternates' => $alternates])
            </div>
            <button type="button" class="icon-btn menu-btn" data-menu-toggle aria-expanded="false" aria-controls="mobile-nav" aria-label="{{ __('site.nav.open_menu') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 7h18M3 12h18M3 17h18"/></svg>
            </button>
        </div>
    </div>

    <nav id="mobile-nav" class="mobile-nav wrap" aria-label="{{ __('site.nav.label') }}" hidden>
        <ul>
            @foreach ($items as $route => $label)
                <li><a href="{{ lroute($route) }}" @if (nav_active($route)) aria-current="page" @endif>{{ __($label) }}</a></li>
            @endforeach
            <li><a href="{{ lroute('search') }}">{{ __('site.nav.search') }}</a></li>
        </ul>
        <div class="lang" role="group" aria-label="{{ __('site.lang.label') }}">
            @include('partials.lang-links', ['alternates' => $alternates])
        </div>
    </nav>
</header>
