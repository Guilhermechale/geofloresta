<?php

namespace App\Console\Commands;

use App\Geo\Geometry;
use App\Support\FireSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Focos de incêndio dos últimos 7 dias em Moçambique (NASA FIRMS, quase em tempo real).
 * Sem chave: ficheiros de texto públicos "Southern Africa 7d" do FIRMS (VIIRS NOAA-20, NOAA-21, S-NPP).
 * Com FIRMS_MAP_KEY: usa a API por país (MOZ) se os ficheiros públicos falharem.
 * Grava o GeoJSON que o mapa lê (público) e o resumo para a página inicial. A chave nunca sai do servidor.
 */
class UpdateFirms extends Command
{
    public const FEEDS = [
        'VIIRS NOAA-20' => 'https://firms.modaps.eosdis.nasa.gov/data/active_fire/noaa-20-viirs-c2/csv/J1_VIIRS_C2_Southern_Africa_7d.csv',
        'VIIRS NOAA-21' => 'https://firms.modaps.eosdis.nasa.gov/data/active_fire/noaa-21-viirs-c2/csv/J2_VIIRS_C2_Southern_Africa_7d.csv',
        'VIIRS S-NPP' => 'https://firms.modaps.eosdis.nasa.gov/data/active_fire/suomi-npp-viirs-c2/csv/SUOMI_VIIRS_C2_Southern_Africa_7d.csv',
    ];

    public const API_SOURCES = ['VIIRS NOAA-20' => 'VIIRS_NOAA20_NRT', 'VIIRS NOAA-21' => 'VIIRS_NOAA21_NRT', 'VIIRS S-NPP' => 'VIIRS_SNPP_NRT'];

    public const PUBLIC_PATH = 'geo/firms-7d.geojson';

    protected $signature = 'geofloresta:firms';

    protected $description = 'Actualiza a cache de focos de incêndio dos últimos 7 dias em Moçambique (NASA FIRMS)';

    public function handle(): int
    {
        $provinces = $this->layer('geo/mz-provincias.geojson');
        $districts = $this->layer('geo/mz-distritos.geojson');
        if (! $provinces) {
            $this->error('Falta a camada de províncias (php artisan geofloresta:datasets provinces).');

            return self::FAILURE;
        }
        $mz = [30.2, -27.0, 41.0, -10.3];
        $features = [];
        $sources = [];
        $now = now('UTC');

        foreach (self::FEEDS as $label => $url) {
            $csv = $this->download($url) ?? $this->apiFallback($label);
            if ($csv === null) {
                $this->warn("{$label}: sem dados");
                continue;
            }
            $n = 0;
            $lines = preg_split('/\r?\n/', trim($csv));
            $head = str_getcsv(array_shift($lines), ',', '"', '');
            $idx = array_flip($head);
            foreach ($lines as $line) {
                $r = str_getcsv($line, ',', '"', '');
                if (count($r) < count($head)) {
                    continue;
                }
                $x = (float) $r[$idx['longitude']];
                $y = (float) $r[$idx['latitude']];
                if ($x < $mz[0] || $x > $mz[2] || $y < $mz[1] || $y > $mz[3]) {
                    continue;
                }
                $d = $this->locate($districts, $x, $y);
                $prov = $d['provincia'] ?? ($this->locate($provinces, $x, $y)['nome'] ?? null);
                if (! $prov) {
                    continue; // fora de Moçambique (o ficheiro cobre a África Austral)
                }
                $time = str_pad($r[$idx['acq_time']], 4, '0', STR_PAD_LEFT);
                $acq = $r[$idx['acq_date']].'T'.substr($time, 0, 2).':'.substr($time, 2, 2).':00Z';
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [round($x, 4), round($y, 4)]],
                    'properties' => [
                        'acq' => $acq, 'sat' => $label, 'conf' => $r[$idx['confidence']] ?? '',
                        'frp' => isset($idx['frp']) ? (float) $r[$idx['frp']] : null,
                        'dn' => $r[$idx['daynight']] ?? '', 'prov' => $prov, 'dist' => $d['nome'] ?? '',
                    ],
                ];
                $n++;
            }
            $sources[] = ['name' => $label, 'count' => $n];
            $this->line(sprintf('%-14s %6d focos em Moçambique', $label, $n));
        }

        if (! $sources) {
            $this->error('Nenhuma fonte FIRMS respondeu; a cache anterior mantém-se.');

            return self::FAILURE;
        }

        usort($features, fn ($a, $b) => strcmp($b['properties']['acq'], $a['properties']['acq']));
        $cut24 = $now->copy()->subDay()->format('Y-m-d\TH:i:s\Z');
        $last24 = array_filter($features, fn ($f) => $f['properties']['acq'] >= $cut24);
        $byProv = fn (array $fs) => collect($fs)->countBy(fn ($f) => $f['properties']['prov'])->sortKeys()->all();

        $this->atomicPut('public', self::PUBLIC_PATH, json_encode([
            'type' => 'FeatureCollection', 'updated_at' => $now->toIso8601String(), 'features' => $features,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->atomicPut('local', FireSummary::PATH, json_encode([
            'count_24h' => count($last24),
            'count_7d' => count($features),
            'by_province_24h' => $byProv($last24),
            'by_province_7d' => $byProv($features),
            'latest_acq' => $features[0]['properties']['acq'] ?? null,
            'updated_at' => $now->toIso8601String(),
            'sources' => $sources,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info(sprintf('%d focos em 7 dias, %d nas últimas 24 h.', count($features), count($last24)));

        return self::SUCCESS;
    }

    protected function download(string $url): ?string
    {
        try {
            $r = Http::timeout(120)->retry(2, 2000, throw: false)->withUserAgent('GEOFLORESTA/1.0')->get($url);
        } catch (\Throwable) {
            return null;
        }
        $body = $r->successful() ? $r->body() : '';

        return substr_count($body, "\n") >= 1 && str_starts_with($body, 'latitude') ? $body : null;
    }

    /** Com chave gratuita: API por país, 5 dias (só se o ficheiro público falhar). */
    protected function apiFallback(string $label): ?string
    {
        $key = config('geofloresta.firms_map_key');
        if (! $key || ! isset(self::API_SOURCES[$label])) {
            return null;
        }

        return $this->download('https://firms.modaps.eosdis.nasa.gov/api/country/csv/'.rawurlencode($key).'/'.self::API_SOURCES[$label].'/MOZ/5');
    }

    protected function layer(string $path): array
    {
        $raw = Storage::disk('public')->get($path);
        if (! $raw) {
            return [];
        }

        return array_map(fn ($f) => $f['properties'] + ['g' => $f['geometry'], 'bbox' => Geometry::bbox($f['geometry'])], json_decode($raw, true)['features']);
    }

    protected function locate(array $areas, float $x, float $y): ?array
    {
        foreach ($areas as $a) {
            [$x0, $y0, $x1, $y1] = $a['bbox'];
            if ($x >= $x0 && $x <= $x1 && $y >= $y0 && $y <= $y1 && Geometry::contains($a['g'], $x, $y)) {
                return $a;
            }
        }

        return null;
    }

    protected function atomicPut(string $disk, string $path, string $contents): void
    {
        // Escreve num temporário e troca de uma vez: o mapa nunca lê um ficheiro a meio
        $tmp = $path.'.tmp';
        Storage::disk($disk)->put($tmp, $contents);
        rename(Storage::disk($disk)->path($tmp), Storage::disk($disk)->path($path));
    }
}
