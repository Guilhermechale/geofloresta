@php
    /**
     * Por baixo do mapa, em todas as ferramentas: estado das fontes, exemplos, especificações, dados e citação.
     * @var string $tool  @var array $status ['state' => ok|degraded|unknown, 'items' => [[rótulo, valor], ...]]  @var array $links [[rótulo, endereço], ...]
     */
    $P = __('map.portal');
    $examples = config('geofloresta.map_examples.'.$tool, []);
    $toolUrl = \App\Support\MapTools::url($tool);
    $creditKeys = ['deforestation' => ['sentinel-2-aws', 'sentinel-1-rtc', 'hansen'], 'lulc' => ['sentinel-2-aws', 'io-lulc'], 'fires' => ['firms'], 'ndvi' => ['gibs'], 'satellite' => ['gibs'], 'relief' => ['terrain-tiles']][$tool] ?? [];
    $credits = collect(config('sources'))->whereIn('key', $creditKeys)->pluck('attribution')->map(fn ($a) => str_replace('[year]', now()->year, $a))->implode(' ');
    $citation = str_replace([':year', ':title', ':url', ':date'], [now()->year, __('map.tools.'.$tool.'.title'), $toolUrl, now()->toDateString()], $P['cite_tpl']);
    $formats = ['deforestation' => 'GeoTIFF · GeoJSON · CSV · TXT', 'lulc' => 'GeoTIFF · CSV', 'fires' => 'CSV · GeoJSON', 'relief' => 'GeoTIFF · GeoJSON', 'ndvi' => 'WMTS', 'satellite' => 'WMTS'][$tool] ?? null;
    $deg = function (string $bbox) {
        [$w, $s, $e, $n] = array_map('floatval', explode(',', $bbox));
        $la = ($s + $n) / 2; $lo = ($w + $e) / 2;

        return sprintf('%.2f° %s · %.2f° %s', abs($la), $la < 0 ? 'S' : 'N', abs($lo), $lo < 0 ? 'W' : 'E');
    };
@endphp
<section class="portal" aria-label="{{ $P['specs'] }}">
    <div class="wrap">
        @if (! empty($status['items']))
            <div class="portal-status portal-status--{{ $status['state'] }}" role="status">
                <span class="portal-status__state"><span class="portal-status__dot" aria-hidden="true"></span>{{ $P[$status['state']] }}</span>
                <dl class="portal-status__list">
                    @foreach ($status['items'] as [$k, $v])<div><dt>{{ $k }}</dt><dd>{{ $v }}</dd></div>@endforeach
                </dl>
            </div>
        @endif

        @if ($examples)
            <h2 class="portal-title">{{ $P['examples'] }}</h2>
            <ul class="portal-examples">
                @foreach ($examples as $ex)
                    @php [$name, $region] = $P['ex'][$ex['key']]; @endphp
                    <li>
                        <a class="portal-ex" href="{{ $toolUrl }}?bbox={{ $ex['bbox'] }}">
                            <span class="portal-ex__media"><img src="{{ asset($ex['image']) }}" width="640" height="400" alt="" loading="lazy" decoding="async"></span>
                            <span class="portal-ex__body">
                                <span class="portal-ex__name">{{ $name }}</span>
                                <span class="portal-ex__meta">{{ $region }}</span>
                                <span class="portal-ex__coord">{{ $deg($ex['bbox']) }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="portal-cols">
            <div class="portal-card">
                <h2 class="portal-title">{{ $P['specs'] }}</h2>
                <dl class="portal-specs">
                    @foreach ($P['spec'][$tool] as [$k, $v])<div><dt>{{ $k }}</dt><dd>{{ $v }}</dd></div>@endforeach
                </dl>
            </div>
            <div class="portal-card">
                <h2 class="portal-title">{{ $P['data'] }}</h2>
                <dl class="portal-specs">
                    @if ($formats)<div><dt>{{ $P['formats'] }}</dt><dd>{{ $formats }}</dd></div>@endif
                    <div class="portal-specs__wide"><dt>{{ $P['cite'] }}</dt><dd>
                        <div class="portal-cite" data-copy-label="{{ $P['copy'] }}" data-copied-label="{{ $P['copied'] }}">
                            <p data-cite-text>{{ $citation }}@if ($credits)<span class="visually-hidden"> {{ $P['data_credit'] }}: {{ $credits }}</span>@endif</p>
                            <button type="button" class="portal-cite__btn" data-cite-copy>{{ $P['copy'] }}</button>
                        </div>
                    </dd></div>
                </dl>
                <ul class="portal-links">
                    @foreach ($links ?? [] as [$label, $href])<li><a href="{{ $href }}">{{ $label }}</a></li>@endforeach
                    <li><a href="{{ lroute('licences') }}">{{ $P['licences'] }}</a></li>
                </ul>
            </div>
        </div>
    </div>
</section>
