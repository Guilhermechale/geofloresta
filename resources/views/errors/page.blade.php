{{-- Página de erro com a identidade do site (404, 410, 403, 419, 429). --}}
<x-layout :title="__('errors.'.$code.'.title')" :noindex="true">
    <section class="hero hero--error">
        <x-carto-art />
        <div class="wrap hero__inner">
            <p class="meta">{{ __('errors.code', ['code' => $code]) }}</p>
            <h1>{{ __('errors.'.$code.'.title') }}</h1>
            <p class="lede">{{ __('errors.'.$code.'.text') }}</p>

            @if (in_array($code, [404, 410]))
                <form method="get" action="{{ lroute('search') }}" role="search" class="search-form">
                    <label for="q404" class="visually-hidden">{{ __('pages.search.label') }}</label>
                    <input id="q404" name="q" type="search" placeholder="{{ __('pages.search.placeholder') }}" minlength="2" maxlength="100">
                    <button type="submit" class="btn btn--primary">{{ __('pages.search.submit') }}</button>
                </form>
            @endif

            <h2 class="meta">{{ __('errors.links') }}</h2>
            <ul class="plain-list plain-list--inline">
                <li><a href="{{ lroute('home') }}">{{ __('site.nav.home') }}</a></li>
                <li><a href="{{ lroute('data') }}">{{ __('site.nav.data') }}</a></li>
                <li><a href="{{ lroute('maps') }}">{{ __('site.nav.maps') }}</a></li>
                <li><a href="{{ lroute('learn') }}">{{ __('site.nav.learn') }}</a></li>
                <li><a href="{{ lroute('services') }}">{{ __('site.nav.services') }}</a></li>
                <li><a href="{{ lroute('contact') }}">{{ __('site.nav.contact') }}</a></li>
            </ul>
        </div>
    </section>
</x-layout>
