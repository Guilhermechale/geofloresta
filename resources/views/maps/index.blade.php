@php
    use App\Support\Breadcrumbs;
    $crumbs = Breadcrumbs::make([['label' => __('map.nav'), 'url' => lroute('maps')]]);
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
    $provinceNames = $isMoz ? collect(json_decode((string) \Illuminate\Support\Facades\Storage::disk('public')->get('geo/mz-provincias.geojson'), true)['features'] ?? [])
        ->pluck('properties.nome')->sort()->values() : collect();
    $isoLower = strtolower($config['iso']);
    $pageTitle = $isMoz && ! request()->has('pais') ? __('map.title') : __('map.title').' · '.$countryName;
@endphp
<x-layout :title="$pageTitle" :description="__('map.description')" :noindex="request()->has('pais') && $isMoz" :jsonld="[Breadcrumbs::jsonld($crumbs)]">
    <x-slot:head>
        @vite(['resources/css/map.css', 'resources/js/map.js'])
        <link rel="preconnect" href="https://tiles.openfreemap.org" crossorigin>
        <link rel="preconnect" href="https://gibs.earthdata.nasa.gov" crossorigin>
    </x-slot:head>

    <x-page-head :title="__('map.title')" :lede="__('map.lede')" :crumbs="$crumbs">
        <p class="meta map-status">
            <span class="dot-fire" aria-hidden="true"></span>{{ __('map.near_real_time') }}
            @if ($summary)
                · {{ __('map.updated', ['date' => format_datetime($summary['updated_at'])]) }}
                @if (! empty($summary['latest_acq'])) · {{ __('map.latest', ['date' => format_datetime($summary['latest_acq'])]) }}@endif
            @elseif (! $isMoz && ! empty($world['updated_at']))
                · {{ __('map.updated', ['date' => format_datetime($world['updated_at'])]) }}
                @if (! empty($stats['latest'])) · {{ __('map.latest', ['date' => format_datetime($stats['latest'])]) }}@endif
            @else
                · {{ __('map.no_data') }}
            @endif
        </p>
    </x-page-head>

    <section class="map-shell" aria-label="{{ __('map.map_label') }}">
        <div class="map-canvas" id="map" data-map role="region" aria-label="{{ __('map.map_label_country', ['country' => $countryName]) }}" data-no-webgl="{{ __('map.no_webgl') }}">
            <p class="map-loading">{{ __('map.loading') }}</p>
            <noscript><p class="map-loading">{{ __('map.no_webgl') }}</p></noscript>
        </div>

        <aside class="map-panel" aria-label="{{ __('map.layers') }}">
            @if ($countries)
                <form class="map-form map-country" method="get" action="{{ lroute('maps') }}" data-country>
                    <label for="map-country" class="meta">{{ __('map.country') }}</label>
                    <div class="map-country__row">
                        <select id="map-country" name="pais">
                            @foreach ($countries as $c)
                                <option value="{{ strtolower($c['iso']) }}" @selected($c['iso'] === $config['iso'])>{{ \App\Geo\World::name($c) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn--small">{{ __('map.country_go') }}</button>
                    </div>
                    <p class="small muted">{{ __('map.country_help') }}</p>
                </form>
            @endif
            <form class="map-form" data-map-layers>
                <fieldset>
                    <legend class="meta">{{ __('map.layers') }}</legend>
                    <label class="check"><input type="checkbox" name="fires" checked> <span>{{ __('map.fires') }}</span></label>
                    <ul class="legend" aria-hidden="true">
                        <li><span class="sw sw--24"></span>{{ __('map.fires_age.24') }}</li>
                        <li><span class="sw sw--48"></span>{{ __('map.fires_age.48') }}</li>
                        <li><span class="sw sw--168"></span>{{ __('map.fires_age.168') }}</li>
                    </ul>
                    @if ($isMoz)
                        <label class="check"><input type="checkbox" name="provinces" checked> <span>{{ __('map.provinces') }}</span></label>
                        <label class="check"><input type="checkbox" name="districts"> <span>{{ __('map.districts') }}</span></label>
                    @else
                        <label class="check"><input type="checkbox" name="outline" checked> <span>{{ __('map.outline') }}</span></label>
                    @endif
                    @if (! empty($config['gibs']['truecolor']))
                        <label class="check"><input type="checkbox" name="truecolor"> <span>{{ __('map.truecolor') }}</span></label>
                    @endif
                    @if (! empty($config['gibs']['ndvi']))
                        <label class="check"><input type="checkbox" name="ndvi"> <span>{{ __('map.ndvi') }}</span></label>
                    @endif
                    <div class="field field--inline">
                        <label for="map-date">{{ __('map.date') }}</label>
                        <input type="date" id="map-date" name="date" value="{{ $config['imageDate'] }}" max="{{ now('UTC')->toDateString() }}" min="2012-01-19">
                    </div>
                </fieldset>
            </form>

            <form class="map-form" data-aoi>
                <fieldset>
                    <legend class="meta">{{ __('map.aoi') }}</legend>
                    @if ($isMoz)
                        <p class="small muted">{{ __('map.aoi_help') }}</p>
                        <div class="field">
                            <label for="aoi-prov">{{ __('map.aoi_province') }}</label>
                            <select id="aoi-prov" name="province">
                                <option value="">{{ __('map.aoi_none') }}</option>
                                @foreach ($provinceNames as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="aoi-dist">{{ __('map.aoi_district') }}</label>
                            <select id="aoi-dist" name="district" disabled>
                                <option value="">{{ __('map.aoi_none') }}</option>
                            </select>
                        </div>
                    @else
                        <p class="small muted">{{ __('map.aoi_help_world') }}</p>
                    @endif
                    <div class="aoi-draw">
                        @unless ($isMoz)<button type="button" class="btn btn--small" data-aoi-country @disabled(! $config['outline'])>{{ __('map.aoi_country') }}</button>@endunless
                        <button type="button" class="btn btn--small" data-draw>{{ __('map.aoi_draw') }}</button>
                        <button type="button" class="btn btn--small" data-draw-finish hidden>{{ __('map.aoi_finish') }}</button>
                        <button type="button" class="btn btn--small" data-draw-cancel hidden>{{ __('map.aoi_cancel') }}</button>
                        <button type="button" class="link-btn small" data-aoi-clear hidden>{{ __('map.aoi_clear') }}</button>
                    </div>
                    <p class="small muted" data-draw-help hidden>{{ __('map.aoi_draw_help') }}</p>
                    <div class="field">
                        <label for="aoi-days">{{ __('map.aoi_days') }}</label>
                        <select id="aoi-days" name="days">
                            @foreach ([1, 2, 3, 5, 7] as $n)
                                <option value="{{ $n }}" @selected($n === 7)>{{ trans_choice('map.aoi_days_opt', $n, ['n' => $n]) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="aoi-result" data-aoi-result role="status" aria-live="polite">
                        <p class="small muted">{{ __('map.aoi_pick') }}</p>
                    </div>
                    <div class="aoi-download" data-aoi-download hidden>
                        <button type="button" class="btn btn--small" data-aoi-csv>{{ __('map.aoi_csv') }}</button>
                        <button type="button" class="btn btn--small" data-aoi-geojson>{{ __('map.aoi_geojson') }}</button>
                    </div>
                </fieldset>
            </form>
        </aside>
    </section>

    <script type="application/json" id="map-config" nonce="{{ $nonce }}">{!! json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <div class="section section--tight">
        <div class="wrap grid">
            <section class="span-6" aria-labelledby="by-prov">
                @if (! $isMoz)
                    <h2 id="by-prov" class="h3">{{ $countryName }}</h2>
                    <p>{{ __('map.country_stats', ['country' => $countryName, 'n24' => format_number($stats['n24'] ?? 0), 'n7' => format_number($stats['n7'] ?? 0)]) }}</p>
                    <p><a class="link-arrow" href="{{ lroute('countries.show', ['iso' => $isoLower]) }}">{{ __('map.kit_link', ['country' => $countryName]) }}</a></p>
                    @if (\Illuminate\Support\Facades\Storage::disk('public')->exists(\App\Geo\World::firesPath($config['iso'], 'csv')))
                        <p><a class="btn btn--small" href="{{ asset('storage/'.\App\Geo\World::firesPath($config['iso'], 'csv')) }}" download data-track-download="focos_{{ $isoLower }}.csv">{{ __('map.download_country', ['country' => $countryName]) }}</a></p>
                    @endif
                @endif
                @if (! $isMoz && ! empty($world['countries']))
                    <div class="table-scroll">
                        <table class="table">
                            <caption class="small muted">{{ __('map.world_title') }} · {{ __('map.world_caption') }}</caption>
                            <thead><tr><th scope="col">{{ __('map.col_country') }}</th><th scope="col" class="num">{{ __('map.col_24h') }}</th><th scope="col" class="num">{{ __('map.col_7d') }}</th></tr></thead>
                            <tbody>
                                @foreach (array_slice($world['countries'], 0, 15, true) as $wIso => $s)
                                    @php $wc = \App\Geo\World::find($wIso); @endphp
                                    @continue(! $wc)
                                    <tr><th scope="row"><a href="{{ lroute('maps') }}?pais={{ strtolower($wIso) }}">{{ \App\Geo\World::name($wc) }}</a></th><td class="num">{{ format_number($s['n24']) }}</td><td class="num">{{ format_number($s['n7']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif ($isMoz)
                <h2 class="h3">{{ __('map.table_title') }}</h2>
                @endif
                @if ($isMoz && $summary)
                    <div class="table-scroll">
                        <table class="table">
                            <caption class="small muted">{{ __('map.table_caption') }} {{ __('map.updated', ['date' => format_datetime($summary['updated_at'])]) }}</caption>
                            <thead><tr><th scope="col">{{ __('map.col_province') }}</th><th scope="col" class="num">{{ __('map.col_24h') }}</th><th scope="col" class="num">{{ __('map.col_7d') }}</th></tr></thead>
                            <tbody>
                                @foreach ($summary['by_province_7d'] as $prov => $n7)
                                    <tr><th scope="row">{{ $prov }}</th><td class="num">{{ format_number($summary['by_province_24h'][$prov] ?? 0) }}</td><td class="num">{{ format_number($n7) }}</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr><th scope="row">{{ __('map.total') }}</th><td class="num">{{ format_number($summary['count_24h']) }}</td><td class="num">{{ format_number($summary['count_7d']) }}</td></tr></tfoot>
                        </table>
                    </div>
                @elseif ($isMoz || empty($world['countries']))
                    <p class="muted">{{ __('map.no_data') }}</p>
                @endif
            </section>
            <section class="span-5 start-8" aria-labelledby="about-data">
                <h2 id="about-data" class="h3">{{ __('map.about_title') }}</h2>
                <p>{{ __('map.about_fires') }}</p>
                <p>{{ __('map.about_imagery') }}</p>
                @if ($isMoz)
                    <p><a class="link-arrow" href="{{ lroute('countries.show', ['iso' => 'moz']) }}">{{ __('map.kit_link', ['country' => $countryName]) }}</a></p>
                @endif
                @if ($fireDataset)
                    <p><a class="link-arrow" href="{{ $fireDataset->url() }}">{{ __('map.history') }}</a></p>
                @endif
                <p class="small muted" lang="en">We acknowledge the use of imagery provided by services from NASA's Global Imagery Browse Services (GIBS), part of NASA's Earth Science Data and Information System (ESDIS). We acknowledge the use of imagery from the NASA LANCE FIRMS (https://earthdata.nasa.gov/firms), part of the NASA Earth Science Data and Information System (ESDIS). Basemap: OpenFreeMap © OpenMapTiles Data from OpenStreetMap.</p>
            </section>
        </div>
    </div>
</x-layout>
