@php
    use App\Support\ArticleBody;
    use App\Support\Breadcrumbs;
    $cur = app()->getLocale();
    $fallback = $locale !== $cur;
    $title = $tutorial->getTranslation('title', $locale);
    $crumbs = Breadcrumbs::make([['label' => __('content.tutorials.title'), 'url' => lroute('learn')], ['label' => $title, 'url' => $tutorial->url()]]);
    $body = ArticleBody::render($tutorial->getTranslation('body', $locale));
    $toc = ArticleBody::toc($body);
    $exercise = ArticleBody::render($tutorial->getTranslation('exercise', $locale, false) ?: $tutorial->getTranslation('exercise', 'pt', false));
    $objectives = $tutorial->lines('objectives', $locale);
    $prereq = $tutorial->lines('prerequisites', $locale);
    $takeaways = $tutorial->lines('takeaways', $locale);
    $sources = $tutorial->sourceList();
    $showCover = $tutorial->cover_image && ! str_contains((string) $tutorial->getTranslation('body', $locale), basename($tutorial->cover_image));
    $ld = [
        '@context' => 'https://schema.org', '@type' => 'TechArticle', 'headline' => $title, 'inLanguage' => $locale,
        'url' => $tutorial->url(), 'datePublished' => $tutorial->published_at?->toIso8601String(),
        'dateModified' => ($tutorial->content_updated_at ?? $tutorial->updated_at)?->toIso8601String(),
        'author' => ['@type' => 'Organization', 'name' => 'GEOFLORESTA'], 'publisher' => ['@type' => 'Organization', 'name' => 'GEOFLORESTA'],
        'proficiencyLevel' => $tutorial->level, 'isAccessibleForFree' => true,
    ];
    if ($objectives) { $ld['teaches'] = $objectives; }
    if ($tutorial->duration_minutes) { $ld['timeRequired'] = 'PT'.$tutorial->duration_minutes.'M'; }
    $cover = $tutorial->cover_image ? asset('storage/'.$tutorial->cover_image) : null;
    $related = $tutorial->related();
@endphp
<x-layout :title="$tutorial->seoTitle()" :description="$tutorial->seoDescription()" type="article" :image="$cover" :jsonld="[Breadcrumbs::jsonld($crumbs), $ld]">
    <x-slot:head>@vite(['resources/css/content.css', 'resources/js/content.js'])</x-slot:head>
    <x-page-head :title="$title" :crumbs="$crumbs" :meta="__('content.tutorials.label').' · '.__('content.tutorials.platforms.'.$tutorial->platform)">
        @if ($s = $tutorial->getTranslation('summary', $locale, false))<p class="lede" @if ($fallback) lang="{{ $locale }}" @endif>{{ $s }}</p>@endif
        <ul class="facts">
            <li><span>{{ __('content.tutorials.level') }}</span> {{ __('content.tutorials.levels.'.$tutorial->level) }}</li>
            @if ($tutorial->duration_minutes)<li><span>⏱</span> {{ __('content.tutorials.duration', ['n' => $tutorial->duration_minutes]) }}</li>@endif
            @if ($tutorial->published_at)<li>{{ __('content.published', ['date' => format_date($tutorial->published_at)]) }}</li>@endif
            @if ($tutorial->content_updated_at)<li>{{ __('content.updated', ['date' => format_date($tutorial->content_updated_at)]) }}</li>@endif
        </ul>
        @if ($tutorial->colab_url || $tutorial->gee_url)
            <p class="hero__actions">
                @if ($tutorial->colab_url)<a class="btn" href="{{ $tutorial->colab_url }}" rel="noopener">{{ __('content.tutorials.open_colab') }}</a>@endif
                @if ($tutorial->gee_url)<a class="btn" href="{{ $tutorial->gee_url }}" rel="noopener">{{ __('content.tutorials.open_gee') }}</a>@endif
            </p>
        @endif
    </x-page-head>

    <div class="section section--tight">
        <div class="wrap doc">
            <article class="doc__main prose article" @if ($fallback) lang="{{ $locale }}" @endif data-copy-label="{{ __('content.tutorials.copy') }}" data-copied-label="{{ __('content.tutorials.copied') }}">
                @if ($fallback)
                    <p class="notice">{{ __('site.lang.only_in', ['language' => __('site.lang.names.'.$locale)]) }}</p>
                @endif
                @if ($showCover)
                    <figure class="cover-figure"><x-cover :path="$tutorial->cover_image" :width="1200" :alt="$tutorial->cover_alt ?? ''" :eager="true" /></figure>
                @endif

                @if ($objectives || $prereq)
                    <div class="brief">
                        @if ($objectives)
                            <section class="brief__box">
                                <h2 class="brief__title" id="aprender">{{ __('content.tutorials.learn') }}</h2>
                                <ul class="checklist">@foreach ($objectives as $o)<li>{{ $o }}</li>@endforeach</ul>
                            </section>
                        @endif
                        @if ($prereq)
                            <section class="brief__box brief__box--alt">
                                <h2 class="brief__title" id="antes">{{ __('content.tutorials.before') }}</h2>
                                <ul>@foreach ($prereq as $p)<li>{{ $p }}</li>@endforeach</ul>
                            </section>
                        @endif
                    </div>
                @endif

                @if (count($toc) > 2)
                    <details class="toc toc--inline"><summary>{{ __('content.tutorials.toc') }}</summary>
                        <ol>@foreach ($toc as [$id, $t])<li><a href="#{{ $id }}">{{ $t }}</a></li>@endforeach
                            @if ($exercise)<li><a href="#exercicio">{{ __('content.tutorials.exercise') }}</a></li>@endif</ol>
                    </details>
                @endif

                {!! $body !!}

                @if ($exercise)
                    <section class="exercise">
                        <h2 id="exercicio">{{ __('content.tutorials.exercise') }}</h2>
                        {!! $exercise !!}
                    </section>
                @endif

                @if ($takeaways)
                    <section class="takeaways">
                        <h2 id="pontos-chave">{{ __('content.tutorials.takeaways') }}</h2>
                        <ul>@foreach ($takeaways as $k)<li>{{ $k }}</li>@endforeach</ul>
                    </section>
                @endif

                @if ($tutorial->attachments->isNotEmpty())
                    <h2>{{ __('content.tutorials.attachments') }}</h2>
                    <ul>
                        @foreach ($tutorial->attachments as $a)
                            <li><a href="{{ asset('storage/'.$a->path) }}" download>{{ $a->getTranslation('label', $cur, false) ?: $a->getTranslation('label', 'pt') }}</a> <small class="coord">{{ $a->filename }} · {{ format_bytes((int) $a->size_bytes) }}</small></li>
                        @endforeach
                    </ul>
                @endif

                @if ($sources)
                    <section class="sources">
                        <h2 id="fontes">{{ __('content.tutorials.sources') }}</h2>
                        <ol>
                            @foreach ($sources as $src)
                                <li>@if ($src['url'])<a href="{{ $src['url'] }}" rel="noopener">{{ $src['title'] }}</a>@else{{ $src['title'] }}@endif</li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                <aside class="help-box">
                    <p class="help-box__title">{{ __('content.tutorials.help_title') }}</p>
                    <p>{{ __('content.tutorials.help_text') }}</p>
                    <a class="btn btn--small" href="{{ lroute('contact') }}">{{ __('content.tutorials.help_btn') }}</a>
                </aside>
            </article>

            @if (count($toc) > 2)
                <nav class="doc__aside toc toc--side" aria-label="{{ __('content.tutorials.toc') }}">
                    <p class="toc__title">{{ __('content.tutorials.toc') }}</p>
                    <ol>@foreach ($toc as [$id, $t])<li><a href="#{{ $id }}">{{ $t }}</a></li>@endforeach
                        @if ($exercise)<li><a href="#exercicio">{{ __('content.tutorials.exercise') }}</a></li>@endif</ol>
                </nav>
            @endif
        </div>
    </div>

    @if ($related->isNotEmpty())
        <section class="section section--tight related" aria-labelledby="related-title">
            <div class="wrap">
                <div class="section-head">
                    <h2 id="related-title">{{ __('content.tutorials.next') }}</h2>
                    <a class="link-arrow" href="{{ lroute('learn') }}">{{ __('content.back_tutorials') }}</a>
                </div>
                <ul class="cards">
                    @foreach ($related as $r)
                        @php $l = $r->displayLocale($cur); @endphp
                        <li class="card">
                            <a class="card__link" href="{{ $r->url() }}">
                                <x-cover :path="$r->cover_image" :width="480" alt="" class="card__img" />
                                <span class="meta">{{ __('content.tutorials.platforms.'.$r->platform) }} · {{ __('content.tutorials.levels.'.$r->level) }}</span>
                                <span class="card__title" @if ($l !== $cur) lang="{{ $l }}" @endif>{{ $r->getTranslation('title', $l) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layout>
