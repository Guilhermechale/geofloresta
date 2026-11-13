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
                <ul class="catalogue">
                    @foreach ($studies as $s)
                        @php $l = $s->displayLocale($loc); @endphp
                        <li class="catalogue__item">
                            <p class="meta">{{ $s->published_at ? format_date($s->published_at, 'medium') : $s->year }}@if ($s->venue) · {{ $s->venue }}@endif</p>
                            <h2 class="catalogue__title"><a href="{{ $s->url() }}" @if ($l !== $loc) lang="{{ $l }}" @endif>{{ $s->getTranslation('title', $l) }}</a></h2>
                            @if ($sum = $s->getTranslation('summary', $l, false))<p class="muted" @if ($l !== $loc) lang="{{ $l }}" @endif>{{ $sum }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-layout>
