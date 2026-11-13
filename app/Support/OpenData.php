<?php

namespace App\Support;

use App\Geo\World;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Dados abertos e gratuitos para qualquer país, com licença que permite uso (incluindo comercial) com atribuição.
 * Os focos de incêndio são gerados no servidor (geofloresta:firms-world); o resto liga às fontes oficiais.
 * As respostas das APIs ficam em cache 30 dias (aquecida por geofloresta:countries-cache).
 */
class OpenData
{
    public const TTL_DAYS = 30;

    /** @return list<array{key: string, items: list<array>}> */
    public static function forCountry(array $c, bool $live = true): array
    {
        $iso = $c['iso'];
        $pub = Storage::disk('public');
        $sections = [];

        // 1. Focos de incêndio (servidos pela GEOFLORESTA)
        $fires = World::fires()['countries'][$iso] ?? null;
        $items = [];
        foreach (['csv' => 'CSV', 'geojson' => 'GeoJSON'] as $ext => $fmt) {
            $p = World::firesPath($iso, $ext);
            if ($pub->exists($p)) {
                $items[] = ['label' => 'fires_7d', 'url' => asset('storage/'.$p).'?v='.$pub->lastModified($p), 'format' => $fmt, 'size' => $pub->size($p), 'licence' => 'NASA Open Data', 'source' => 'NASA FIRMS (VIIRS NOAA-20, NOAA-21)', 'download' => true];
            }
        }
        $items[] = ['label' => 'fires_archive', 'url' => 'https://firms.modaps.eosdis.nasa.gov/country/', 'format' => 'CSV', 'licence' => 'NASA Open Data', 'source' => 'NASA FIRMS'];
        $sections[] = ['key' => 'fires', 'items' => $items, 'stats' => $fires];

        // 2. Limites administrativos (geoBoundaries)
        $items = [];
        foreach (['ADM0', 'ADM1', 'ADM2'] as $lvl) {
            $b = self::cached("gb:{$iso}:{$lvl}", fn () => self::json("https://www.geoboundaries.org/api/current/gbOpen/{$iso}/{$lvl}/"), $live);
            if (! is_array($b) || empty($b['gjDownloadURL'])) {
                continue;
            }
            $licence = $b['boundaryLicense'] ?? '';
            $meta = ['level' => $lvl, 'units' => $b['admUnitCount'] ?? null, 'year' => $b['boundaryYearRepresented'] ?? null, 'licence' => $licence, 'source' => 'geoBoundaries'.(! empty($b['boundarySource']) ? ' · '.$b['boundarySource'] : '')];
            $items[] = ['label' => 'boundaries', 'url' => $b['gjDownloadURL'], 'format' => 'GeoJSON'] + $meta;
            if (! empty($b['staticDownloadLink'])) {
                $items[] = ['label' => 'boundaries', 'url' => $b['staticDownloadLink'], 'format' => 'Shapefile + GeoJSON (ZIP)'] + $meta;
            }
        }
        if (! $items) {
            $items[] = ['label' => 'boundaries_portal', 'url' => 'https://www.geoboundaries.org/countryDownloads.html', 'format' => 'GeoJSON / Shapefile', 'licence' => 'CC BY / ODbL', 'source' => 'geoBoundaries'];
        }
        $sections[] = ['key' => 'boundaries', 'items' => $items];

        // 3. Floresta (Global Forest Watch)
        $gfw = "https://gfw2-data.s3.amazonaws.com/country-pages/country_stats/download/{$iso}.xlsx";
        $hasGfw = self::cached("gfw:{$iso}", fn () => self::exists($gfw) ? 1 : 0, $live);
        $items = [];
        if ($hasGfw) {
            $items[] = ['label' => 'forest_stats', 'url' => $gfw, 'format' => 'XLSX', 'licence' => 'CC BY 4.0', 'source' => 'Global Forest Watch (Hansen et al.; Sims et al.)'];
        }
        $items[] = ['label' => 'forest_dashboard', 'url' => "https://www.globalforestwatch.org/dashboards/country/{$iso}/", 'format' => 'Web', 'licence' => 'CC BY 4.0', 'source' => 'Global Forest Watch'];
        $sections[] = ['key' => 'forest', 'items' => $items];

        // 4. População (WorldPop)
        $wp = self::cached("wp:{$iso}", function () use ($iso) {
            $d = self::json("https://hub.worldpop.org/rest/data/pop/wpgp?iso3={$iso}")['data'] ?? [];
            usort($d, fn ($a, $b) => (int) ($b['popyear'] ?? 0) <=> (int) ($a['popyear'] ?? 0));
            $x = $d[0] ?? null;

            return $x ? ['year' => $x['popyear'] ?? null, 'file' => $x['files'][0] ?? null, 'page' => $x['url_summary'] ?? null] : null;
        }, $live);
        $items = [];
        if (! empty($wp['file'])) {
            $items[] = ['label' => 'population', 'url' => $wp['file'], 'format' => 'GeoTIFF (100 m)', 'year' => $wp['year'], 'licence' => 'CC BY 4.0', 'source' => 'WorldPop'];
        }
        $items[] = ['label' => 'population_portal', 'url' => 'https://hub.worldpop.org/geodata/listing?id=29', 'format' => 'GeoTIFF', 'licence' => 'CC BY 4.0', 'source' => 'WorldPop'];
        $sections[] = ['key' => 'population', 'items' => $items];

        // 5. Rios, bacias e lagos (HydroSHEDS)
        $items = [];
        foreach (World::hydroRegions($c) as $r) {
            $items[] = ['label' => 'rivers', 'url' => "https://data.hydrosheds.org/file/HydroRIVERS/HydroRIVERS_v10_{$r}_shp.zip", 'format' => 'Shapefile (ZIP)', 'region' => $r, 'licence' => 'HydroSHEDS License', 'source' => 'HydroSHEDS / WWF'];
            $items[] = ['label' => 'basins', 'url' => "https://data.hydrosheds.org/file/hydrobasins/standard/hybas_{$r}_lev01-12_v1c.zip", 'format' => 'Shapefile (ZIP)', 'region' => $r, 'licence' => 'HydroSHEDS License', 'source' => 'HydroSHEDS / WWF'];
        }
        $items[] = ['label' => 'lakes', 'url' => 'https://data.hydrosheds.org/file/hydrolakes/HydroLAKES_polys_v10_shp.zip', 'format' => 'Shapefile (ZIP)', 'licence' => 'CC BY 4.0', 'source' => 'HydroSHEDS / WWF'];
        $sections[] = ['key' => 'water', 'items' => $items];

        // 6. Uso do solo, relevo, clima e imagens (fontes oficiais)
        [$x0, $y0, $x1, $y1] = $c['bbox'];
        $lat = round(($y0 + $y1) / 2, 3);
        $lng = round(($x0 + $x1) / 2, 3);
        $sections[] = ['key' => 'more', 'items' => [
            ['label' => 'landcover', 'url' => 'https://esa-worldcover.org/en/data-access', 'format' => 'GeoTIFF (10 m)', 'licence' => 'CC BY 4.0', 'source' => 'ESA WorldCover'],
            ['label' => 'elevation', 'url' => 'https://portal.opentopography.org/raster?opentopoID=OTSDEM.032021.4326.3', 'format' => 'GeoTIFF (30 m)', 'licence' => 'Copernicus DEM License', 'source' => 'Copernicus DEM GLO-30 / OpenTopography'],
            ['label' => 'rainfall', 'url' => 'https://www.chc.ucsb.edu/data/chirps', 'format' => 'GeoTIFF / NetCDF', 'licence' => 'CC BY 4.0', 'source' => 'CHIRPS (Climate Hazards Center)'],
            ['label' => 'climate', 'url' => 'https://cds.climate.copernicus.eu/datasets/reanalysis-era5-single-levels-monthly-means', 'format' => 'NetCDF / GRIB', 'licence' => 'Copernicus License', 'source' => 'ERA5 / Copernicus C3S'],
            ['label' => 'imagery', 'url' => "https://browser.dataspace.copernicus.eu/?zoom=6&lat={$lat}&lng={$lng}", 'format' => 'Sentinel-1/2 (Web, GeoTIFF)', 'licence' => 'Copernicus License', 'source' => 'Copernicus Data Space'],
            ['label' => 'osm', 'url' => 'https://download.geofabrik.de/', 'format' => 'Shapefile / PBF', 'licence' => 'ODbL', 'source' => 'OpenStreetMap / Geofabrik'],
        ]];

        return $sections;
    }

    /** Lê da cache; só chama a API se $live (página) e não houver valor guardado. */
    protected static function cached(string $key, callable $fetch, bool $live): mixed
    {
        $k = 'opendata:'.$key;
        if (Cache::has($k)) {
            return Cache::get($k);
        }
        if (! $live) {
            return null;
        }
        $v = $fetch();
        Cache::put($k, $v ?? 0, now()->addDays($v === null ? 1 : self::TTL_DAYS));

        return $v;
    }

    public static function warm(array $c): void
    {
        foreach (['ADM0', 'ADM1', 'ADM2'] as $lvl) {
            Cache::forget("opendata:gb:{$c['iso']}:{$lvl}");
        }
        Cache::forget("opendata:gfw:{$c['iso']}");
        Cache::forget("opendata:wp:{$c['iso']}");
        self::forCountry($c, true);
    }

    protected static function json(string $url): ?array
    {
        try {
            $r = Http::timeout(8)->withUserAgent('GEOFLORESTA/1.0')->acceptJson()->get($url);

            return $r->successful() ? $r->json() : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected static function exists(string $url): bool
    {
        try {
            return Http::timeout(8)->withUserAgent('GEOFLORESTA/1.0')->head($url)->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
