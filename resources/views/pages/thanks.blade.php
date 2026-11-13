<x-layout :title="__('pages.thanks.title')" :noindex="true">
    <x-page-head :title="__('pages.thanks.title')" :lede="__('pages.thanks.text')">
        <p class="muted">{{ __('pages.thanks.confirm') }}</p>
        <div class="hero__actions">
            <a class="btn" href="{{ lroute('home') }}">{{ __('pages.thanks.back') }}</a>
            <a class="link-arrow" href="{{ lroute('data') }}">{{ __('pages.thanks.data') }}</a>
        </div>
    </x-page-head>
    {{-- Evento de conversão: só é enviado se a analítica estiver activa e autorizada (ver resources/js/analytics.js) --}}
    @if ($sent)
        <span hidden data-track-event="generate_lead" data-track-params='{"form":"contact"}'></span>
    @endif
</x-layout>
