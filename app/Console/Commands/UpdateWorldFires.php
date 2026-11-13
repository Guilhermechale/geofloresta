<?php

namespace App\Console\Commands;

use App\Geo\Geometry;
use App\Geo\World;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Focos de incêndio dos últimos 7 dias em todos os países (NASA FIRMS, ficheiros globais públicos, sem chave).
 * Cada foco é atribuído ao país (Natural Earth 1:50m). Grava, por país, GeoJSON (mapa) e CSV (download)
 * em storage/app/public/geo/fires/{ISO}.* e um resumo com as contagens de 24 h e 7 dias.
 */
class UpdateWorldFires extends Command
{
    protected $signature = 'geofloresta:firms-world';

    protected $description = 'Actualiza os focos de incêndio dos últimos 7 dias de todos os países (NASA FIRMS)';

    public const FEEDS = [
        'VIIRS NOAA-20' => 'https://firms.modaps.eosdis.nasa.gov/data/active_fire/noaa-20-viirs-c2/csv/J1_VIIRS_C2_Global_7d.csv',
        'VIIRS NOAA-21' => 'https://firms.modaps.eosdis.nasa.gov/data/active_fire/noaa-21-viirs-c2/csv/J2_VIIRS_C2_Global_7d.csv',
    ];

    protected array $countries = [];
    protected array $grid = [];

    public function handle(): int
    {
        ini_set('memory_limit', '1536M');
        set_time_limit(0);
        $local = Storage::disk('local');
        $public = Storage::disk('public');
        if (! $local->exists(PrepareWorld::RAW)) {
            $this->error('Falta a lista de países: php artisan geofloresta:world');

            return self::FAILURE;
        }
        $this->index(json_decode($local->get(PrepareWorld::RAW), true)['features'] ?? []);

        $tmpDir = $local->path('world/tmp-fires');
        @mkdir($tmpDir, 0775, true);
        array_map('unlink', glob($tmpDir.'/*.csv') ?: []);
        $handles = [];
        $sources = [];
        $start = microtime(true);

        foreach (self::FEEDS as $label => $url) {
            $file = $local->path('world/feed.csv');
            try {
                $r = Http::timeout(300)->retry(2, 3000, throw: false)->withUserAgent('GEOFLORESTA/1.0')->sink($file)->get($url);
            } catch (\Throwable $e) {
                $this->warn("{$label}: {$e->getMessage()}");
                continue;
            }
            if (! $r->successful() || filesize($file) < 100) {
                $this->warn("{$label}: sem dados");
                continue;
            }
            $fh = fopen($file, 'r');
            $head = fgetcsv($fh, 0, ',', '"', '');
            $i = array_flip($head ?: []);
            if (! isset($i['latitude'], $i['longitude'], $i['acq_date'])) {
                fclose($fh);
                continue;
            }
            $n = 0;
            $matched = 0;
            while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
                $n++;
                $x = (float) $row[$i['longitude']];
                $y = (float) $row[$i['latitude']];
                $iso = $this->locate($x, $y);
                if (! $iso) {
                    continue;
                }
                $matched++;
                $t = str_pad($row[$i['acq_time']] ?? '0000', 4, '0', STR_PAD_LEFT);
                $acq = $row[$i['acq_date']].'T'.substr($t, 0, 2).':'.substr($t, 2, 2).':00Z';
                $handles[$iso] ??= fopen("{$tmpDir}/{$iso}.csv", 'a');
                fputcsv($handles[$iso], [$acq, $label, $row[$i['confidence']] ?? '', $row[$i['frp']] ?? '', $row[$i['daynight']] ?? '', round($x, 4), round($y, 4)], ',', '"', '');
            }
            fclose($fh);
            @unlink($file);
            $sources[] = ['name' => $label, 'count' => $matched];
            $this->line(sprintf('%-14s %7d focos lidos, %7d atribuídos a um país', $label, $n, $matched));
        }
        foreach ($handles as $h) {
            fclose($h);
        }
        if (! $sources) {
            $this->error('Nenhuma fonte FIRMS respondeu; os ficheiros anteriores mantêm-se.');

            return self::FAILURE;
        }

        // Ficheiros por país
        $now = now('UTC');
        $cut24 = $now->copy()->subDay()->format('Y-m-d\TH:i:s\Z');
        $summary = [];
        $seen = [];
        foreach (glob($tmpDir.'/*.csv') ?: [] as $csvFile) {
            $iso = basename($csvFile, '.csv');
            $rows = array_map(fn ($l) => str_getcsv($l, ',', '"', ''), file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            usort($rows, fn ($a, $b) => strcmp($b[0], $a[0]));
            $features = [];
            $csv = "data_hora_utc,satelite,confianca,frp_mw,dia_noite,longitude,latitude\n";
            $n24 = 0;
            $compact = [];
            $sats = array_keys(self::FEEDS);
            foreach ($rows as [$acq, $sat, $conf, $frp, $dn, $x, $y]) {
                $features[] = ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [(float) $x, (float) $y]],
                    'properties' => ['acq' => $acq, 'sat' => $sat, 'conf' => $conf, 'frp' => $frp === '' ? null : (float) $frp, 'dn' => $dn]];
                // Versão compacta para o mapa: [lon, lat, "AAAA-MM-DDTHH:MM", satélite (índice), confiança (1.ª letra), FRP]
                $compact[] = [(float) $x, (float) $y, substr($acq, 0, 16), (int) array_search($sat, $sats, true), substr($conf, 0, 1), $frp === '' ? null : round((float) $frp, 1)];
                $csv .= "{$acq},{$sat},{$conf},{$frp},{$dn},{$x},{$y}\n";
                $n24 += $acq >= $cut24 ? 1 : 0;
            }
            $this->atomicPut($public, World::firesPath($iso), json_encode(['type' => 'FeatureCollection', 'updated_at' => $now->toIso8601String(), 'features' => $features], JSON_UNESCAPED_SLASHES));
            $this->atomicPut($public, World::firesPath($iso, 'json'), json_encode(['updated_at' => $now->toIso8601String(), 'sats' => $sats, 'rows' => $compact], JSON_UNESCAPED_SLASHES));
            $this->atomicPut($public, World::firesPath($iso, 'csv'), $csv);
            $summary[$iso] = ['n24' => $n24, 'n7' => count($rows), 'latest' => $rows[0][0] ?? null];
            $seen[$iso] = true;
            unlink($csvFile);
        }
        // Países sem focos nesta actualização: ficheiros vazios (não ficam dados antigos)
        foreach (array_keys($this->countries) as $iso) {
            if (! isset($seen[$iso]) && $public->exists(World::firesPath($iso))) {
                $this->atomicPut($public, World::firesPath($iso), json_encode(['type' => 'FeatureCollection', 'updated_at' => $now->toIso8601String(), 'features' => []]));
                $this->atomicPut($public, World::firesPath($iso, 'csv'), "data_hora_utc,satelite,confianca,frp_mw,dia_noite,longitude,latitude\n");
                $this->atomicPut($public, World::firesPath($iso, 'json'), json_encode(['updated_at' => $now->toIso8601String(), 'sats' => array_keys(self::FEEDS), 'rows' => []]));
            }
        }
        uasort($summary, fn ($a, $b) => $b['n7'] <=> $a['n7']);
        $this->atomicPut($local, World::FIRES_SUMMARY, json_encode([
            'updated_at' => $now->toIso8601String(), 'sources' => $sources, 'countries' => $summary,
        ], JSON_PRETTY_PRINT));
        $this->info(sprintf('%d países com focos · %d focos em 7 dias · %.0f s', count($summary), array_sum(array_column($summary, 'n7')), microtime(true) - $start));

        return self::SUCCESS;
    }

    /** Índice espacial simples: grelha de 1° com os países cujo bbox a toca. */
    protected function index(array $features): void
    {
        foreach ($features as $f) {
            $iso = PrepareWorld::isoOf(array_change_key_case($f['properties'] ?? [], CASE_LOWER));
            if (! $iso || ! $f['geometry']) {
                continue;
            }
            // Cada polígono com o seu bbox (países com ilhas dispersas ficam mais rápidos)
            foreach (Geometry::toMulti($f['geometry'])['coordinates'] as $poly) {
                $g = ['type' => 'Polygon', 'coordinates' => $poly];
                $b = Geometry::bbox($g);
                $this->countries[$iso] = true;
                $part = ['iso' => $iso, 'g' => $g, 'b' => $b];
                for ($gx = (int) floor($b[0]); $gx <= (int) floor($b[2]); $gx++) {
                    for ($gy = (int) floor($b[1]); $gy <= (int) floor($b[3]); $gy++) {
                        $this->grid["{$gx}:{$gy}"][] = $part;
                    }
                }
            }
        }
    }

    protected function locate(float $x, float $y): ?string
    {
        foreach ($this->grid[((int) floor($x)).':'.((int) floor($y))] ?? [] as $p) {
            [$x0, $y0, $x1, $y1] = $p['b'];
            if ($x >= $x0 && $x <= $x1 && $y >= $y0 && $y <= $y1 && Geometry::contains($p['g'], $x, $y)) {
                return $p['iso'];
            }
        }

        return null;
    }

    protected function atomicPut($disk, string $path, string $contents): void
    {
        $tmp = $path.'.tmp';
        $disk->put($tmp, $contents);
        rename($disk->path($tmp), $disk->path($path));
    }
}
