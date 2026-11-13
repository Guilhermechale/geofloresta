<?php

namespace App\Console\Commands;

use App\Geo\Geometry;
use App\Geo\World;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Prepara a lista de países do mundo e os contornos (Natural Earth 1:50m, domínio público).
 * Grava storage/app/world/countries.json e storage/app/public/geo/world/{ISO}.geojson.
 */
class PrepareWorld extends Command
{
    protected $signature = 'geofloresta:world {--refresh : volta a descarregar o Natural Earth}';

    protected $description = 'Prepara a lista de países e contornos (Natural Earth)';

    public const SOURCE = 'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson/ne_50m_admin_0_countries.geojson';
    public const RAW = 'world/ne_50m_admin_0_countries.geojson';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');
        $local = Storage::disk('local');
        if ($this->option('refresh') || ! $local->exists(self::RAW)) {
            $r = Http::timeout(180)->withUserAgent('GEOFLORESTA/1.0')->get(self::SOURCE);
            if (! $r->successful() || ! str_contains($r->body(), 'FeatureCollection')) {
                $this->error('Não foi possível descarregar o Natural Earth.');

                return self::FAILURE;
            }
            $local->put(self::RAW, $r->body());
        }
        $features = json_decode($local->get(self::RAW), true)['features'] ?? [];

        $countries = [];
        foreach ($features as $f) {
            $p = array_change_key_case($f['properties'] ?? [], CASE_LOWER);
            $iso = $this->iso($p);
            if (! $iso || ! $f['geometry']) {
                continue;
            }
            $geom = Geometry::simplify($f['geometry'], 0.01, 4);
            $bbox = array_map(fn ($v) => round($v, 3), Geometry::bbox($f['geometry']));
            $countries[$iso] = [
                'iso' => $iso,
                'iso2' => ($p['iso_a2_eh'] ?? $p['iso_a2'] ?? '') !== '-99' ? ($p['iso_a2_eh'] ?? $p['iso_a2'] ?? '') : '',
                'names' => [
                    'pt' => $p['name_pt'] ?? $p['name'] ?? $iso,
                    'fr' => $p['name_fr'] ?? $p['name'] ?? $iso,
                    'en' => $p['name_en'] ?? $p['name'] ?? $iso,
                ],
                'continent' => $p['continent'] ?? '',
                'subregion' => $p['subregion'] ?? '',
                'bbox' => $bbox,
            ];
            Storage::disk('public')->put(World::outlinePath($iso), json_encode([
                'type' => 'FeatureCollection',
                'features' => [['type' => 'Feature', 'properties' => ['iso' => $iso, 'nome' => $countries[$iso]['names']['pt']], 'geometry' => $geom]],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        ksort($countries);
        $local->put(World::LIST, json_encode($countries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info(count($countries).' países gravados.');

        return self::SUCCESS;
    }

    protected function iso(array $p): ?string
    {
        foreach (['iso_a3_eh', 'iso_a3', 'adm0_a3'] as $k) {
            $v = strtoupper((string) ($p[$k] ?? ''));
            if (preg_match('/^[A-Z]{3}$/', $v)) {
                return $v;
            }
        }

        return null;
    }
}
