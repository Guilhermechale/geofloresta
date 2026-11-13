<?php

namespace App\Http\Controllers;

use App\Console\Commands\UpdateFirms;
use App\Console\Commands\UpdateGibsLayers;
use App\Models\Dataset;
use App\Support\FireSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;

class MapController extends Controller
{
    public function __invoke(): View
    {
        $gibs = UpdateGibsLayers::read();
        $summary = FireSummary::read();

        // Data da imagem por defeito: ontem (UTC) — a de hoje ainda está incompleta durante o dia
        $yesterday = now('UTC')->subDay()->toDateString();
        $imageDate = min($yesterday, $gibs['layers']['truecolor']['default_time'] ?? $yesterday);

        $fileUrl = fn (string $path) => Storage::disk('public')->exists($path)
            ? asset('storage/'.$path).'?v='.Storage::disk('public')->lastModified($path)
            : null;

        $config = [
            'locale' => app()->getLocale(),
            'styles' => [
                'light' => 'https://tiles.openfreemap.org/styles/positron',
                'dark' => 'https://tiles.openfreemap.org/styles/dark',
            ],
            // O estilo OpenFreeMap já inclui "OpenFreeMap © OpenMapTiles Data from OpenStreetMap"
            'attribution' => 'NASA GIBS · NASA FIRMS',
            'bounds' => [[30.2, -27.0], [41.0, -10.3]],
            'gibs' => $gibs['layers'] ?? [],
            'imageDate' => $imageDate,
            'fires' => $fileUrl(UpdateFirms::PUBLIC_PATH),
            'provinces' => $fileUrl('geo/mz-provincias.geojson'),
            'districts' => $fileUrl('geo/mz-distritos.geojson'),
            'updatedAt' => $summary['updated_at'] ?? null,
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
            ],
        ];

        $fireDataset = Dataset::published()->where('theme', 'fire')->where('hosting', 'hosted')->orderByDesc('year_end')->first();

        return view('maps.index', compact('config', 'summary', 'fireDataset'));
    }
}
