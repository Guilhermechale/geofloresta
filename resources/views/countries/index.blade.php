@php
    use App\Geo\World;
    use App\Support\Breadcrumbs;
    $crumbs = Breadcrumbs::make([['label' => __('data.title'), 'url' => lroute('data')], ['label' => __('countries.title'), 'url' => lroute('countries')]]);
@endphp
<x-layout :title="__('countries.title')" :description="__('countries.description')" :noindex="$q !== ''" :jsonld="[Breadcrumbs::jsonld($crumbs)]">
    <x-page-head :title="__('countries.title')" :lede="__('countries.lede', ['n' => $total])" :crumbs="$crumbs" />

    <div class="section section--tight">
        <div class="wrap">
            <form method="get" action="{{ lroute('countries') }}" class="country-search" role="search">
                <label for="c-q">{{ __('countries.search') }}</label>
                <div class="country-search__row">
                    <input id="c-q" type="search" name="q" value="{{ $q }}" maxlength="60" placeholder="{{ __('countries.search_ph') }}" autocomplete="off">
                    <button type="submit" class="btn btn--primary">{{ __('countries.search_btn') }}</button>
                </div>
                @if ($q !== '')<p class="small"><a href="{{ lroute('countries') }}">{{ __('data.clear') }}</a></p>@endif
            </form>

            @if ($q === '' && $top)
                <section class="country-top" aria-labelledby="top-title">
                    <h2 id="top-title" class="h3">{{ __('countries.top_title') }}</h2>
                    <p class="small muted">{{ __('countries.top_note') }}@if ($firesUpdated) · {{ __('countries.updated', ['date' => format_datetime($firesUpdated)]) }}@endif</p>
                    <div class="table-scroll">
                        <table class="table table--top">
                            <thead><tr>
                                <th scope="col">{{ __('countries.country') }}</th>
                                <th scope="col" class="num">{{ __('countries.fires_24h') }}</th>
                                <th scope="col" class="num">{{ __('countries.fires_7d') }}</th>
                                <th scope="col" class="col-map"><span class="visually-hidden">{{ __('countries.open') }}</span></th>
                            </tr></thead>
                            <tbody>
                                @foreach ($top as $iso => $s)
                                    @php $tc = World::find($iso); @endphp
                                    @continue(! $tc)
                                    <tr>
                                        <th scope="row"><a href="{{ lroute('countries.show', ['iso' => strtolower($iso)]) }}">{{ World::name($tc) }}</a></th>
                                        <td class="num">{{ format_number($s['n24']) }}</td>
                                        <td class="num">{{ format_number($s['n7']) }}</td>
                                        <td class="col-map"><a class="link-arrow" href="{{ \App\Support\MapTools::url('fires', null, ['pais' => strtolower($iso)]) }}">{{ __('countries.see_map') }}</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @forelse ($groups as $continent => $list)
                <section class="country-group" aria-labelledby="ct-{{ Str::slug($continent) }}">
                    <h2 id="ct-{{ Str::slug($continent) }}" class="h3">{{ __('countries.continents.'.$continent) }} <span class="muted small">({{ count($list) }})</span></h2>
                    <ul class="country-list">
                        @foreach ($list as $c)
                            @php $n = $fires[$c['iso']]['n7'] ?? 0; @endphp
                            <li>
                                <a href="{{ lroute('countries.show', ['iso' => strtolower($c['iso'])]) }}">
                                    <span class="country-list__name">{{ World::name($c) }}</span>
                                    <span class="coord">{{ $c['iso'] }}@if ($n) · <span title="{{ __('countries.fires_7d') }}">{{ format_number($n) }} {{ __('countries.fires_short') }}</span>@endif</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <p class="notice">{{ __('countries.none', ['q' => $q]) }}</p>
            @endforelse

            <div class="notice">
                <p>{{ __('countries.custom') }}</p>
                <a class="link-arrow" href="{{ lroute('contact') }}?assunto=data">{{ __('data.custom_btn') }}</a>
            </div>
        </div>
    </div>
</x-layout>
