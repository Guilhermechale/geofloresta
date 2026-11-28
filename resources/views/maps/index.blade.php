@php
    use App\Support\Breadcrumbs;
    $toolTitle = __('map.tools.'.$tool.'.title');
    $crumbs = Breadcrumbs::make([['label' => __('map.nav'), 'url' => lroute('maps')], ['label' => $toolTitle, 'url' => \App\Support\MapTools::url($tool)]]);
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
    $provinceNames = $isMoz ? collect(json_decode((string) \Illuminate\Support\Facades\Storage::disk('public')->get('geo/mz-provincias.geojson'), true)['features'] ?? [])
        ->pluck('properties.nome')->sort()->values() : collect();
    $isoLower = strtolower($config['iso']);
    $pageTitle = $isMoz && ! request()->has('pais') ? $toolTitle : $toolTitle.' · '.$countryName;
    $on = fn (string $layer) => \App\Support\MapTools::on($tool, $layer);
@endphp
<x-layout :title="$pageTitle" :description="__('map.tools.'.$tool.'.text')" :noindex="request()->has('pais') && $isMoz" :jsonld="array_merge([Breadcrumbs::jsonld($crumbs)], \App\Support\Schema::mapTool($tool))">
    <x-slot:head>
        @vite(['resources/css/map.css', 'resources/js/map.js'])
        <link rel="preconnect" href="https://tiles.openfreemap.org" crossorigin>
        <link rel="preconnect" href="https://gibs.earthdata.nasa.gov" crossorigin>
        <link rel="preconnect" href="https://elevation-tiles-prod.s3.amazonaws.com" crossorigin>
    </x-slot:head>

    <x-page-head :title="$toolTitle" :lede="__('map.tools.'.$tool.'.text')" :crumbs="$crumbs" class="page-head--compact page-head--tool">
        @if ($tool === 'fires')
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
        @endif
    </x-page-head>

    <nav class="tool-tabs" aria-label="{{ __('map.tools_nav') }}">
        <div class="wrap tool-tabs__row">
            @foreach (\App\Support\MapTools::TOOLS as $k => $t)
                <a href="{{ \App\Support\MapTools::url($k, null, request()->filled('pais') ? ['pais' => $isoLower] : []) }}" @if ($k === $tool) aria-current="page" @endif>
                    <img src="{{ asset($t['image']) }}" width="48" height="30" alt="" loading="lazy">
                    <span class="tool-tabs__full">{{ __('map.tools.'.$k.'.title') }}</span>
                    <span class="tool-tabs__short" aria-hidden="true">{{ __('map.tools_short.'.$k) }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    <section class="map-shell" data-tool="{{ $tool }}" aria-label="{{ __('map.map_label') }}">
        <div class="map-canvas" id="map" data-map role="region" aria-label="{{ __('map.map_label_country', ['country' => $countryName]) }}" data-no-webgl="{{ __('map.no_webgl') }}">
            <p class="map-loading">{{ __('map.loading') }}</p>
            <noscript><p class="map-loading">{{ __('map.no_webgl') }}</p></noscript>
        </div>

        <div class="map-resizer" data-map-resizer role="separator" aria-orientation="vertical" tabindex="0" aria-controls="map-panel"
             aria-label="{{ __('map.resize') }}" title="{{ __('map.resize') }}">
            <button type="button" class="map-resizer__toggle" data-map-panel-toggle aria-controls="map-panel" aria-expanded="true"
                    data-label-hide="{{ __('map.panel_hide') }}" data-label-show="{{ __('map.panel_show') }}" aria-label="{{ __('map.panel_hide') }}" title="{{ __('map.panel_hide') }}">
                <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M6 3l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>

        <aside class="map-panel" id="map-panel" aria-label="{{ __('map.layers') }}">
            @if ($tool === 'lulc')<p class="panel-intro">{{ __('map.panel.lulc_intro') }}</p>@endif
            @if ($tool === 'deforestation')<p class="panel-intro">{{ __('map.dist.intro') }}</p>@endif
            <details class="howto" @unless (in_array($tool, ['lulc', 'deforestation'], true)) open @endunless>
                <summary class="howto__title">{{ __('map.howto_title') }}</summary>
                <ol>
                    @foreach (__('map.howto.'.$tool) as $step)<li>{{ $step }}</li>@endforeach
                </ol>
                @if ($tool === 'lulc' && config('geofloresta.lulc_video.id'))<p class="howto__video"><a class="link-arrow" href="#video">{{ __('map.video.link') }}</a></p>@endif
            </details>
            @if ($countries)
                <form class="map-form map-country" method="get" action="{{ \App\Support\MapTools::url($tool) }}" data-country>
                    <label for="map-country" class="meta">{{ __('map.country') }}</label>
                    <div class="map-country__row">
                        <select id="map-country" name="pais">
                            @foreach ($countries as $c)
                                <option value="{{ strtolower($c['iso']) }}" @selected($c['iso'] === $config['iso'])>{{ \App\Geo\World::name($c) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn--small">{{ __('map.country_go') }}</button>
                    </div>
                    @if ($tool === 'fires')<p class="small muted">{{ __('map.country_help') }}</p>@endif
                </form>
            @endif
            <form class="map-form" data-map-layers>
                @if (in_array($tool, ['lulc', 'deforestation'], true))<details class="panel-fold"><summary>{{ __('map.panel.layers_fold') }}</summary>@endif
                <fieldset>
                    <legend class="meta">{{ __('map.layers') }}</legend>
                    @php
                        $items = [];
                        if ($tool === 'fires') {
                            $items['fires'] = '<label class="check"><input type="checkbox" name="fires" checked> <span>'.e(__('map.fires')).'</span></label>'
                                .'<ul class="legend" aria-hidden="true"><li><span class="sw sw--24"></span>'.e(__('map.fires_age.24')).'</li><li><span class="sw sw--48"></span>'.e(__('map.fires_age.48')).'</li><li><span class="sw sw--168"></span>'.e(__('map.fires_age.168')).'</li></ul>';
                        }
                        if (! empty($config['gibs']['truecolor'])) {
                            $items['truecolor'] = '<label class="check"><input type="checkbox" name="truecolor"'.($on('truecolor') ? ' checked' : '').'> <span>'.e(__('map.truecolor')).'</span></label>';
                        }
                        if (! empty($config['gibs']['ndvi'])) {
                            $items['ndvi'] = '<label class="check"><input type="checkbox" name="ndvi"'.($on('ndvi') ? ' checked' : '').'> <span>'.e(__('map.ndvi')).'</span></label>';
                        }
                        $items['hillshade'] = '<label class="check"><input type="checkbox" name="hillshade"'.($on('hillshade') ? ' checked' : '').'> <span>'.e(__('map.relief')).'</span></label>';
                        $items['relief'] = '<label class="check"><input type="checkbox" name="relief"'.($on('relief') ? ' checked' : '').'> <span>'.e(__('map.relief_color')).'</span></label>'
                            .'<p class="relief-legend small" aria-hidden="true"><span>'.e(__('map.relief_low')).'</span><span class="relief-legend__bar"></span><span>'.e(__('map.relief_high')).'</span></p>';
                        $items['boundaries'] = $isMoz
                            ? '<label class="check"><input type="checkbox" name="provinces" checked> <span>'.e(__('map.provinces')).'</span></label><label class="check"><input type="checkbox" name="districts"> <span>'.e(__('map.districts')).'</span></label>'
                            : '<label class="check"><input type="checkbox" name="outline" checked> <span>'.e(__('map.outline')).'</span></label>';
                        $items['date'] = '<div class="field field--inline"><label for="map-date">'.e(__('map.date')).'</label><input type="date" id="map-date" name="date" value="'.e($config['imageDate']).'" max="'.now('UTC')->toDateString().'" min="2012-01-19"></div>';
                        $main = collect($items)->filter(fn ($v, $k) => $k === 'fires' || $on($k) || ($k === 'date' && $tool === 'satellite'));
                        $rest = collect($items)->diffKeys($main);
                    @endphp
                    {!! $main->implode('') !!}
                    @if ($rest->isNotEmpty())
                        <details class="more-layers">
                            <summary class="small">{{ __('map.more_layers') }}</summary>
                            {!! $rest->implode('') !!}
                        </details>
                    @endif
                </fieldset>
                @if (in_array($tool, ['lulc', 'deforestation'], true))</details>@endif
            </form>

            <form class="map-form" data-aoi data-only="fires lulc relief deforestation">
                <fieldset class="@if (in_array($tool, ['lulc', 'deforestation'], true)) panel-step @endif">
                    @if (in_array($tool, ['lulc', 'deforestation'], true))
                        <legend class="panel-step__title"><span class="panel-step__n">1</span>{{ __('map.panel.step_area') }}</legend>
                    @else
                        <legend class="meta">{{ __('map.aoi') }}</legend>
                    @endif
                    @if ($isMoz)
                        <p class="small muted">{{ in_array($tool, ['lulc', 'deforestation'], true) ? __('map.panel.aoi_hint_lulc') : __('map.aoi_help') }}</p>
                        <div class="aoi-selects">
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
                        </div>
                    @else
                        <p class="small muted">{{ $tool === 'fires' ? __('map.aoi_help_world') : __('map.aoi_help_world_generic') }}</p>
                    @endif
                    <div class="aoi-draw">
                        @unless ($isMoz)<button type="button" class="btn btn--small" data-aoi-country @disabled(! $config['outline'])>{{ __('map.aoi_country') }}</button>@endunless
                        <button type="button" class="btn btn--small" data-draw>{{ __('map.aoi_draw') }}</button>
                        <button type="button" class="btn btn--small" data-draw-finish hidden>{{ __('map.aoi_finish') }}</button>
                        <button type="button" class="btn btn--small" data-draw-cancel hidden>{{ __('map.aoi_cancel') }}</button>
                        <button type="button" class="link-btn small" data-aoi-clear hidden>{{ __('map.aoi_clear') }}</button>
                    </div>
                    <p class="small muted" data-draw-help hidden>{{ __('map.aoi_draw_help') }}</p>
                    <div class="field" data-only="fires">
                        <label for="aoi-days">{{ __('map.aoi_days') }}</label>
                        <select id="aoi-days" name="days">
                            @foreach ([1, 2, 3, 5, 7] as $n)
                                <option value="{{ $n }}" @selected($n === 7)>{{ trans_choice('map.aoi_days_opt', $n, ['n' => $n]) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="aoi-result" data-only="fires" data-aoi-result role="status" aria-live="polite">
                        <p class="small muted">{{ __('map.aoi_pick') }}</p>
                    </div>
                    <div class="aoi-download" data-only="fires" data-aoi-download hidden>
                        <button type="button" class="btn btn--go" data-aoi-csv>{{ __('map.aoi_csv') }}</button>
                        <button type="button" class="btn btn--go" data-aoi-geojson>{{ __('map.aoi_geojson') }}</button>
                    </div>
                </fieldset>
                <fieldset class="aoi-dem" data-only="relief" data-dem hidden>
                    <legend class="meta">{{ __('map.dem_title') }}</legend>
                    <p class="small muted">{{ __('map.dem_help') }}</p>
                    <button type="button" class="btn btn--primary" data-dem-build>{{ __('map.dem_build') }}</button>
                    <div class="small" data-dem-status role="status" aria-live="polite"></div>
                    <div data-dem-ready hidden>
                        <div class="field">
                            <label for="dem-interval">{{ __('map.dem_interval') }}</label>
                            <select id="dem-interval" name="interval">
                                @foreach ([10, 20, 50, 100, 200, 500] as $m)<option value="{{ $m }}">{{ $m }} m</option>@endforeach
                            </select>
                        </div>
                        <div class="aoi-download">
                            <button type="button" class="btn btn--go" data-dem-tiff>{{ __('map.dem_tiff') }}</button>
                            <button type="button" class="btn btn--go" data-dem-contours>{{ __('map.dem_contours') }}</button>
                        </div>
                    </div>
                    <p class="small muted">{{ __('map.dem_note') }}</p>
                    <p class="panel-links"><a class="link-arrow" href="{{ lroute('contact') }}?assunto=data">{{ __('map.dem_more') }}</a></p>
                </fieldset>
                <div class="aoi-lulc" data-only="lulc" data-lulc>
                    <p class="lulc-area" data-lulc-area role="status" aria-live="polite">{{ __('map.area_none') }}</p>

                    <fieldset class="panel-step">
                        <legend class="panel-step__title"><span class="panel-step__n">2</span>{{ __('map.panel.step_params') }}</legend>
                        <div class="lulc-fields">
                            <div class="field">
                                <label for="lulc-start">{{ __('map.lulc_start') }}</label>
                                <input type="date" id="lulc-start" name="lulc_start" value="2025-01-01" min="2017-04-01">
                            </div>
                            <div class="field">
                                <label for="lulc-end">{{ __('map.lulc_end') }}</label>
                                <input type="date" id="lulc-end" name="lulc_end" value="2025-12-31" min="2017-04-01">
                            </div>
                            <div class="field lulc-fields__trees">
                                <label for="lulc-trees">{{ __('map.lulc_trees') }}</label>
                                <select id="lulc-trees" name="lulc_trees" aria-describedby="lulc-trees-hint">
                                    @foreach ([50, 100, 200, 300, 400, 500] as $n)<option value="{{ $n }}" @selected($n === 100)>{{ $n }}</option>@endforeach
                                </select>
                                <p class="small muted lulc-hint" id="lulc-trees-hint">{{ __('map.lulc_trees_hint') }}</p>
                            </div>
                        </div>
                    </fieldset>

                    <div class="panel-step panel-step--run">
                        <button type="button" class="btn btn--primary btn--block" data-lulc-run disabled>{{ __('map.lulc_run') }}</button>
                        <details class="lulc-warning" role="note">
                            <summary><strong>{{ __('map.lulc_warning') }}</strong> <span class="lulc-warning__more">{{ __('map.panel.lulc_why') }}</span></summary>
                            <p>{{ __('map.lulc_warning_text') }}</p>
                        </details>
                        <div class="small" data-lulc-status role="status" aria-live="polite"></div>
                        <progress data-lulc-progress max="1" value="0" hidden></progress>
                    </div>

                    <section class="lulc-card" data-lulc-download hidden aria-labelledby="lulc-result-title">
                        <h3 class="panel-step__title" id="lulc-result-title"><span class="panel-step__n panel-step__n--ok">✓</span>{{ __('map.panel.step_result') }}</h3>
                        <div data-lulc-result hidden></div>
                        <div class="lulc-done__btns">
                            <button type="button" class="btn btn--go" data-lulc-tiff>{{ __('map.lulc_tiff') }}</button>
                            <button type="button" class="btn btn--go" data-lulc-csv>{{ __('map.lulc_csv') }}</button>
                        </div>
                        <label class="lulc-toggle"><input type="checkbox" data-lulc-show checked> <span>{{ __('map.lulc_show') }}</span></label>
                        <div data-lulc-extra></div>
                    </section>

                    <nav class="panel-cards" aria-label="{{ __('map.panel.learn_title') }}">
                        @if ($rfTutorialUrl)
                            <a class="panel-card" href="{{ $rfTutorialUrl }}"><span class="panel-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/><path d="M9 7h6M9 11h6"/></svg></span><span><strong>{{ __('map.panel.learn_title') }}</strong><span class="small muted">{{ __('map.panel.learn_sub') }}</span></span></a>
                        @endif
                        <a class="panel-card panel-card--accent" href="{{ lroute('contact') }}?assunto=proposal"><span class="panel-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg></span><span><strong>{{ __('map.panel.more_title') }}</strong><span class="small muted">{{ __('map.panel.more_sub') }}</span></span></a>
                    </nav>

                    <details class="panel-fold panel-fold--small">
                        <summary>{{ __('map.panel.lulc_sources') }}</summary>
                        <p class="small muted">{{ __('map.lulc_note') }}</p>
                    </details>
                </div>
                <div class="aoi-lulc" data-only="deforestation" data-dist>
                    <p class="lulc-area" data-dist-area role="status" aria-live="polite">{{ __('map.area_none') }}</p>

                    <fieldset class="panel-step">
                        <legend class="panel-step__title"><span class="panel-step__n">2</span>{{ __('map.dist.step_params') }}</legend>
                        <div class="lulc-fields">
                            <div class="field">
                                <label for="dist-period">{{ __('map.dist.period') }}</label>
                                <select id="dist-period" name="dist_period">
                                    <option value="30">{{ __('map.dist.last30') }}</option>
                                    <option value="90" selected>{{ __('map.dist.last90') }}</option>
                                    <option value="180">{{ __('map.dist.last180') }}</option>
                                    <option value="year">{{ __('map.dist.year') }}</option>
                                </select>
                            </div>
                            <div class="field" data-dist-year hidden>
                                <label for="dist-year">{{ __('map.dist.year_label') }}</label>
                                <select id="dist-year" name="dist_year">
                                    @for ($y = (int) now()->year; $y >= 2019; $y--)<option value="{{ $y }}" @selected($y === (int) now()->year - 1)>{{ $y }}</option>@endfor
                                </select>
                            </div>
                        </div>
                        <fieldset class="dist-choice">
                            <legend class="small">{{ __('map.dist.level') }}</legend>
                            <label class="check"><input type="radio" name="dist_level" value="conf" checked> <span>{{ __('map.dist.level_conf') }}</span></label>
                            <label class="check"><input type="radio" name="dist_level" value="all"> <span>{{ __('map.dist.level_all') }}</span></label>
                        </fieldset>
                        <fieldset class="dist-choice">
                            <legend class="small">{{ __('map.dist.sources') }}</legend>
                            <label class="check"><input type="checkbox" name="dist_optical" checked> <span>{{ __('map.dist.optical') }}</span></label>
                            <label class="check"><input type="checkbox" name="dist_radar" checked> <span>{{ __('map.dist.radar') }}</span></label>
                        </fieldset>
                    </fieldset>

                    <div class="panel-step panel-step--run">
                        <button type="button" class="btn btn--primary btn--block" data-dist-run disabled>{{ __('map.dist.run') }}</button>
                        <details class="lulc-warning" role="note">
                            <summary><strong>{{ __('map.dist.warning') }}</strong> <span class="lulc-warning__more">{{ __('map.dist.why') }}</span></summary>
                            <p>{{ __('map.dist.warning_text') }}</p>
                        </details>
                        <div class="small" data-dist-status role="status" aria-live="polite"></div>
                        <progress data-dist-progress max="1" value="0" hidden></progress>
                    </div>

                    <section class="lulc-card" data-dist-card hidden aria-labelledby="dist-result-title">
                        <h3 class="panel-step__title" id="dist-result-title"><span class="panel-step__n panel-step__n--ok">✓</span>{{ __('map.panel.step_result') }}</h3>
                        <div data-dist-result></div>
                        <ul class="dist-legend small" aria-hidden="true"><li><span class="sw sw--conf"></span>{{ __('map.dist.legend_conf') }}</li><li><span class="sw sw--prov"></span>{{ __('map.dist.legend_prov') }}</li><li data-dist-hansen-legend hidden><span class="sw sw--hansen"></span>Hansen 2021-2025</li></ul>
                        <label class="lulc-toggle"><input type="checkbox" data-dist-show checked> <span>{{ __('map.dist.show') }}</span></label>
                        <div class="dist-btns">
                            <button type="button" class="btn btn--go btn--block" data-dist-zip>{{ __('map.dist.package') }}</button>
                            <button type="button" class="btn btn--small btn--block" data-dist-geojson>{{ __('map.dist.geojson') }}</button>
                        </div>
                        <p class="small muted">{{ __('map.dist.package_hint') }}</p>
                        <div class="dist-hansen" data-dist-hansen>
                            <button type="button" class="btn btn--small" data-dist-hansen-run>{{ __('map.dist.hansen_btn') }}</button>
                            <div data-dist-hansen-result></div>
                            <label class="lulc-toggle" data-dist-hansen-toggle hidden><input type="checkbox" data-dist-hansen-show checked> <span>{{ __('map.dist.hansen_show') }}</span></label>
                        </div>
                        <div data-dist-extra></div>
                    </section>

                    <nav class="panel-cards" aria-label="{{ __('map.panel.more_title') }}">
                        <a class="panel-card panel-card--accent" href="{{ lroute('contact') }}?assunto=proposal"><span class="panel-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg></span><span><strong>{{ __('map.panel.more_title') }}</strong><span class="small muted">{{ __('map.panel.more_sub') }}</span></span></a>
                    </nav>

                    <details class="panel-fold panel-fold--small">
                        <summary>{{ __('map.dist.method') }}</summary>
                        <p class="small muted">{{ __('map.dist.method_text') }}</p>
                    </details>
                    <details class="panel-fold panel-fold--small">
                        <summary>{{ __('map.dist.sources_fold') }}</summary>
                        <p class="small muted">{{ __('map.dist.sources_text') }}</p>
                    </details>
                </div>
            </form>
        </aside>
    </section>

    <script type="application/json" id="map-config" nonce="{{ $nonce }}">{!! json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    @if ($tool === 'fires')
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
                <p>{{ __('map.about_relief') }}</p>
                @if ($isMoz)
                    <p><a class="link-arrow" href="{{ lroute('countries.show', ['iso' => 'moz']) }}">{{ __('map.kit_link', ['country' => $countryName]) }}</a></p>
                @endif
                @if ($fireDataset)
                    <p><a class="link-arrow" href="{{ $fireDataset->url() }}">{{ __('map.history') }}</a></p>
                @endif
            </section>
        </div>
    </div>
    @else
    @if ($tool === 'lulc' && config('geofloresta.lulc_video.id'))
        <x-tool-video :id="config('geofloresta.lulc_video.id')" cover="img/video-uso-do-solo.webp" />
    @endif
    @php
        $learn = ['lulc' => $rfTutorialUrl, 'relief' => \App\Models\Tutorial::query()->published()->where('slug->pt', 'curvas-de-nivel-da-sua-area-em-2-minutos-gratis')->first()?->url()];
    @endphp
    @if (! empty($learn[$tool]))
    <div class="section section--tight">
        <div class="wrap"><p><a class="link-arrow" href="{{ $learn[$tool] }}">{{ $tool === 'lulc' ? __('map.lulc_learn') : __('map.tutorial_link') }}</a></p></div>
    </div>
    @endif
    @endif
    <x-service-cta compact />
</x-layout>
