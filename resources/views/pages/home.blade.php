<x-layout :jsonld="\App\Support\Schema::home()">
    @php
        $cards = [];
        foreach (['lulc', 'fires', 'ndvi', 'relief'] as $k) {
            $cards[] = ['href' => \App\Support\MapTools::url($k), 'image' => \App\Support\MapTools::TOOLS[$k]['image'], 'title' => __('map.tools.'.$k.'.title'), 'text' => __('map.tools.'.$k.'.text')];
        }
        foreach (['data' => 'dados', 'learn' => 'tutoriais', 'studies' => 'pesquisas', 'services' => 'servicos'] as $route => $img) {
            $cards[] = ['href' => lroute($route), 'image' => "img/cards/{$img}.webp", 'title' => __("site.home2.cards.$route.title"), 'text' => __("site.home2.cards.$route.text")];
        }
    @endphp
    <section class="hero hero--compact">
        <x-carto-art />
        <div class="wrap hero__inner">
            <h1>{{ __('site.home.h1') }}</h1>
            <p class="lede">{{ __('site.home2.lede') }}</p>
        </div>
    </section>

    <section class="section section--tight" aria-labelledby="find-title">
        <div class="wrap">
            <x-section-title id="find-title" num="01" :eyebrow="__('site.home2.find_eyebrow')" :title="__('site.home2.find')" :text="__('site.home2.find_text')" />
            <div class="tcards tcards--home">
                @foreach ($cards as $c)
                    <x-tool-card :href="$c['href']" :image="$c['image']" :title="$c['title']" :text="$c['text']" :eager="$loop->index < 4" />
                @endforeach
            </div>
        </div>
    </section>

    <x-service-cta />

    @if ($tutorials->isNotEmpty())
        <section class="section section--tight" aria-labelledby="learn-title">
            <div class="wrap">
                <x-section-title id="learn-title" num="02" :eyebrow="__('site.home2.learn_eyebrow')" :title="__('site.home2.latest_learn')" :text="__('site.home2.learn_text')" :href="lroute('learn')" :link="__('site.home.all_learn')" />
                <div class="tcards">
                    @foreach ($tutorials->take(3) as $t)
                        @php $l = $t->displayLocale(app()->getLocale()); @endphp
                        <x-tool-card :href="$t->url()" :image="$t->cover_image ? \App\Support\Cover::image($t->cover_image, 480)['src'] ?? null : null" :title="$t->getTranslation('title', $l)" :text="__('content.tutorials.platforms.'.$t->platform)" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($studies->isNotEmpty())
        <section class="section section--tight" aria-labelledby="research-title">
            <div class="wrap">
                <x-section-title id="research-title" num="03" :eyebrow="__('site.home2.studies_eyebrow')" :title="__('site.home2.latest_studies')" :text="__('site.home2.studies_text')" :href="lroute('studies')" :link="__('content.back_research')" />
                <div class="tcards">
                    @foreach ($studies->take(3) as $s)
                        @php $l = $s->displayLocale(app()->getLocale()); @endphp
                        <x-tool-card :href="$s->url()" :image="$s->cover_image ? \App\Support\Cover::image($s->cover_image, 480)['src'] ?? null : null" :title="$s->getTranslation('title', $l)" :text="$s->published_at ? format_date($s->published_at, 'medium') : null" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <x-intro-video />

    <section class="section sustain" aria-labelledby="sustain-title">
        <div class="wrap">
            <div class="sustain__head">
                <h2 id="sustain-title">{{ __('site.home.sustain.title') }}</h2>
                <p class="lede">{{ __('site.home.sustain.lede') }}</p>
            </div>
            <ul class="sustain__pillars">
                @foreach (['env' => 'maps.tool', 'soc' => 'learn', 'eco' => 'services'] as $key => $route)
                    <li class="sustain__pillar sustain__pillar--{{ $key }}">
                        <h3>{{ __("site.home.sustain.$key.title") }}</h3>
                        <p>{{ __("site.home.sustain.$key.text") }}</p>
                        <a class="link-arrow" href="{{ $route === 'maps.tool' ? \App\Support\MapTools::url('fires') : lroute($route) }}">{{ __("site.home.sustain.$key.link") }}</a>
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
