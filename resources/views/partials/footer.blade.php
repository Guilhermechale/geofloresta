<footer class="site-footer">
    <div class="wrap grid">
        <div class="span-4">
            <p><strong>GEOFLORESTA</strong></p>
            <p class="muted">{{ __('site.footer.about') }}</p>
            <p><a href="mailto:{{ config('geofloresta.contact_email') }}">{{ config('geofloresta.contact_email') }}</a></p>
            <p class="social-title">{{ __('social.title') }}</p>
            <x-social-links variant="icons" />
        </div>
        <nav class="span-3 start-6" aria-labelledby="ft-site">
            <h2 id="ft-site">{{ __('site.footer.site') }}</h2>
            <ul>
                <li><a href="{{ lroute('services') }}">{{ __('site.nav.services') }}</a></li>
                <li><a href="{{ lroute('data') }}">{{ __('site.nav.data') }}</a></li>
                <li><a href="{{ lroute('maps') }}">{{ __('site.nav.maps') }}</a></li>
                <li><a href="{{ lroute('learn') }}">{{ __('site.nav.learn') }}</a></li>
                <li><a href="{{ lroute('studies') }}">{{ __('site.nav.studies') }}</a></li>
                <li><a href="{{ lroute('about') }}">{{ __('site.nav.about') }}</a></li>
                <li><a href="{{ lroute('contact') }}">{{ __('site.nav.contact') }}</a></li>
                <li><a href="{{ lroute('sitemap') }}">{{ __('site.footer.sitemap') }}</a></li>
            </ul>
        </nav>
        <nav class="span-3 start-9" aria-labelledby="ft-legal">
            <h2 id="ft-legal">{{ __('site.footer.legal') }}</h2>
            <ul>
                <li><a href="{{ lroute('privacy') }}">{{ __('site.footer.privacy') }}</a></li>
                <li><a href="{{ lroute('cookies') }}">{{ __('site.footer.cookies') }}</a></li>
                <li><a href="{{ lroute('terms') }}">{{ __('site.footer.terms') }}</a></li>
                <li><a href="{{ lroute('licences') }}">{{ __('site.footer.licences') }}</a></li>
                <li><a href="{{ lroute('accessibility') }}">{{ __('site.footer.accessibility') }}</a></li>
                <li><button type="button" class="link-btn" data-cookie-prefs>{{ __('site.footer.cookie_prefs') }}</button></li>
            </ul>
        </nav>
    </div>
    <div class="wrap">
        <div class="credits">
            <p><strong>{{ __('site.footer.credits_title') }}.</strong> {{ __('site.footer.credits') }}</p>
            <p>{{ __('site.footer.rights', ['year' => now()->year]) }}</p>
        </div>
    </div>
</footer>
