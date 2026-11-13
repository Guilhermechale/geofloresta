<?php

namespace App\Http\Controllers;

use App\Console\Commands\UpdateFirms;
use App\Console\Commands\UpdateGibsLayers;
use App\Geo\World;
use App\Models\Dataset;
use App\Support\FireSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Monitor de incêndios para qualquer país (?pais=iso3). Moçambique mantém províncias e distritos
 * (geofloresta:firms); os restantes países usam os ficheiros por país de geofloresta:firms-world.
 */
class MapController extends Controller
{
    public function __invoke(Request $request): View
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
            'attribution' => 'NASA GIBS · NASA FIRMS · Natural Earth',
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
                'drawHelp' => __('map.aoi_draw_help'),
                'polygon' => __('map.aoi_polygon'),
            ],
        ];

        $fireDataset = $isMoz ? Dataset::published()->where('theme', 'fire')->where('hosting', 'hosted')->orderByDesc('year_end')->first() : null;

        return view('maps.index', [
            'config' => $config,
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
