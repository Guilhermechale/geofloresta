<?php

namespace App\Data;

use App\Geo\DatasetPackager;
use App\Geo\Writers\CsvWriter;
use App\Geo\Writers\FeatureWriter;
use App\Geo\Writers\Field;
use App\Geo\Writers\GeoJsonWriter;
use App\Geo\Writers\GeoPackageWriter;
use App\Geo\Writers\ShapefileWriter;
use App\Models\Dataset;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Base dos geradores de datasets vectoriais/tabulares que correm no servidor (leves, sem GDAL).
 * Rasters (WorldCover, Hansen) são processados no computador do utilizador: ver pipeline/.
 */
abstract class DatasetBuilder
{
    public ?OutputStyle $out = null;

    abstract public function key(): string;

    /** Atributos do Dataset (textos PT/FR/EN, fonte, licença...). */
    abstract public function definition(): array;

    /** Gera os ficheiros e regista-os. */
    abstract public function build(Dataset $dataset): void;

    public function run(): Dataset
    {
        $def = $this->definition();
        $dataset = Dataset::query()->where('slug->pt', $def['slug']['pt'])->first()
            ?? Dataset::query()->where('slug', 'like', '%"pt":"'.$def['slug']['pt'].'"%')->first();
        if ($dataset) {
            // Só actualiza campos técnicos; textos editados no painel não são sobrepostos
            $dataset->fill(array_intersect_key($def, array_flip(['source_url', 'version', 'accessed_at', 'year_start', 'year_end', 'licence_spdx', 'licence_url', 'attribution', 'citation', 'resolution', 'crs'])));
            $dataset->save();
        } else {
            $dataset = Dataset::create($def + ['status' => 'draft']);
        }
        $this->build($dataset);

        return $dataset->fresh();
    }

    protected function info(string $m): void
    {
        $this->out?->writeln('  '.$m);
    }

    /** Descarrega para a cache de fontes (storage/app/sources), reutilizando se já existir. */
    protected function fetch(string $url, string $name, bool $refresh = false): string
    {
        $dir = storage_path('app/sources');
        @mkdir($dir, 0755, true);
        $path = $dir.'/'.$name;
        if (! $refresh && is_file($path) && filesize($path) > 0) {
            return $path;
        }
        $this->info("a descarregar {$url}");
        $r = Http::timeout(600)->withOptions(['sink' => $path])->withUserAgent('GEOFLORESTA-data-pipeline/1.0')->get($url);
        if (! $r->successful() || filesize($path) === 0) {
            @unlink($path);
            throw new \RuntimeException("Falha ao descarregar {$url} (HTTP {$r->status()})");
        }

        return $path;
    }

    protected function workDir(): string
    {
        $dir = storage_path('app/build/'.$this->key());
        if (is_dir($dir)) {
            foreach (glob($dir.'/*') as $f) {
                @unlink($f);
            }
        }
        @mkdir($dir, 0755, true);

        return $dir;
    }

    /**
     * Escreve as mesmas entidades em GeoJSON, GeoPackage, Shapefile (e CSV) numa só passagem.
     *
     * @param Field[] $fields
     * @param iterable<array{0:array,1:?array}> $features [props, geom]
     * @return array<string,string[]> formato => ficheiros
     */
    protected function writeAll(string $dir, string $baseName, string $layer, array $fields, string $geomType, iterable $features, array $formats = ['geojson', 'gpkg', 'shp', 'csv']): array
    {
        $writers = [];
        foreach ($formats as $f) {
            $writers[$f] = match ($f) {
                'geojson' => new GeoJsonWriter, 'gpkg' => new GeoPackageWriter, 'shp' => new ShapefileWriter, 'csv' => new CsvWriter,
            };
            @mkdir("{$dir}/{$f}", 0755, true);
            $writers[$f]->open("{$dir}/{$f}/{$baseName}", $fields, $geomType, $layer);
        }
        $n = 0;
        foreach ($features as [$props, $geom]) {
            foreach ($writers as $w) {
                $w->write($props, $geom);
            }
            if (++$n % 50000 === 0) {
                $this->info("… {$n} entidades");
            }
        }
        $files = [];
        foreach ($writers as $f => $w) {
            /** @var FeatureWriter $w */
            $files[$f] = $w->close();
        }
        $this->info("{$n} entidades escritas em ".implode(', ', array_keys($files)));

        return $files;
    }

    /** Empacota cada formato num ZIP próprio. */
    protected function packageAll(Dataset $dataset, array $files, string $baseName, array $fieldDocs, ?array $label = null, int $sortBase = 0): void
    {
        $packager = new DatasetPackager;
        $order = ['gpkg' => 1, 'geojson' => 2, 'shp' => 3, 'csv' => 4];
        foreach ($files as $format => $paths) {
            $zipName = $baseName.'_'.$format.'.zip';
            $file = $packager->package($dataset, $format, $paths, $zipName, $label, $fieldDocs, $sortBase + ($order[$format] ?? 9));
            $this->info(sprintf('%s  %s  %s', $zipName, format_bytes($file->size_bytes), substr($file->sha256, 0, 16).'…'));
        }
    }

    /** Guarda uma versão leve e pública (para os mapas do site). */
    protected function publishWebLayer(string $name, array $featureCollection): string
    {
        $path = 'geo/'.$name;
        Storage::disk('public')->put($path, json_encode($featureCollection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
