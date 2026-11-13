@php
    use App\Support\ArticleBody;
    use App\Support\Breadcrumbs;
    use App\Support\Citation;
    $cur = app()->getLocale();
    $fallback = $locale !== $cur;
    $title = $study->getTranslation('title', $locale);
    $crumbs = Breadcrumbs::make([['label' => __('content.research.title'), 'url' => lroute('studies')], ['label' => $title, 'url' => $study->url()]]);
    $body = ArticleBody::render($study->getTranslation('body', $locale));
    $toc = ArticleBody::toc($body);
    $abstract = $study->getTranslation('abstract', $locale, false) ?: $study->getTranslation('abstract', 'pt', false);
    $keywords = $study->keywordList($locale);
    $refs = $study->referenceList();
    $authors = $study->authorsList();
    $type = in_array($study->article_type, \App\Models\Study::TYPES, true) ? $study->article_type : 'original';
    $ld = [
        '@context' => 'https://schema.org', '@type' => 'ScholarlyArticle', 'headline' => $title, 'inLanguage' => $locale,
        'url' => $study->url(), 'datePublished' => $study->published_at?->toIso8601String(), 'dateModified' => $study->updated_at?->toIso8601String(),
        'author' => array_map(fn ($a) => \App\Support\Citation::abntName($a) === mb_strtoupper($a) ? ['@type' => 'Organization', 'name' => $a] : ['@type' => 'Person', 'name' => $a], $authors),
        'publisher' => ['@type' => 'Organization', 'name' => 'GEOFLORESTA'],
        'genre' => __('content.research.types.'.$type, [], 'en'),
    ];
    if ($abstract) { $ld['abstract'] = $abstract; }
    if ($keywords) { $ld['keywords'] = implode(', ', $keywords); }
    if ($study->doi) { $ld['sameAs'] = 'https://doi.org/'.$study->doi; }
    if ($refs) { $ld['citation'] = $refs; }
    $cover = $study->cover_image ? asset('storage/'.$study->cover_image) : null;
    $showCover = $study->cover_image && ! str_contains((string) $study->getTranslation('body', $locale), basename($study->cover_image));
    $related = $study->related();
@endphp
<x-layout :title="$study->seoTitle()" :description="$study->seoDescription()" type="article" :image="$cover" :jsonld="[Breadcrumbs::jsonld($crumbs), $ld]">
    <x-slot:head>@vite(['resources/css/content.css', 'resources/js/content.js'])</x-slot:head>
    <x-page-head :title="$title" :crumbs="$crumbs" :meta="__('content.research.label').' · '.__('content.research.types.'.$type)">
        <p class="byline">{{ implode(' · ', $authors) }}</p>
        <ul class="facts">
            @if ($study->published_at)<li>{{ __('content.published', ['date' => format_date($study->published_at)]) }}</li>@endif
            <li><span>{{ __('content.research.published_in') }}</span> {{ $study->venue ?: 'GEOFLORESTA, Beira' }}</li>
            @if ($study->doi)<li><span>DOI</span> <a href="https://doi.org/{{ $study->doi }}" rel="noopener">{{ $study->doi }}</a></li>@endif
            <li><a href="#citar">{{ __('content.research.cite') }}</a></li>
        </ul>
    </x-page-head>

    <div class="section section--tight">
        <div class="wrap doc">
            <article class="doc__main prose article article--paper" @if ($fallback) lang="{{ $locale }}" @endif>
                @if ($fallback)
                    <p class="notice">{{ __('site.lang.only_in', ['language' => __('site.lang.names.'.$locale)]) }}</p>
                @endif

                @if ($abstract)
                    <section class="abstract">
                        <h2 class="abstract__title" id="resumo">{{ __('content.research.abstract') }}</h2>
                        @foreach (preg_split('/\R{2,}/u', trim($abstract)) as $par)<p>{{ $par }}</p>@endforeach
                        @if ($keywords)
                            <p class="keywords"><strong>{{ __('content.research.keywords') }}:</strong>
                                @foreach ($keywords as $k)<span class="tag">{{ $k }}</span>@endforeach</p>
                        @endif
                    </section>
                @endif

                @if ($showCover)
                    <figure class="cover-figure"><x-cover :path="$study->cover_image" :width="1200" :alt="$study->cover_alt ?? ''" />@if ($study->cover_credit)<figcaption class="cover-credit">{{ __('content.image_credit') }}: {{ $study->cover_credit }}</figcaption>@endif</figure>
                @endif

                @if (count($toc) > 2)
                    <details class="toc toc--inline"><summary>{{ __('content.research.toc') }}</summary>
                        <ol>@foreach ($toc as [$id, $t])<li><a href="#{{ $id }}">{{ $t }}</a></li>@endforeach
                            @if ($refs)<li><a href="#referencias">{{ __('content.research.references') }}</a></li>@endif</ol>
                    </details>
                @endif

                {!! $body !!}

                @if ($refs)
                    <section class="references">
                        <h2 id="referencias">{{ __('content.research.references') }}</h2>
                        @foreach ($refs as $r)<p>{!! preg_replace('#(https?://[^\s<]+[^\s<.,;)])#u', '<a href="$1" rel="noopener">$1</a>', e($r)) !!}</p>@endforeach
                    </section>
                @endif

                @if ($study->external_url)
                    <p class="notice"><a class="link-arrow" href="{{ $study->external_url }}" rel="noopener">{{ __('content.research.link') }}</a></p>
                @endif

                <section class="cite-box" id="citar" data-copy-label="{{ __('content.research.copy') }}" data-copied-label="{{ __('content.research.copied') }}">
                    <h2>{{ __('content.research.cite') }}</h2>
                    @foreach (Citation::all($study) as $style => $html)
                        <div class="cite">
                            <p class="cite__style">{{ $style }}</p>
                            <p class="cite__text">{!! $html !!}</p>
                            <button type="button" class="copy-btn cite__copy">{{ __('content.research.copy') }}</button>
                        </div>
                    @endforeach
                </section>
            </article>

            @if (count($toc) > 2)
                <nav class="doc__aside toc toc--side" aria-label="{{ __('content.research.toc') }}">
                    <p class="toc__title">{{ __('content.research.toc') }}</p>
                    <ol>
                        @if ($abstract)<li><a href="#resumo">{{ __('content.research.abstract') }}</a></li>@endif
                        @foreach ($toc as [$id, $t])<li><a href="#{{ $id }}">{{ $t }}</a></li>@endforeach
                        @if ($refs)<li><a href="#referencias">{{ __('content.research.references') }}</a></li>@endif
                        <li><a href="#citar">{{ __('content.research.cite') }}</a></li>
                    </ol>
                </nav>
            @endif
        </div>
    </div>

    @if ($related->isNotEmpty())
        <section class="section section--tight related" aria-labelledby="related-title">
            <div class="wrap">
                <div class="section-head">
                    <h2 id="related-title">{{ __('content.research.more') }}</h2>
                    <a class="link-arrow" href="{{ lroute('studies') }}">{{ __('content.back_research') }}</a>
                </div>
                <ul class="cards">
                    @foreach ($related as $r)
                        @php $l = $r->displayLocale($cur); @endphp
                        <li class="card">
                            <a class="card__link" href="{{ $r->url() }}">
                                <x-cover :path="$r->cover_image" :width="480" alt="" class="card__img" />
                                <span class="meta">{{ __('content.research.types.'.($r->article_type ?: 'original')) }} · {{ $r->year ?: $r->published_at?->year }}</span>
                                <span class="card__title" @if ($l !== $cur) lang="{{ $l }}" @endif>{{ $r->getTranslation('title', $l) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layout>
