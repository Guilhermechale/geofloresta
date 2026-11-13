@php
    use App\Support\Breadcrumbs;
    $cur = app()->getLocale();
    $fallback = $locale !== $cur;
    $title = $study->getTranslation('title', $locale);
    $crumbs = Breadcrumbs::make([['label' => __('content.research.title'), 'url' => lroute('studies')], ['label' => $title, 'url' => $study->url()]]);
    $ld = [
        '@context' => 'https://schema.org', '@type' => 'ScholarlyArticle', 'headline' => $title, 'inLanguage' => $locale,
        'url' => $study->url(), 'datePublished' => $study->published_at?->toIso8601String(), 'dateModified' => $study->updated_at?->toIso8601String(),
        'author' => $study->authors ? ['@type' => 'Person', 'name' => $study->authors] : ['@type' => 'Organization', 'name' => 'GEOFLORESTA'],
        'publisher' => ['@type' => 'Organization', 'name' => 'GEOFLORESTA'],
    ];
    if ($study->doi) { $ld['sameAs'] = 'https://doi.org/'.$study->doi; }
    $cover = $study->cover_image ? asset('storage/'.$study->cover_image) : null;
@endphp
<x-layout :title="$study->seoTitle()" :description="$study->seoDescription()" type="article" :image="$cover" :jsonld="[Breadcrumbs::jsonld($crumbs), $ld]">
    <x-slot:head>@vite(['resources/css/content.css', 'resources/js/content.js'])</x-slot:head>
    <x-page-head :title="$title" :crumbs="$crumbs" :meta="__('content.research.title')">
        @if ($s = $study->getTranslation('summary', $locale, false))<p class="lede" @if ($fallback) lang="{{ $locale }}" @endif>{{ $s }}</p>@endif
        <p class="meta">
            @if ($study->published_at){{ __('content.published', ['date' => format_date($study->published_at)]) }}@endif
            @if ($study->authors) · {{ $study->authors }}@endif
            @if ($study->venue) · {{ $study->venue }}@endif
        </p>
    </x-page-head>
    <div class="section section--tight">
        <div class="wrap">
            <article class="prose article" @if ($fallback) lang="{{ $locale }}" @endif>
                @if ($study->cover_image)
                    <figure class="cover-figure"><x-cover :path="$study->cover_image" :width="1200" :alt="$study->cover_alt ?? ''" :eager="true" /></figure>
                @endif
                @if ($fallback)
                    <p class="notice">{{ __('site.lang.only_in', ['language' => __('site.lang.names.'.$locale)]) }}</p>
                @endif
                {!! $study->getTranslation('body', $locale) !!}
                @if ($study->doi || $study->external_url)
                    <p class="notice">
                        @if ($study->doi)<strong>{{ __('content.research.doi') }}:</strong> <a href="https://doi.org/{{ $study->doi }}" rel="noopener">{{ $study->doi }}</a>@endif
                        @if ($study->external_url) <a class="link-arrow" href="{{ $study->external_url }}" rel="noopener">{{ __('content.research.link') }}</a>@endif
                    </p>
                @endif
                <p><a class="link-arrow" href="{{ lroute('studies') }}">{{ __('content.back_research') }}</a></p>
            </article>
        </div>
    </div>
</x-layout>
