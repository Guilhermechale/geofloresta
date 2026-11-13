@php
    use App\Support\Breadcrumbs;
    $crumbs = Breadcrumbs::make([['label' => __('pages.search.title'), 'url' => lroute('search')]]);
@endphp
<x-layout :title="__('pages.search.title')" :description="__('pages.search.description')" :noindex="$q !== ''" :jsonld="[Breadcrumbs::jsonld($crumbs)]">
    <x-page-head :title="__('pages.search.title')" :crumbs="$crumbs">
        <form method="get" action="{{ lroute('search') }}" role="search" class="search-form">
            <label for="q" class="visually-hidden">{{ __('pages.search.label') }}</label>
            <input id="q" name="q" type="search" value="{{ $q }}" placeholder="{{ __('pages.search.placeholder') }}" minlength="2" maxlength="100" autocomplete="off">
            <button type="submit" class="btn btn--primary">{{ __('pages.search.submit') }}</button>
        </form>
    </x-page-head>

    <div class="section section--tight">
        <div class="wrap">
            @if ($q === '')
                <p class="muted">{{ __('pages.search.hint') }}</p>
            @else
                <p role="status">{{ trans_choice('pages.search.results', $results->count(), ['q' => $q, 'count' => $results->count()]) }}</p>
                <ul class="entries">
                    @foreach ($results as $r)
                        <li class="entry">
                            <span class="meta">{{ __('pages.search.types.'.$r->type) }}</span>
                            <a href="{{ $r->url }}" @if ($r->locale !== app()->getLocale()) lang="{{ $r->locale }}" @endif>{{ $r->title }}</a>
                            @if ($r->summary)<p class="muted small">{{ \Illuminate\Support\Str::limit(strip_tags($r->summary), 220) }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-layout>
