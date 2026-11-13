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
                <ul class="catalogue">
                    @foreach ($tutorials as $t)
                        @php $l = $t->displayLocale($loc); @endphp
                        <li class="catalogue__item">
                            <p class="meta">
                                {{ __('content.tutorials.platforms.'.$t->platform) }} · {{ __('content.tutorials.levels.'.$t->level) }}
                                @if ($t->duration_minutes) · {{ __('content.tutorials.duration', ['n' => $t->duration_minutes]) }}@endif
                                @if ($t->published_at) · {{ format_date($t->published_at, 'medium') }}@endif
                            </p>
                            <h2 class="catalogue__title"><a href="{{ $t->url() }}" @if ($l !== $loc) lang="{{ $l }}" @endif>{{ $t->getTranslation('title', $l) }}</a></h2>
                            @if ($s = $t->getTranslation('summary', $l, false))<p class="muted" @if ($l !== $loc) lang="{{ $l }}" @endif>{{ $s }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-layout>
