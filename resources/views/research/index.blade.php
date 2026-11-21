@php
    use App\Support\Breadcrumbs;
    $crumbs = Breadcrumbs::make([['label' => __('content.research.title'), 'url' => lroute('studies')]]);
    $loc = app()->getLocale();
@endphp
<x-layout :title="__('content.research.title')" :description="__('content.research.description')" :jsonld="[Breadcrumbs::jsonld($crumbs)]">
    <x-page-head :title="__('content.research.title')" :lede="__('content.research.lede')" :crumbs="$crumbs" />
    <div class="section section--tight">
        <div class="wrap">
            @if ($studies->isEmpty())
                <p class="muted">{{ __('content.research.none') }}</p>
            @else
                <div class="tcards">
                    @foreach ($studies as $s)
                        @php $l = $s->displayLocale($loc); @endphp
                        <x-tool-card :href="$s->url()" :image="$s->cover_image ? (\App\Support\Cover::image($s->cover_image, 480)['src'] ?? null) : 'img/cards/pesquisas.webp'"
                            :title="$s->getTranslation('title', $l)" :lang="$l !== $loc ? $l : null"
                            :text="__('content.research.types.'.($s->article_type ?: 'original')).' · '.($s->published_at ? format_date($s->published_at, 'medium') : $s->year)" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <x-service-cta compact />
</x-layout>
