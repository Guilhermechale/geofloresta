@php
    use App\Support\Breadcrumbs;
    use App\Support\PageBody;
    $crumbs = Breadcrumbs::make([['label' => __('pages.about.title'), 'url' => lroute('about')]]);
    $title = $page->getTranslation('title', $locale);
    $person = [
        '@context' => 'https://schema.org', '@type' => 'Person', 'name' => 'Guilherme Francisco Fernando Chale', 'image' => asset('img/fundador-guilherme-chale.webp'),
        'jobTitle' => 'Engenheiro Florestal', 'worksFor' => ['@type' => 'Organization', 'name' => 'GEOFLORESTA'],
        'alumniOf' => ['@type' => 'CollegeOrUniversity', 'name' => 'Universidade Lúrio'],
        'sameAs' => ['https://www.linkedin.com/in/guilherme-chale-8b378623a/'],
    ];
@endphp
<x-layout :title="$page->seoTitle()" :description="$page->seoDescription() ?? __('pages.about.description')" :jsonld="[Breadcrumbs::jsonld($crumbs), $person]">
    <x-page-head :title="$title" :crumbs="$crumbs" />

    <div class="section section--tight">
        <div class="wrap grid">
            <article class="span-7 prose" @if ($fallback) lang="{{ $locale }}" @endif>
                @if ($fallback)
                    <p class="notice">{{ __('site.lang.only_in', ['language' => __('site.lang.names.'.$locale)]) }}</p>
                @endif
                {!! PageBody::render($page->getTranslation('body', $locale)) !!}
                <p><a class="link-arrow" href="https://www.linkedin.com/in/guilherme-chale-8b378623a/" rel="noopener me">{{ __('pages.about.linkedin') }}</a></p>
            </article>

            <aside class="span-4 start-9" aria-labelledby="facts">
                <figure class="founder">
                    <img src="{{ asset('img/fundador-guilherme-chale.webp') }}" width="600" height="750" alt="{{ __('pages.about.founder_alt') }}" decoding="async" fetchpriority="high">
                    <figcaption><strong>Guilherme Francisco Fernando Chale</strong>{{ __('pages.about.founder_role') }}</figcaption>
                </figure>
                <div class="specimen">
                    <h2 class="specimen__title" id="facts">{{ __('pages.about.facts') }}</h2>
                    <dl>
                        <dt>{{ __('pages.about.founded') }}</dt><dd>2026</dd>
                        <dt>{{ __('pages.about.base') }}</dt><dd>Beira, Sofala</dd>
                        <dt>{{ __('pages.about.status') }}</dt><dd>{{ __('pages.about.status_value') }}</dd>
                        <dt>{{ __('pages.about.areas') }}</dt><dd>{{ $servicesCount ?: 4 }}</dd>
                        <dt>{{ __('pages.about.territory') }}</dt><dd>{{ __('pages.about.territory_value') }}</dd>
                        <dt>{{ __('pages.about.founder') }}</dt><dd>Guilherme F. F. Chale</dd>
                        <dt>{{ __('pages.contact.email_label') }}</dt><dd><a href="mailto:{{ config('geofloresta.contact_email') }}">{{ config('geofloresta.contact_email') }}</a></dd>
                    </dl>
                    <p class="coord specimen__foot">19°50′S 34°51′E</p>
                </div>
            </aside>
        </div>
    </div>
</x-layout>
