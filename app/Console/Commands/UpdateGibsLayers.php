<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Lê o GetCapabilities do NASA GIBS (WMTS, EPSG:3857) e guarda, para cada camada usada no mapa,
 * o identificador, o conjunto de matrizes, o nível máximo, o formato e a data por defeito.
 * Assim os identificadores e níveis nunca ficam fixos à mão no código.
 */
class UpdateGibsLayers extends Command
{
    public const CAPABILITIES = 'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/1.0.0/WMTSCapabilities.xml';

    public const PATH = 'gibs/layers.json';

    /** Por papel, candidatas por ordem de preferência (usa-se a primeira presente e mais recente). */
    public const CANDIDATES = [
        'truecolor' => ['VIIRS_NOAA20_CorrectedReflectance_TrueColor', 'VIIRS_NOAA21_CorrectedReflectance_TrueColor', 'VIIRS_SNPP_CorrectedReflectance_TrueColor', 'MODIS_Terra_CorrectedReflectance_TrueColor'],
        'ndvi' => ['VIIRS_SNPP_NDVI_8Day', 'MODIS_Terra_NDVI_8Day', 'MODIS_Terra_L3_NDVI_16Day'],
        'thermal' => ['VIIRS_NOAA20_Thermal_Anomalies_375m_All', 'VIIRS_SNPP_Thermal_Anomalies_375m_All', 'MODIS_Combined_Thermal_Anomalies_All'],
    ];

    protected $signature = 'geofloresta:gibs';

    protected $description = 'Actualiza a configuração das camadas NASA GIBS a partir do GetCapabilities';

    public function handle(): int
    {
        $r = Http::timeout(120)->withUserAgent('GEOFLORESTA/1.0')->get(self::CAPABILITIES);
        if (! $r->successful()) {
            $this->error('GetCapabilities falhou: HTTP '.$r->status());

            return self::FAILURE;
        }
        $xml = simplexml_load_string($r->body());
        $xml->registerXPathNamespace('w', 'http://www.opengis.net/wmts/1.0');
        $xml->registerXPathNamespace('ows', 'http://www.opengis.net/ows/1.1');

        $out = ['updated_at' => now()->toIso8601String(), 'source' => self::CAPABILITIES, 'layers' => []];
        foreach (self::CANDIDATES as $role => $ids) {
            $best = null;
            foreach ($ids as $id) {
                $layer = $this->layer($xml, $id);
                if ($layer && (! $best || ($layer['default_time'] ?? '') > ($best['default_time'] ?? ''))) {
                    $best = $layer;
                }
            }
            if ($best) {
                $out['layers'][$role] = $best;
                $this->line(sprintf('%-10s %s  (%s, z≤%d, %s)', $role, $best['id'], $best['default_time'] ?? '—', $best['maxzoom'], $best['format']));
            } else {
                $this->warn("Nenhuma camada disponível para {$role}");
            }
        }
        Storage::disk('local')->put(self::PATH, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    protected function layer(\SimpleXMLElement $xml, string $id): ?array
    {
        $nodes = $xml->xpath("//w:Contents/w:Layer[ows:Identifier='{$id}']");
        if (! $nodes) {
            return null;
        }
        $l = $nodes[0];
        $l->registerXPathNamespace('w', 'http://www.opengis.net/wmts/1.0');
        $l->registerXPathNamespace('ows', 'http://www.opengis.net/ows/1.1');
        $set = (string) ($l->xpath('w:TileMatrixSetLink/w:TileMatrixSet')[0] ?? '');
        $tpl = (string) ($l->xpath('w:ResourceURL/@template')[0] ?? '');
        $format = (string) ($l->xpath('w:Format')[0] ?? 'image/jpeg');
        $title = (string) ($l->xpath('ows:Title')[0] ?? $id);
        $default = (string) ($l->xpath("w:Dimension[ows:Identifier='Time']/w:Default")[0] ?? '');
        if (! $set || ! $tpl) {
            return null;
        }
        preg_match('/Level(\d+)/', $set, $m);

        return [
            'id' => $id,
            'title' => $title,
            'format' => $format,
            'tile_matrix_set' => $set,
            'maxzoom' => isset($m[1]) ? (int) $m[1] : 9,
            'default_time' => $default ? substr($default, 0, 10) : null,
            // {time} é substituído no navegador pela data escolhida
            'tiles' => str_replace(['{Time}', '{TileMatrixSet}', '{TileMatrix}', '{TileRow}', '{TileCol}'], ['{time}', $set, '{z}', '{y}', '{x}'], $tpl),
        ];
    }

    public static function read(): ?array
    {
        $raw = Storage::disk('local')->get(self::PATH);

        return $raw ? json_decode($raw, true) : null;
    }
}
