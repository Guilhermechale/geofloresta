<x-layout>
    <section class="hero">
        <x-carto-art />
        <div class="wrap hero__inner">
            <h1>{{ __('site.home.h1') }}</h1>
            <p class="lede">{{ __('site.home.lede') }}</p>
            <div class="hero__actions">
                <a class="btn btn--primary" href="{{ lroute('data') }}">{{ __('site.home.cta_data') }}</a>
                <a class="btn" href="{{ lroute('contact') }}">{{ __('site.home.cta_contact') }}</a>
            </div>
        </div>
    </section>

    <x-intro-video />

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
                <p><a class="link-arrow" href="{{ lroute('countries') }}">{{ __('site.home.countries_link') }}</a></p>
            </div>
            <div class="span-6 start-7 monitor">
                @if ($world)
                    <p class="monitor__value">{{ format_number($world['n24']) }}</p>
                    <p>{{ __('site.home.monitor_label') }} · {{ trans_choice('site.home.monitor_countries', $world['countries'], ['n' => $world['countries']]) }}</p>
                    @if ($fire)<p>{{ __('site.home.monitor_moz', ['n' => format_number($fire['count_24h'])]) }}</p>@endif
                    <p class="meta">{{ __('site.home.monitor_updated', ['date' => format_datetime($world['updated_at'])]) }}</p>
                @elseif ($fire)
                    <p class="monitor__value">{{ format_number($fire['count_24h']) }}</p>
                    <p>{{ __('site.home.monitor_label_moz') }}</p>
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
                            <dt>{{ __('site.specimen.licence') }}</dt><dd>{{ $d->licenceLabel() }}</dd>
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
                    <li class="entry @if ($t->cover_image) entry--media @endif">
                        <x-cover :path="$t->cover_image" :width="320" alt="" class="entry__thumb" />
                        <div class="entry__body">
                            <span class="meta">{{ __('content.tutorials.platforms.'.$t->platform) }}</span>
                            <a href="{{ $t->url() }}" @if ($l !== app()->getLocale()) lang="{{ $l }}" @endif>{{ $t->getTranslation('title', $l) }}</a>
                        </div>
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
                        <li class="entry @if ($s->cover_image) entry--media @endif">
                            <x-cover :path="$s->cover_image" :width="320" alt="" class="entry__thumb" />
                            <div class="entry__body">
                                <span class="meta">{{ $s->published_at ? format_date($s->published_at, 'medium') : '' }}</span>
                                <a href="{{ $s->url() }}" @if ($l !== app()->getLocale()) lang="{{ $l }}" @endif>{{ $s->getTranslation('title', $l) }}</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="section sustain" aria-labelledby="sustain-title">
        <div class="wrap">
            <div class="sustain__head">
                <h2 id="sustain-title">{{ __('site.home.sustain.title') }}</h2>
                <p class="lede">{{ __('site.home.sustain.lede') }}</p>
            </div>
            <ul class="sustain__pillars">
                @foreach (['env' => 'maps', 'soc' => 'learn', 'eco' => 'services'] as $key => $route)
                    <li class="sustain__pillar sustain__pillar--{{ $key }}">
                        <h3>{{ __("site.home.sustain.$key.title") }}</h3>
                        <p>{{ __("site.home.sustain.$key.text") }}</p>
                        <a class="link-arrow" href="{{ lroute($route) }}">{{ __("site.home.sustain.$key.link") }}</a>
                    </li>
                @endforeach
            </ul>
            <h3 class="sustain__goals-title">{{ __('site.home.sustain.goals_title') }}</h3>
            <ul class="sdg-list">
                @foreach ([4 => 'learn', 6 => 'data', 11 => 'studies', 13 => 'maps', 15 => 'studies', 17 => 'data'] as $n => $route)
                    <li>
                        <a class="sdg" href="{{ lroute($route) }}">
                            <span class="sdg__num" aria-hidden="true">{{ $n }}</span>
                            <span class="sdg__text">
                                <strong>{{ __('site.home.sustain.sdg') }} {{ $n }} · {{ __("site.home.sustain.goals.$n.name") }}</strong>
                                <span>{{ __("site.home.sustain.goals.$n.how") }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

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
