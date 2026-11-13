@php
    use App\Http\Controllers\SitemapController;
    use App\Support\Breadcrumbs;
    $crumbs = Breadcrumbs::make([['label' => __('pages.sitemap.title'), 'url' => lroute('sitemap')]]);
    $labels = [
        'home' => 'site.nav.home', 'services' => 'site.nav.services', 'data' => 'site.nav.data', 'maps' => 'site.nav.maps',
        'learn' => 'site.nav.learn', 'studies' => 'site.nav.studies', 'about' => 'site.nav.about', 'contact' => 'site.nav.contact', 'search' => 'site.nav.search',
        'privacy' => 'site.footer.privacy', 'cookies' => 'site.footer.cookies', 'terms' => 'site.footer.terms',
        'licences' => 'site.footer.licences', 'accessibility' => 'site.footer.accessibility', 'sitemap' => 'site.footer.sitemap',
    ];
    $loc = app()->getLocale();
    $groups = ['datasets' => $datasets, 'tutorials' => $tutorials, 'studies' => $studies];
@endphp
<x-layout :title="__('pages.sitemap.title')" :description="__('pages.sitemap.description')" :jsonld="[Breadcrumbs::jsonld($crumbs)]">
    <x-page-head :title="__('pages.sitemap.title')" :crumbs="$crumbs" />
    <div class="section section--tight">
        <div class="wrap grid">
            <nav class="span-4" aria-labelledby="sm-main">
                <h2 id="sm-main" class="h4">{{ __('pages.sitemap.main') }}</h2>
                <ul class="plain-list">
                    @foreach (SitemapController::MAIN as $r)<li><a href="{{ lroute($r) }}">{{ __($labels[$r]) }}</a></li>@endforeach
                </ul>
                <h2 class="h4">{{ __('pages.sitemap.legal') }}</h2>
                <ul class="plain-list">
                    @foreach (SitemapController::LEGAL as $r)<li><a href="{{ lroute($r) }}">{{ __($labels[$r]) }}</a></li>@endforeach
                </ul>
            </nav>
            <div class="span-7 start-6">
                @foreach ($groups as $key => $items)
                    @if ($items->isNotEmpty())
                        <h2 class="h4">{{ __('pages.sitemap.'.$key) }}</h2>
                        <ul class="plain-list">
                            @foreach ($items as $item)
                                @php $l = $item->displayLocale($loc); try { $u = $item->url(); } catch (\Throwable) { $u = null; } @endphp
                                @if ($u)<li><a href="{{ $u }}" @if ($l !== $loc) lang="{{ $l }}" @endif>{{ $item->getTranslation('title', $l) }}</a></li>@endif
                            @endforeach
                        </ul>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</x-layout>
