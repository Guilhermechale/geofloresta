@php
    use App\Support\Breadcrumbs;
    use App\Support\PageBody;
    $title = $page->getTranslation('title', $locale);
    $crumbs = Breadcrumbs::make([['label' => $title, 'url' => url()->current()]]);
@endphp
<x-layout :title="$page->seoTitle()" :description="$page->seoDescription()" :jsonld="[Breadcrumbs::jsonld($crumbs)]">
    <x-page-head :title="$title" :crumbs="$crumbs">
        @if ($page->content_updated_at)
            <p class="meta">{{ __('pages.updated', ['date' => format_date($page->content_updated_at)]) }}</p>
        @endif
    </x-page-head>

    <div class="section section--tight">
        <div class="wrap">
            <article class="prose prose--wide" @if ($fallback) lang="{{ $locale }}" @endif>
                @if ($fallback)
                    <p class="notice">{{ __('site.lang.only_in', ['language' => __('site.lang.names.'.$locale)]) }}</p>
                @endif
                @if ($page->getTranslation('summary', $locale, false))
                    <p class="lede">{{ $page->getTranslation('summary', $locale) }}</p>
                @endif
                {!! PageBody::render($page->getTranslation('body', $locale)) !!}
                @if ($page->key === 'cookies')
                    <p><button type="button" class="btn" data-cookie-prefs>{{ __('site.footer.cookie_prefs') }}</button></p>
                @endif
            </article>
        </div>
    </div>
</x-layout>
