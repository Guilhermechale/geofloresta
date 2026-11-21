@php
    use App\Support\Breadcrumbs;
    $crumbs = Breadcrumbs::make([['label' => __('pages.services.title'), 'url' => lroute('services')]]);
    $loc = app()->getLocale();
@endphp
<x-layout :title="__('pages.services.title')" :description="__('pages.services.description')" :jsonld="array_merge([Breadcrumbs::jsonld($crumbs)], \App\Support\Schema::services($services))">
    <x-page-head :title="__('pages.services.title')" :lede="__('pages.services.lede')" :crumbs="$crumbs" />

    <section class="section section--tight" aria-label="{{ __('pages.services.title') }}">
        <div class="wrap">
            <ol class="pillars">
                @foreach ($services as $s)
                    @php $l = $s->displayLocale($loc); @endphp
                    <li class="pillar service" id="{{ $s->key }}">
                        <span class="pillar__idx">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <div>
                            <h2 class="service__title" @if ($l !== $loc) lang="{{ $l }}" @endif>{{ $s->getTranslation('title', $l) }}</h2>
                            <p>{{ $s->getTranslation('summary', $l) }}</p>
                        </div>
                        <div class="service__items" @if ($l !== $loc) lang="{{ $l }}" @endif>
                            <p class="meta">{{ __('pages.services.includes') }}</p>
                            <div class="prose prose--list">{!! $s->getTranslation('body', $l, false) !!}</div>
                        </div>
                        <a class="link-arrow" href="{{ lroute('contact') }}?assunto=proposal">{{ __('pages.services.ask') }}</a>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="section" aria-labelledby="data-req">
        <div class="wrap grid">
            <p class="span-7 lede" id="data-req">{{ __('pages.services.data_cta') }}</p>
            <div class="span-4 start-9 cta__action">
                <a class="btn" href="{{ lroute('contact') }}?assunto=data">{{ __('pages.services.data_cta_btn') }}</a>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="studies-title">
        <div class="wrap grid">
            <div class="span-4">
                <h2 id="studies-title">{{ __('pages.services.studies_title') }}</h2>
                <p class="muted">{{ __('pages.services.studies_lede') }}</p>
            </div>
            <div class="span-7 start-6">
                @if ($studies->isEmpty())
                    <p class="muted">{{ __('pages.services.no_studies') }}</p>
                @else
                    <ul class="entries">
                        @foreach ($studies as $st)
                            @php $sl = $st->displayLocale($loc); @endphp
                            <li class="entry">
                                <span class="meta">{{ $st->year ?: optional($st->published_at)->year }}@if ($st->venue) · {{ $st->venue }}@endif</span>
                                <a href="{{ $st->url() }}" @if ($sl !== $loc) lang="{{ $sl }}" @endif>{{ $st->getTranslation('title', $sl) }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>
</x-layout>
