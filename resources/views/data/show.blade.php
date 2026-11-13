@php
    use App\Geo\DatasetPackager;
    use App\Geo\Geometry;
    use App\Models\Dataset;
    use App\Support\Breadcrumbs;
    use App\Support\SvgPreview;
    $cur = app()->getLocale();
    $fallback = $locale !== $cur;
    $title = $dataset->getTranslation('title', $locale);
    $summary = $dataset->getTranslation('summary', $locale, false);
    $crumbs = Breadcrumbs::make([['label' => __('data.title'), 'url' => lroute('data')], ['label' => $title, 'url' => $dataset->url()]]);
    [$licName, $licUrl] = DatasetPackager::LICENCE_NAMES[$dataset->licence_spdx] ?? [$dataset->licence_spdx, null];
    $licUrl = $dataset->licence_url ?: $licUrl;
    $preview = SvgPreview::render($dataset->preview_geojson, __('data.preview').': '.$title);
    $downloads = $dataset->files->sum('download_count');

    // JSON-LD schema.org/Dataset (Google Dataset Search)
    $ld = [
        '@context' => 'https://schema.org', '@type' => 'Dataset',
        'name' => $title, 'description' => strip_tags($summary.' '.$dataset->getTranslation('description', $locale, false)),
        'url' => $dataset->url(), 'inLanguage' => $locale, 'isAccessibleForFree' => true,
        'license' => $licUrl ?: $dataset->source_url,
        'creator' => ['@type' => 'Organization', 'name' => $dataset->source_name, 'url' => $dataset->source_url],
        'publisher' => ['@type' => 'Organization', 'name' => 'GEOFLORESTA', 'url' => url('/')],
        'isBasedOn' => $dataset->source_url,
        'citation' => $dataset->citation ?: $dataset->attribution,
        'keywords' => [__('data.themes.'.$dataset->theme), 'Moçambique', 'Mozambique', 'SIG', 'GIS'],
        'dateModified' => $dataset->updated_at?->toDateString(),
    ];
    if ($dataset->version) { $ld['version'] = $dataset->version; }
    if ($dataset->year_start) { $ld['temporalCoverage'] = $dataset->year_start.'-01-01/'.($dataset->year_end ?: $dataset->year_start).'-12-31'; }
    if ($dataset->preview_geojson && ($fc = json_decode($dataset->preview_geojson, true)) && ! empty($fc['features'])) {
        $bb = null;
        foreach ($fc['features'] as $f) { if (! empty($f['geometry'])) { $bb = Geometry::mergeBbox($bb, Geometry::bbox($f['geometry'])); } }
        if ($bb) { $ld['spatialCoverage'] = ['@type' => 'Place', 'geo' => ['@type' => 'GeoShape', 'box' => round($bb[1], 3).' '.round($bb[0], 3).' '.round($bb[3], 3).' '.round($bb[2], 3)]]; }
    } else {
        $ld['spatialCoverage'] = ['@type' => 'Place', 'name' => 'Mozambique'];
    }
    if (! $dataset->isExternal() && $dataset->files->isNotEmpty()) {
        $ld['distribution'] = $dataset->files->map(fn ($f) => [
            '@type' => 'DataDownload', 'encodingFormat' => ['cog' => 'image/tiff', 'geojson' => 'application/geo+json', 'gpkg' => 'application/geopackage+sqlite3', 'shp' => 'application/zip', 'csv' => 'text/csv'][$f->format] ?? 'application/zip',
            'contentUrl' => route('download', [$f->id, $f->filename]), 'contentSize' => $f->size_bytes.' B', 'name' => $f->filename,
        ])->values()->all();
    }
@endphp
<x-layout :title="$dataset->seoTitle()" :description="$dataset->seoDescription()" type="article" :jsonld="[Breadcrumbs::jsonld($crumbs), $ld]">
    <x-page-head :title="$title" :crumbs="$crumbs" :meta="__('data.themes.'.$dataset->theme).' · '.__('data.regions.'.$dataset->region)">
        @if ($summary)<p class="lede" @if ($fallback) lang="{{ $locale }}" @endif>{{ $summary }}</p>@endif
        @if ($dataset->isExternal())<p><span class="badge">{{ __('data.external') }}</span></p>@endif
    </x-page-head>

    <div class="section section--tight">
        <div class="wrap grid">
            <article class="span-7" @if ($fallback) lang="{{ $locale }}" @endif>
                @if ($fallback)
                    <p class="notice">{{ __('site.lang.only_in', ['language' => __('site.lang.names.'.$locale)]) }}</p>
                @endif

                <div class="prose">{!! $dataset->getTranslation('description', $locale, false) !!}</div>

                @if ($dataset->isExternal())
                    <div class="notice notice--warn">
                        <p>{{ __('data.external_text') }}</p>
                        <a class="btn" href="{{ $dataset->source_url }}" rel="noopener">{{ __('data.go_source') }}</a>
                    </div>
                @elseif ($dataset->files->isNotEmpty())
                    <h2 class="h3" id="files">{{ __('data.files') }}</h2>
                    <div class="table-scroll">
                        <table class="table downloads">
                            <thead><tr>
                                <th scope="col">{{ __('data.file_format') }}</th>
                                <th scope="col">{{ __('data.file_content') }}</th>
                                <th scope="col" class="num">{{ __('data.file_size') }}</th>
                                <th scope="col"><span class="visually-hidden">{{ __('data.download') }}</span></th>
                            </tr></thead>
                            <tbody>
                                @foreach ($dataset->files as $f)
                                    <tr>
                                        <td>{{ $f->formatLabel() }}</td>
                                        <td>
                                            <code>{{ $f->filename }}</code>
                                            @if ($f->getTranslation('label', $cur, false) ?: $f->getTranslation('label', 'pt', false))<br><small>{{ $f->getTranslation('label', $cur, false) ?: $f->getTranslation('label', 'pt', false) }}</small>@endif
                                            <br><small class="coord sha" title="SHA-256">sha256 {{ $f->sha256 }}</small>
                                        </td>
                                        <td class="num">{{ format_bytes((int) $f->size_bytes) }}</td>
                                        <td>
                                            <a class="btn btn--small" href="{{ route('download', [$f->id, $f->filename]) }}" download
                                               data-track-download="{{ $f->filename }}" data-dataset="{{ $dataset->getTranslation('slug', 'pt') }}"
                                               aria-label="{{ __('data.download_label', ['name' => $f->formatLabel(), 'size' => format_bytes((int) $f->size_bytes)]) }}">{{ __('data.download') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small muted">{{ __('data.zip_note') }} @if ($dataset->files->contains('format', 'shp')){{ __('data.shp_note') }}@endif</p>
                @endif

                <h2 class="h3">{{ __('data.attribution') }}</h2>
                <blockquote class="attribution" lang="en"><p>{{ $dataset->attribution }}</p></blockquote>

                @if ($dataset->citation)
                    <h2 class="h3">{{ __('data.citation') }}</h2>
                    <p class="small" lang="en">{{ $dataset->citation }}</p>
                @endif

                @if ($p = $dataset->getTranslation('processing', $locale, false))
                    <h2 class="h3">{{ __('data.processing') }}</h2>
                    <p>{{ $p }}</p>
                @endif

                @if ($lim = $dataset->getTranslation('limitations', $locale, false))
                    <h2 class="h3">{{ __('data.limitations') }}</h2>
                    <p>{{ $lim }}</p>
                @endif

                <div class="notice">
                    <p>{{ __('data.custom') }}</p>
                    <a class="link-arrow" href="{{ lroute('contact') }}?assunto=data">{{ __('data.custom_btn') }}</a>
                </div>
            </article>

            <aside class="span-4 start-9">
                <div class="specimen">
                    <h2 class="specimen__title">{{ __('data.record') }}</h2>
                    <dl>
                        <dt>{{ __('data.fields.source') }}</dt><dd><a href="{{ $dataset->source_url }}" rel="noopener">{{ $dataset->source_name }}</a></dd>
                        <dt>{{ __('data.fields.licence') }}</dt><dd>@if ($licUrl)<a href="{{ $licUrl }}" rel="noopener license">{{ $dataset->licenceLabel() }}</a>@else{{ $dataset->licenceLabel() }}@endif</dd>
                        @if ($dataset->version)<dt>{{ __('data.fields.version') }}</dt><dd>{{ $dataset->version }}</dd>@endif
                        @if ($dataset->period())<dt>{{ __('data.fields.period') }}</dt><dd>{{ $dataset->period() }}</dd>@endif
                        @if ($dataset->resolution)<dt>{{ __('data.fields.resolution') }}</dt><dd>{{ $dataset->resolution }}</dd>@endif
                        <dt>{{ __('data.fields.crs') }}</dt><dd>{{ $dataset->crs ?: 'EPSG:4326' }}</dd>
                        @if ($dataset->accessed_at)<dt>{{ __('data.fields.accessed') }}</dt><dd>{{ $dataset->accessed_at->toDateString() }}</dd>@endif
                        <dt>{{ __('data.fields.updated') }}</dt><dd>{{ __('data.frequency.'.$dataset->update_frequency) }}</dd>
                        @if (! $dataset->isExternal())<dt>{{ __('data.fields.downloads') }}</dt><dd>{{ format_number($downloads) }}</dd>@endif
                    </dl>
                    @if ($preview)
                        <figure class="preview">
                            {!! $preview !!}
                            <figcaption class="coord">{{ __('data.preview') }} · WGS 84</figcaption>
                        </figure>
                    @endif
                    <p class="coord specimen__foot">{{ __('data.last_updated', ['date' => format_date($dataset->updated_at)]) }}</p>
                </div>
            </aside>
        </div>
    </div>
</x-layout>
