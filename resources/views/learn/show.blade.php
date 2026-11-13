@php
    use App\Support\Breadcrumbs;
    $cur = app()->getLocale();
    $fallback = $locale !== $cur;
    $title = $tutorial->getTranslation('title', $locale);
    $crumbs = Breadcrumbs::make([['label' => __('content.tutorials.title'), 'url' => lroute('learn')], ['label' => $title, 'url' => $tutorial->url()]]);
    $ld = [
        '@context' => 'https://schema.org', '@type' => 'TechArticle', 'headline' => $title, 'inLanguage' => $locale,
        'url' => $tutorial->url(), 'datePublished' => $tutorial->published_at?->toIso8601String(),
        'dateModified' => ($tutorial->content_updated_at ?? $tutorial->updated_at)?->toIso8601String(),
        'author' => ['@type' => 'Organization', 'name' => 'GEOFLORESTA'], 'publisher' => ['@type' => 'Organization', 'name' => 'GEOFLORESTA'],
        'proficiencyLevel' => $tutorial->level, 'isAccessibleForFree' => true,
    ];
    $cover = $tutorial->cover_image ? asset('storage/'.$tutorial->cover_image) : null;
@endphp
<x-layout :title="$tutorial->seoTitle()" :description="$tutorial->seoDescription()" type="article" :image="$cover" :jsonld="[Breadcrumbs::jsonld($crumbs), $ld]">
    <x-slot:head>@vite(['resources/css/content.css', 'resources/js/content.js'])</x-slot:head>
    <x-page-head :title="$title" :crumbs="$crumbs"
        :meta="__('content.tutorials.platforms.'.$tutorial->platform).' · '.__('content.tutorials.levels.'.$tutorial->level).($tutorial->duration_minutes ? ' · '.__('content.tutorials.duration', ['n' => $tutorial->duration_minutes]) : '')">
        @if ($s = $tutorial->getTranslation('summary', $locale, false))<p class="lede" @if ($fallback) lang="{{ $locale }}" @endif>{{ $s }}</p>@endif
        <p class="meta">
            @if ($tutorial->published_at){{ __('content.published', ['date' => format_date($tutorial->published_at)]) }}@endif
            @if ($tutorial->content_updated_at) · {{ __('content.updated', ['date' => format_date($tutorial->content_updated_at)]) }}@endif
        </p>
        @if ($tutorial->colab_url || $tutorial->gee_url)
            <p class="hero__actions">
                @if ($tutorial->colab_url)<a class="btn" href="{{ $tutorial->colab_url }}" rel="noopener">{{ __('content.tutorials.open_colab') }}</a>@endif
                @if ($tutorial->gee_url)<a class="btn" href="{{ $tutorial->gee_url }}" rel="noopener">{{ __('content.tutorials.open_gee') }}</a>@endif
            </p>
        @endif
    </x-page-head>

    <div class="section section--tight">
        <div class="wrap">
            <article class="prose article" @if ($fallback) lang="{{ $locale }}" @endif data-copy-label="{{ __('content.tutorials.copy') }}" data-copied-label="{{ __('content.tutorials.copied') }}">
                @if ($tutorial->cover_image)
                    <figure class="cover-figure"><x-cover :path="$tutorial->cover_image" :width="1200" :alt="$tutorial->cover_alt ?? ''" :eager="true" /></figure>
                @endif
                @if ($fallback)
                    <p class="notice">{{ __('site.lang.only_in', ['language' => __('site.lang.names.'.$locale)]) }}</p>
                @endif
                @if ($p = $tutorial->getTranslation('prerequisites', $locale, false))
                    <div class="notice"><p><strong>{{ __('content.tutorials.prerequisites') }}:</strong> {{ $p }}</p></div>
                @endif
                {!! $tutorial->getTranslation('body', $locale) !!}

                @if ($tutorial->attachments->isNotEmpty())
                    <h2>{{ __('content.tutorials.attachments') }}</h2>
                    <ul>
                        @foreach ($tutorial->attachments as $a)
                            <li><a href="{{ asset('storage/'.$a->path) }}" download>{{ $a->getTranslation('label', $cur, false) ?: $a->getTranslation('label', 'pt') }}</a> <small class="coord">{{ $a->filename }} · {{ format_bytes((int) $a->size_bytes) }}</small></li>
                        @endforeach
                    </ul>
                @endif
                <p><a class="link-arrow" href="{{ lroute('learn') }}">{{ __('content.back_tutorials') }}</a></p>
            </article>
        </div>
    </div>
</x-layout>
