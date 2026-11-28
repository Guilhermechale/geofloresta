@php
    /** @var string $tool  @var array|null $sources  (estado da vigilância diária, App\Console\Commands\CheckSources::read()) */
    $I = __('map.insights');
    $T = $I[$tool];
    $loc = app()->getLocale();
    $ago = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->locale($loc)->diffForHumans() : null;
    $fresh = $sources && \Illuminate\Support\Carbon::parse($sources['checked_at'])->gt(now()->subHours(48));
    $state = ! $sources || ! $fresh ? 'unknown' : ($sources['ok'] ? 'ok' : 'degraded');
    $examples = config('geofloresta.map_examples.'.$tool, []);
    $toolUrl = \App\Support\MapTools::url($tool);
    $citation = str_replace([':year', ':url'], [now()->year, $toolUrl], $T['citation']);
    $icons = [
        '<path d="M4 7l8-4 8 4-8 4z"/><path d="M4 12l8 4 8-4"/><path d="M4 17l8 4 8-4"/>',
        '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
        '<path d="M3 12h4l3-7 4 14 3-7h4"/>',
        '<path d="M4 20h16"/><path d="M7 16V9M12 16V5M17 16v-4"/>',
    ];
@endphp
<section class="insights" aria-label="{{ $I['how_title'] }}">
    <div class="wrap">
        {{-- 1. Estado dos dados (vigilância diária) --}}
        @if ($sources)
            <div class="data-status data-status--{{ $state }}" role="status">
                <span class="data-status__state"><span class="data-status__dot" aria-hidden="true"></span>{{ $I[$state] }}</span>
                <ul class="data-status__list">
                    @if (! empty($sources['s2_latest']))<li><strong>{{ $I['s2'] }}</strong> {{ str_replace(':ago', $ago($sources['s2_latest']), $I['latest']) }}</li>@endif
                    @if ($tool === 'deforestation' && ! empty($sources['s1_latest']))<li><strong>{{ $I['s1'] }}</strong> {{ str_replace(':ago', $ago($sources['s1_latest']), $I['latest']) }}</li>@endif
                    @if ($tool === 'deforestation' && ! empty($sources['hansen']))<li><strong>{{ $I['hansen'] }}</strong> {{ str_replace([':version', ':year'], [$sources['hansen']['version'], $sources['hansen']['last_year']], $I['hansen_value']) }}</li>@endif
                    <li class="data-status__checked">{{ str_replace(':ago', $ago($sources['checked_at']), $I['checked']) }}</li>
                </ul>
                @if ($state === 'degraded')<p class="data-status__note">{{ $I['degraded_note'] }}</p>@endif
            </div>
        @endif

        {{-- 2. Exemplos prontos --}}
        @if ($examples)
            <div class="insights__head">
                <h2 class="insights__title">{{ $I['examples_title'] }}</h2>
                <p class="insights__sub">{{ $I['examples_sub'] }}</p>
            </div>
            <ul class="examples">
                @foreach ($examples as $ex)
                    @php [$title, $sub] = $T['examples'][$ex['key']]; @endphp
                    <li class="example">
                        <a class="example__link" href="{{ $toolUrl }}?bbox={{ $ex['bbox'] }}">
                            <img class="example__img" src="{{ asset($ex['image']) }}" width="640" height="400" alt="" loading="lazy" decoding="async">
                            <span class="example__body">
                                <span class="example__title">{{ $title }}</span>
                                <span class="example__sub">{{ $sub }}</span>
                                <span class="example__cta">{{ $I['open'] }} <span aria-hidden="true">→</span></span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- 3. Como funciona + números da validação --}}
        <div class="how">
            <div class="how__main">
                <h2 class="insights__title">{{ $I['how_title'] }}</h2>
                <ol class="how__steps">
                    @foreach ($T['steps'] as $k => [$st, $sd])
                        <li class="how__step">
                            <span class="how__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$k] !!}</svg></span>
                            <span class="how__n">{{ $k + 1 }}</span>
                            <strong class="how__t">{{ $st }}</strong>
                            <span class="how__d">{{ $sd }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
            <dl class="how__stats">
                @foreach ($T['stats'] as [$v, $l])
                    <div><dt>{{ $l }}</dt><dd>{{ $v }}</dd></div>
                @endforeach
            </dl>
        </div>

        {{-- 4. Para investigadores --}}
        <div class="research-box">
            <div class="research-box__intro">
                <h2 class="insights__title">{{ $I['res_title'] }}</h2>
                <p class="insights__sub">{{ $I['res_sub'] }}</p>
                <p class="research-box__label">{{ $I['files_title'] }}</p>
                <ul class="file-chips">
                    @foreach ($T['files'] as $f)<li>{{ $f }}</li>@endforeach
                </ul>
            </div>
            <div class="research-box__side">
                <details class="cite-fold" data-copy-label="{{ $I['copy'] }}" data-copied-label="{{ $I['copied'] }}">
                    <summary>{{ $I['cite'] }}</summary>
                    <p class="cite-fold__text" data-cite-text>{{ $citation }}</p>
                    <button type="button" class="btn btn--small" data-cite-copy>{{ $I['copy'] }}</button>
                </details>
                <ul class="research-links">
                    @if ($tool === 'deforestation' && ! empty($eudrUrl))<li><a class="link-arrow" href="{{ $eudrUrl }}">{{ $I['eudr'] }}</a></li>@endif
                    @if ($tool === 'lulc' && ! empty($rfTutorialUrl))<li><a class="link-arrow" href="{{ $rfTutorialUrl }}">{{ __('map.lulc_learn') }}</a></li>@endif
                    <li><a class="link-arrow" href="{{ lroute('licences') }}">{{ $I['licences'] }}</a></li>
                </ul>
            </div>
        </div>
    </div>
</section>
