<?php

namespace App\Http\Controllers;

use App\Console\Commands\UpdateFirms;
use App\Console\Commands\UpdateGibsLayers;
use App\Geo\World;
use App\Models\Dataset;
use App\Models\Tutorial;
use App\Support\FireSummary;
use App\Support\Locales;
use App\Support\MapTools;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Mapas: página de escolha (cartões) e as ferramentas (/mapas/{ferramenta}) — incêndios, uso do solo, NDVI, relevo, imagem de satélite.
 * Monitor de incêndios para qualquer país (?pais=iso3). Moçambique mantém províncias e distritos
 * (geofloresta:firms); os restantes países usam os ficheiros por país de geofloresta:firms-world.
 */
class MapController extends Controller
{
    /** Página de escolha da ferramenta. Os endereços antigos com ?pais= abrem o monitor de incêndios desse país. */
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->filled('pais')) {
            return redirect()->to(MapTools::url('fires', null, ['pais' => strtolower((string) $request->query('pais'))]), 301);
        }

        return view('maps.hub');
    }

    public function tool(Request $request, string $tool): View
    {
        $key = MapTools::fromSlug($tool) ?? abort(404);
        $query = $request->filled('pais') ? ['pais' => strtolower(substr(preg_replace('/[^a-z]/i', '', (string) $request->query('pais')), 0, 3))] : [];
        Locales::setAlternates(collect(Locales::all())->mapWithKeys(fn ($l) => [$l => MapTools::url($key, $l, $query)])->all());

        return $this->map($request, $key);
    }

    protected function map(Request $request, string $tool): View
    {
        $iso = strtoupper(substr(preg_replace('/[^a-z]/i', '', (string) $request->query('pais', 'moz')), 0, 3));
        $country = World::find($iso) ?? World::find('MOZ');
        $iso = $country['iso'] ?? 'MOZ';
        $isMoz = $iso === 'MOZ';

        $gibs = UpdateGibsLayers::read();
        $summary = $isMoz ? FireSummary::read() : null;
        $world = World::fires();
        $stats = $world['countries'][$iso] ?? null;

        // Data da imagem por defeito: ontem (UTC) — a de hoje ainda está incompleta durante o dia
        $yesterday = now('UTC')->subDay()->toDateString();
        $imageDate = min($yesterday, $gibs['layers']['truecolor']['default_time'] ?? $yesterday);

        $fileUrl = fn (string $path) => Storage::disk('public')->exists($path)
            ? asset('storage/'.$path).'?v='.Storage::disk('public')->lastModified($path)
            : null;

        $bbox = $country['bbox'] ?? [30.2, -27.0, 41.0, -10.3];
        $countryName = $country ? World::name($country) : 'Moçambique';

        $config = [
            'locale' => app()->getLocale(),
            'iso' => $iso,
            'countryName' => $countryName,
            'styles' => [
                'light' => 'https://tiles.openfreemap.org/styles/positron',
                'dark' => 'https://tiles.openfreemap.org/styles/dark',
            ],
            // O estilo OpenFreeMap já inclui "OpenFreeMap © OpenMapTiles Data from OpenStreetMap"
            'attribution' => 'NASA GIBS · NASA FIRMS · Natural Earth · <a href="https://github.com/tilezen/joerd/blob/master/docs/attribution.md" target="_blank" rel="noopener">Terrain Tiles</a> (SRTM, GMTED2010, ETOPO1…)',
            'dem' => ['tiles' => 'https://elevation-tiles-prod.s3.amazonaws.com/terrarium/{z}/{x}/{y}.png', 'attribution' => config('sources')[array_search('terrain-tiles', array_column(config('sources'), 'key'))]['attribution'] ?? ''],
            'bounds' => $isMoz ? [[30.2, -27.0], [41.0, -10.3]] : [[$bbox[0], $bbox[1]], [$bbox[2], $bbox[3]]],
            'gibs' => $gibs['layers'] ?? [],
            'imageDate' => $imageDate,
            'fires' => $isMoz ? $fileUrl(UpdateFirms::PUBLIC_PATH) : $fileUrl(World::firesPath($iso, 'json')),
            'compact' => ! $isMoz,
            'outline' => $fileUrl(World::outlinePath($iso)),
            'provinces' => $isMoz ? $fileUrl('geo/mz-provincias.geojson') : null,
            'districts' => $isMoz ? $fileUrl('geo/mz-distritos.geojson') : null,
            'updatedAt' => $isMoz ? ($summary['updated_at'] ?? null) : ($world['updated_at'] ?? null),
            't' => [
                'popup' => __('map.popup'),
                'conf' => __('map.conf'),
                'result' => [
                    0 => trans_choice('map.aoi_result', 0, ['count' => 0]),
                    1 => trans_choice('map.aoi_result', 1, ['count' => 1]),
                    'n' => trans_choice('map.aoi_result', 2, ['count' => ':count']),
                ],
                'area' => __('map.aoi_area', ['km2' => ':km2']),
                'byDay' => __('map.aoi_by_day'),
                'pick' => __('map.aoi_pick'),
                'dem_progress' => __('map.dem_progress'),
                'dem_working' => __('map.dem_working'),
                'dem_result' => __('map.dem_result'),
                'dem_too_big' => __('map.dem_too_big'),
                'dem_empty' => __('map.dem_empty'),
                'dem_failed' => __('map.dem_failed'),
                'dem_contours_done' => __('map.dem_contours_done'),
                'dist' => trans('map.dist') + ['hansenBase' => url('/dados-abertos/hansen'), 'hansenLast' => config('geofloresta.hansen.last_year')],
                'lulc' => collect(trans('map'))->filter(fn ($v, $k) => str_starts_with($k, 'lulc_') || str_starts_with($k, 'area_'))->all() + ['panel' => trans('map.panel')],
                'drawHelp' => __('map.aoi_draw_help'),
                'polygon' => __('map.aoi_polygon'),
            ],
        ];

        $fireDataset = $isMoz ? Dataset::published()->where('theme', 'fire')->where('hosting', 'hosted')->orderByDesc('year_end')->first() : null;

        // Tutorial que explica o método (ligação no painel de uso do solo)
        $rfTutorial = Tutorial::query()->published()->where('slug->pt', 'classificacao-supervisionada-random-forest-google-earth-engine')->first();

        // Pesquisa sobre o EUDR (ligação no bloco «Para investigadores» do monitor de desmatamento)
        $eudr = in_array($tool, ['deforestation'], true)
            ? \App\Models\Study::query()->published()->where('slug', 'perda-de-cobertura-arborea-agricultura-data-de-corte-eudr-2021-2024')->first() : null;

        $P = trans('map.portal');
        $utc = fn ($iso, $time = false) => $iso ? \Illuminate\Support\Carbon::parse($iso)->utc()->format($time ? 'Y-m-d H:i' : 'Y-m-d').($time ? ' UTC' : '') : null;
        $health = \App\Console\Commands\CheckSources::read();
        $fresh = $health && \Illuminate\Support\Carbon::parse($health['checked_at'])->gt(now()->subHours(48));
        $healthState = ! $fresh ? 'unknown' : ($health['ok'] ? 'ok' : 'degraded');
        $checked = $health ? [$P['checked'], $utc($health['checked_at'], true)] : null;
        $gibsLayer = fn (string $role) => $gibs['layers'][$role] ?? null;
        $status = match ($tool) {
            'deforestation' => ['state' => $healthState, 'items' => array_values(array_filter([
                ! empty($health['s2_latest']) ? ['Sentinel-2 L2A', $utc($health['s2_latest'])] : null,
                ! empty($health['s1_latest']) ? ['Sentinel-1 RTC', $utc($health['s1_latest'])] : null,
                ! empty($health['hansen']) ? ['Hansen GFC', $health['hansen']['version'].' · 2001–'.$health['hansen']['last_year']] : null,
                $checked,
            ]))],
            'lulc' => ['state' => $healthState, 'items' => array_values(array_filter([
                ! empty($health['s2_latest']) ? ['Sentinel-2 L2A', $utc($health['s2_latest'])] : null,
                ['Esri / IO LULC', '10 m · 2017–'.(now()->year - 1)],
                $checked,
            ]))],
            'relief' => ['state' => $health ? (($health['checks']['terrain_tiles']['ok'] ?? true) && $fresh ? 'ok' : $healthState) : 'unknown', 'items' => array_values(array_filter([
                ['Terrain Tiles', 'AWS Open Data · Terrarium'], $checked,
            ]))],
            'fires' => ['state' => ! empty($config['updatedAt']) && \Illuminate\Support\Carbon::parse($config['updatedAt'])->gt(now()->subHours(8)) ? 'ok' : (empty($config['updatedAt']) ? 'unknown' : 'degraded'),
                'items' => array_values(array_filter([
                    ! empty($config['updatedAt']) ? ['NASA FIRMS · VIIRS', $utc($config['updatedAt'], true)] : null,
                    ($latest = $isMoz ? ($summary['latest_acq'] ?? null) : ($stats['latest'] ?? null)) ? [$P['latest_fire'], $utc($latest, true)] : null,
                ]))],
            'ndvi', 'satellite' => (function () use ($gibsLayer, $tool, $utc, $P) {
                $l = $gibsLayer($tool === 'ndvi' ? 'ndvi' : 'truecolor');
                if (! $l) {
                    return ['state' => 'unknown', 'items' => []];
                }
                $age = now()->diffInDays($l['default_time'] ?? now(), true);

                return ['state' => $age <= ($tool === 'ndvi' ? 24 : 4) ? 'ok' : 'degraded', 'items' => [
                    [$P['layer'], str_replace('_', ' ', $l['id'])], ['NASA GIBS', $l['default_time'] ?? ''],
                ]];
            })(),
            default => ['state' => 'unknown', 'items' => []],
        };
        $relief = $tool === 'relief' ? Tutorial::query()->published()->where('slug->pt', 'curvas-de-nivel-da-sua-area-em-2-minutos-gratis')->first() : null;
        $links = array_values(array_filter([
            $tool === 'deforestation' && $eudr ? [$P['eudr'], $eudr->url(app()->getLocale())] : null,
            $tool === 'lulc' && $rfTutorial ? [__('map.lulc_learn'), $rfTutorial->url(app()->getLocale())] : null,
            $relief ? [__('map.tutorial_link'), $relief->url(app()->getLocale())] : null,
            $tool === 'fires' && $fireDataset ? [$P['history'], $fireDataset->url()] : null,
            $tool === 'fires' ? [$P['kit'].' · '.$countryName, lroute('countries.show', ['iso' => strtolower($iso)])] : null,
        ]));

        return view('maps.index', [
            'status' => $status,
            'links' => $links,
            'rfTutorialUrl' => $rfTutorial?->url(app()->getLocale()),
            'eudrUrl' => $eudr?->url(app()->getLocale()),
            'config' => $config + ['tool' => $tool],
            'tool' => $tool,
            'summary' => $summary,
            'fireDataset' => $fireDataset,
            'country' => $country,
            'countryName' => $countryName,
            'isMoz' => $isMoz,
            'stats' => $stats,
            'world' => $world,
            'countries' => World::sorted(),
        ]);
    }
}
