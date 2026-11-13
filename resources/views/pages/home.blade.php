<x-layout>
    <section class="hero">
        <x-carto-art />
        <div class="wrap hero__inner">
            <p class="meta">{{ __('site.home.kicker') }}</p>
            <h1>{{ __('site.home.h1') }}</h1>
            <p class="lede">{{ __('site.home.lede') }}</p>
            <div class="hero__actions">
                <a class="btn btn--primary" href="{{ lroute('data') }}">{{ __('site.home.cta_data') }}</a>
                <a class="btn" href="{{ lroute('contact') }}">{{ __('site.home.cta_contact') }}</a>
            </div>
            <div class="hero__coords">
                <span class="coord">19°50′S · 34°51′E</span>
                <span class="coord">WGS 84 · EPSG:4326</span>
                <div class="scalebar" aria-hidden="true">
                    <div class="scalebar__bar"><span></span><span></span><span></span><span></span></div>
                    <div class="scalebar__labels"><span>0</span><span>50</span><span>100 km</span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="pillars-title">
        <div class="wrap">
            <div class="section-head">
                <h2 id="pillars-title">{{ __('site.home.pillars_title') }}</h2>
            </div>
            <ol class="pillars">
                @foreach (['data' => 'data', 'maps' => 'maps', 'services' => 'services'] as $key => $route)
                    <li class="pillar">
                        <span class="pillar__idx">0{{ $loop->iteration }}</span>
                        <h3>{{ __("site.home.pillars.$key.title") }}</h3>
                        <p>{{ __("site.home.pillars.$key.text") }}</p>
                        <a class="link-arrow" href="{{ lroute($route) }}">{{ __("site.home.pillars.$key.link") }}</a>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="section" aria-labelledby="monitor-title">
        <div class="wrap grid">
            <div class="span-5">
                <p class="meta"><span class="dot-fire" aria-hidden="true"></span>{{ __('site.status.near_real_time') }}</p>
                <h2 id="monitor-title">{{ __('site.home.monitor_title') }}</h2>
                <p class="muted">{{ __('site.home.monitor_source') }}</p>
                <a class="link-arrow" href="{{ lroute('maps') }}">{{ __('site.home.monitor_link') }}</a>
            </div>
            <div class="span-6 start-7 monitor">
                @if ($fire)
                    <p class="monitor__value">{{ format_number($fire['count_24h']) }}</p>
                    <p>{{ __('site.home.monitor_label') }}</p>
                    <p class="meta">{{ __('site.home.monitor_updated', ['date' => format_datetime($fire['updated_at'])]) }}</p>
                @else
                    <p class="monitor__value" aria-hidden="true">—</p>
                    <p class="muted">{{ __('site.home.monitor_empty') }}</p>
                @endif
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="data-title">
        <div class="wrap">
            <div class="section-head">
                <h2 id="data-title">{{ __('site.home.featured_data') }}</h2>
                <a class="link-arrow" href="{{ lroute('data') }}">{{ __('site.home.all_data') }}</a>
            </div>
            <div class="grid">
                @foreach ($datasets as $d)
                    @php $l = $d->displayLocale(app()->getLocale()); @endphp
                    <article class="specimen span-4">
                        <p class="meta">{{ __('data.themes.'.$d->theme) }}</p>
                        <h3 class="specimen__title"><a href="{{ $d->url() }}" @if ($l !== app()->getLocale()) lang="{{ $l }}" @endif>{{ $d->getTranslation('title', $l) }}</a></h3>
                        <dl>
                            <dt>{{ __('site.specimen.source') }}</dt><dd>{{ $d->source_name }}</dd>
                            <dt>{{ __('site.specimen.licence') }}</dt><dd>{{ $d->licence_spdx }}</dd>
                            @if ($d->period())<dt>{{ __('site.specimen.period') }}</dt><dd>{{ $d->period() }}</dd>@endif
                            @if ($d->resolution)<dt>{{ __('site.specimen.resolution') }}</dt><dd>{{ $d->resolution }}</dd>@endif
                            <dt>{{ __('site.specimen.format') }}</dt><dd>{{ $d->files->pluck('format')->unique()->map(fn ($f) => \App\Models\Dataset::FORMATS[$f] ?? $f)->implode(', ') ?: __('data.external') }}</dd>
                        </dl>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="learn-title">
        <div class="wrap grid">
            <div class="span-4">
                <h2 id="learn-title">{{ __('site.home.featured_learn') }}</h2>
                <a class="link-arrow" href="{{ lroute('learn') }}">{{ __('site.home.all_learn') }}</a>
            </div>
            <ul class="entries span-7 start-6">
                @foreach ($tutorials as $t)
                    @php $l = $t->displayLocale(app()->getLocale()); @endphp
                    <li class="entry">
                        <span class="meta">{{ __('content.tutorials.platforms.'.$t->platform) }}</span>
                        <a href="{{ $t->url() }}" @if ($l !== app()->getLocale()) lang="{{ $l }}" @endif>{{ $t->getTranslation('title', $l) }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    @if ($studies->isNotEmpty())
        <section class="section" aria-labelledby="research-title">
            <div class="wrap grid">
                <div class="span-4">
                    <h2 id="research-title">{{ __('content.research.recent') }}</h2>
                    <a class="link-arrow" href="{{ lroute('studies') }}">{{ __('content.back_research') }}</a>
                </div>
                <ul class="entries span-7 start-6">
                    @foreach ($studies as $s)
                        @php $l = $s->displayLocale(app()->getLocale()); @endphp
                        <li class="entry">
                            <span class="meta">{{ $s->published_at ? format_date($s->published_at, 'medium') : '' }}</span>
                            <a href="{{ $s->url() }}" @if ($l !== app()->getLocale()) lang="{{ $l }}" @endif>{{ $s->getTranslation('title', $l) }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="cta" aria-labelledby="cta-title">
        <div class="wrap grid">
            <div class="span-7">
                <h2 id="cta-title">{{ __('site.home.contact_title') }}</h2>
                <p>{{ __('site.home.contact_text') }}</p>
            </div>
            <div class="span-4 start-9 cta__action">
                <a class="btn" href="{{ lroute('contact') }}">{{ __('site.home.contact_btn') }}</a>
            </div>
        </div>
    </section>
</x-layout>
