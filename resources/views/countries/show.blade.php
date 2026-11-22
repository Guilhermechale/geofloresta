@php
    use App\Support\Breadcrumbs;
    $iso = strtolower($c['iso']);
    $crumbs = Breadcrumbs::make([
        ['label' => __('data.title'), 'url' => lroute('data')],
        ['label' => __('countries.title'), 'url' => lroute('countries')],
        ['label' => $name, 'url' => lroute('countries.show', ['iso' => $iso])],
    ]);
    $stats = collect($sections)->firstWhere('key', 'fires')['stats'] ?? null;

    // Resumo próprio do país: localização, focos (com posição entre países), unidades administrativas e bacias
    $deg = function (float $v, string $pos, string $neg) { return str_replace('.', app()->getLocale() === 'en' ? '.' : ',', number_format(abs($v), 1)).'° '.__('countryguide.dir.'.($v >= 0 ? $pos : $neg)); };
    $ranking = array_keys(\App\Geo\World::fires()['countries'] ?? []);
    $rankPos = array_search($c['iso'], $ranking, true);
    $units = collect(collect($sections)->firstWhere('key', 'boundaries')['items'] ?? [])->filter(fn ($i) => ! empty($i['units']))->mapWithKeys(fn ($i) => [$i['level'] => (int) $i['units']]);
    $hydro = collect(\App\Geo\World::hydroRegions($c))->map(fn ($r) => __('countries.hydro.'.$r))->implode(' '.__('countryguide.and').' ');
    $overview = [__('countryguide.location', [
        'country' => $name, 'in_continent' => __('countryguide.in_continent.'.$c['continent']),
        'lat0' => $deg($c['bbox'][1], 'N', 'S'), 'lat1' => $deg($c['bbox'][3], 'N', 'S'), 'lon0' => $deg($c['bbox'][0], 'E', 'W'), 'lon1' => $deg($c['bbox'][2], 'E', 'W'),
    ])];
    $overview[] = ($stats['n7'] ?? 0) > 0 && $rankPos !== false
        ? __('countryguide.fires_some', ['country' => $name, 'n7' => format_number($stats['n7']), 'n24' => format_number($stats['n24'] ?? 0), 'rank' => $rankPos + 1, 'total' => count($ranking)])
        : __('countryguide.fires_none', ['country' => $name]);
    if (($units['ADM1'] ?? 0) > 1) {
        $overview[] = ($units['ADM2'] ?? 0) > 1
            ? __('countryguide.units_both', ['adm1' => format_number($units['ADM1']), 'adm2' => format_number($units['ADM2'])])
            : __('countryguide.units_one', ['adm1' => format_number($units['ADM1'])]);
    }
    if ($hydro !== '') {
        $overview[] = __('countryguide.water', ['regions' => $hydro]);
    }
    [$x0, $y0, $x1, $y1] = $c['bbox'];
    $ld = [
        '@context' => 'https://schema.org', '@type' => 'DataCatalog',
        'name' => __('countries.kit_title', ['country' => $name]),
        'description' => __('countries.show_description', ['country' => $name]),
        'url' => lroute('countries.show', ['iso' => $iso]), 'inLanguage' => app()->getLocale(),
        'publisher' => ['@type' => 'Organization', 'name' => 'GEOFLORESTA', 'url' => url('/')],
        'spatialCoverage' => ['@type' => 'Place', 'name' => $c['names']['en'] ?? $name, 'geo' => ['@type' => 'GeoShape', 'box' => round($y0, 3).' '.round($x0, 3).' '.round($y1, 3).' '.round($x1, 3)]],
    ];
@endphp
<x-layout :title="__('countries.kit_title', ['country' => $name])" :description="__('countries.show_description', ['country' => $name])" :jsonld="[Breadcrumbs::jsonld($crumbs), $ld]">
    <x-page-head :title="__('countries.kit_title', ['country' => $name])" :crumbs="$crumbs" :meta="__('countries.continents.'.$c['continent']).' · ISO '.$c['iso']">
        <p class="lede">{{ __('countries.show_lede', ['country' => $name]) }}</p>
    </x-page-head>

    <div class="section section--tight">
        <div class="wrap grid">
            <article class="span-8">
                <section class="kit kit--overview" aria-labelledby="h-overview">
                    <h2 class="h3" id="h-overview">{{ __('countryguide.title') }}</h2>
                    <p>{{ implode(' ', $overview) }}</p>
                    <p>{{ __('countryguide.service', ['country' => $name]) }} <a class="link-arrow" href="{{ lroute('contact') }}?assunto=data">{{ __('countryguide.service_link') }}</a></p>
                </section>

                <nav class="toc-inline" aria-label="{{ __('countries.sections_nav') }}">
                    @foreach ($sections as $s)
                        <a href="#{{ $s['key'] }}">{{ __('countries.sections.'.$s['key']) }}</a>
                    @endforeach
                </nav>

                @foreach ($sections as $s)
                    <section class="kit" id="{{ $s['key'] }}" aria-labelledby="h-{{ $s['key'] }}">
                        <h2 class="h3" id="h-{{ $s['key'] }}">{{ __('countries.sections.'.$s['key']) }}</h2>
                        <p class="small muted">{{ __('countries.intro.'.$s['key'], ['country' => $name]) }}</p>

                        @if ($s['key'] === 'fires')
                            <div class="kit__stats">
                                <p><strong>{{ format_number($stats['n24'] ?? 0) }}</strong> <span>{{ __('countries.fires_24h') }}</span></p>
                                <p><strong>{{ format_number($stats['n7'] ?? 0) }}</strong> <span>{{ __('countries.fires_7d') }}</span></p>
                                <p><a class="btn" href="{{ lroute('maps') }}?pais={{ $iso }}">{{ __('countries.see_map') }}</a></p>
                            </div>
                            @if ($firesUpdated)<p class="coord">{{ __('countries.updated', ['date' => format_datetime($firesUpdated)]) }}</p>@endif
                        @endif

                        <ul class="kit__list">
                            @foreach ($s['items'] as $it)
                                <li class="kit__item">
                                    <div class="kit__body">
                                        <p class="kit__title">
                                            {{ __('countries.items.'.$it['label']) }}
                                            @isset($it['level']) <span class="badge">{{ __('countries.levels.'.$it['level']) }}</span>@endisset
                                            @isset($it['region']) <span class="badge">{{ __('countries.hydro.'.$it['region']) }}</span>@endisset
                                        </p>
                                        <p class="coord">
                                            {{ $it['format'] }}
                                            @if (! empty($it['size'])) · {{ format_bytes((int) $it['size']) }}@endif
                                            @if (! empty($it['units'])) · {{ trans_choice('countries.units', (int) $it['units'], ['n' => format_number((int) $it['units'])]) }}@endif
                                            @if (! empty($it['year'])) · {{ $it['year'] }}@endif
                                        </p>
                                        <p class="small muted">{{ $it['source'] }} · {{ __('countries.licence') }}: {{ $it['licence'] ?: '-' }}</p>
                                    </div>
                                    <a class="btn btn--small" href="{{ $it['url'] }}" @if (! empty($it['download'])) download data-track-download="{{ basename(parse_url($it['url'], PHP_URL_PATH)) }}" @else rel="noopener" target="_blank" @endif>
                                        {{ ! empty($it['download']) || preg_match('/\.(zip|xlsx|csv|tif|geojson|json)(\?|$)/i', $it['url']) ? __('data.download') : __('countries.open_source') }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach

                @if ($local->isNotEmpty())
                    <section class="kit" id="geofloresta" aria-labelledby="h-local">
                        <h2 class="h3" id="h-local">{{ __('countries.local_title') }}</h2>
                        <ul class="catalogue">
                            @foreach ($local as $d)
                                <li class="catalogue__item">
                                    <p class="meta">{{ __('data.themes.'.$d->theme) }}</p>
                                    <h3 class="catalogue__title"><a href="{{ $d->url() }}">{{ $d->getTranslation('title', $d->displayLocale(app()->getLocale())) }}</a></h3>
                                </li>
                            @endforeach
                        </ul>
                        <p><a class="link-arrow" href="{{ lroute('data') }}">{{ __('countries.local_all') }}</a></p>
                    </section>
                @endif

                <div class="notice notice--warn">
                    <p class="small">{{ __('countries.disclaimer') }}</p>
                </div>
            </article>

            <aside class="span-4 start-9">
                <div class="specimen">
                    <h2 class="specimen__title">{{ $name }}</h2>
                    <dl>
                        <dt>ISO 3166-1</dt><dd>{{ $c['iso'] }}@if (! empty($c['iso2'])) / {{ $c['iso2'] }}@endif</dd>
                        <dt>{{ __('countries.continent') }}</dt><dd>{{ __('countries.continents.'.$c['continent']) }}</dd>
                        <dt>{{ __('countries.bbox') }}</dt><dd class="coord">{{ round($x0, 2) }}, {{ round($y0, 2) }} · {{ round($x1, 2) }}, {{ round($y1, 2) }}</dd>
                        <dt>{{ __('data.fields.crs') }}</dt><dd>EPSG:4326 (WGS 84)</dd>
                    </dl>
                    <p class="small"><a class="link-arrow" href="{{ asset('storage/'.App\Geo\World::outlinePath($c['iso'])) }}" download>{{ __('countries.outline') }}</a></p>
                    <p class="coord specimen__foot">{{ __('countries.outline_note') }}</p>
                </div>
                <div class="notice">
                    <p>{{ __('countries.custom') }}</p>
                    <a class="link-arrow" href="{{ lroute('contact') }}?assunto=data">{{ __('data.custom_btn') }}</a>
                </div>
                <p><a class="link-arrow" href="{{ lroute('countries') }}">{{ __('countries.all') }}</a></p>
            </aside>
        </div>
    </div>
</x-layout>
