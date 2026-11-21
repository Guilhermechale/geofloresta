@props(['compact' => false])
{{-- Convite a pedir um serviço: presente nas páginas onde as pessoas usam as ferramentas e os dados. --}}
<section {{ $attributes->merge(['class' => 'service-cta'.($compact ? ' service-cta--compact' : '')]) }} aria-labelledby="service-cta-title">
    <div class="wrap service-cta__inner">
        <div class="service-cta__text">
            <h2 id="service-cta-title" class="h3">{{ __('site.service_cta.title') }}</h2>
            @unless ($compact)<p>{{ __('site.service_cta.text') }}</p>@endunless
        </div>
        <div class="service-cta__actions">
            <a class="btn btn--primary" href="{{ lroute('contact') }}?assunto=proposal">{{ __('site.service_cta.ask') }}</a>
            <a class="link-arrow" href="{{ lroute('services') }}">{{ __('site.service_cta.see') }}</a>
        </div>
    </div>
</section>
