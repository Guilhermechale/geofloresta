@php
    use App\Support\Breadcrumbs;
    $crumbs = Breadcrumbs::make([['label' => __('content.tutorials.title'), 'url' => lroute('learn')]]);
    $loc = app()->getLocale();
@endphp
<x-layout :title="__('content.tutorials.title')" :description="__('content.tutorials.description')" :noindex="! empty(array_filter($filters))" :jsonld="[Breadcrumbs::jsonld($crumbs)]">
    <x-page-head :title="__('content.tutorials.title')" :lede="__('content.tutorials.lede')" :crumbs="$crumbs">
        @if (count($platforms) > 1)
            <nav class="chips" aria-label="{{ __('content.tutorials.platform') }}">
                <a href="{{ lroute('learn') }}" @if (empty($filters['plataforma'])) aria-current="true" @endif>{{ __('content.tutorials.all') }}</a>
                @foreach ($platforms as $p)
                    <a href="{{ lroute('learn') }}?plataforma={{ $p }}" @if (($filters['plataforma'] ?? '') === $p) aria-current="true" @endif>{{ __('content.tutorials.platforms.'.$p) }}</a>
                @endforeach
            </nav>
        @endif
    </x-page-head>

    <div class="section section--tight">
        <div class="wrap">
            @if ($tutorials->isEmpty())
                <p class="muted">{{ __('content.tutorials.none') }}</p>
            @else
                <div class="tcards">
                    @foreach ($tutorials as $t)
                        @php $l = $t->displayLocale($loc); @endphp
                        <x-tool-card :href="$t->url()" :image="$t->cover_image ? (\App\Support\Cover::image($t->cover_image, 480)['src'] ?? null) : 'img/cards/tutoriais.webp'"
                            :title="$t->getTranslation('title', $l)" :lang="$l !== $loc ? $l : null"
                            :text="__('content.tutorials.platforms.'.$t->platform).' · '.__('content.tutorials.levels.'.$t->level).($t->duration_minutes ? ' · '.__('content.tutorials.duration', ['n' => $t->duration_minutes]) : '')" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layout>
