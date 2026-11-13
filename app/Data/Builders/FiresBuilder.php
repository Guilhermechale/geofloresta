<?php

namespace App\Data\Builders;

use App\Data\DatasetBuilder;
use App\Geo\DatasetPackager;
use App\Geo\Geometry;
use App\Geo\Writers\Field;
use App\Models\Dataset;

/**
 * Focos de incêndio activo em Moçambique — arquivo anual por país do NASA FIRMS (VIIRS S-NPP 375 m),
 * descarregável sem chave. Cada detecção recebe a província e o distrito onde cai.
 */
class FiresBuilder extends DatasetBuilder
{
    public function __construct(public int $year = 2025, public string $sensor = 'viirs-snpp') {}

    public function key(): string
    {
        return "fires-{$this->sensor}-{$this->year}";
    }

    public function url(): string
    {
        return "https://firms.modaps.eosdis.nasa.gov/data/country/{$this->sensor}/{$this->year}/{$this->sensor}_{$this->year}_Mozambique.csv";
    }

    public function definition(): array
    {
        $y = $this->year;

        return [
            'slug' => ['pt' => "focos-de-incendio-mocambique-viirs-{$y}", 'fr' => "foyers-incendie-mozambique-viirs-{$y}", 'en' => "active-fires-mozambique-viirs-{$y}"],
            'title' => ['pt' => "Focos de incêndio activo em Moçambique, {$y} (VIIRS)", 'fr' => "Foyers d’incendie actifs au Mozambique, {$y} (VIIRS)", 'en' => "Active fire detections in Mozambique, {$y} (VIIRS)"],
            'summary' => [
                'pt' => "Todas as detecções de fogo activo do satélite S-NPP (sensor VIIRS, 375 m) sobre Moçambique em {$y}, com província e distrito, e uma tabela de contagens por província e mês.",
                'fr' => "Toutes les détections de feux actifs du satellite S-NPP (capteur VIIRS, 375 m) sur le Mozambique en {$y}, avec province et district, et un tableau des décomptes par province et par mois.",
                'en' => "All active fire detections by the S-NPP satellite (VIIRS sensor, 375 m) over Mozambique in {$y}, with province and district, plus a table of counts by province and month.",
            ],
            'description' => [
                'pt' => "<p>Cada registo é um píxel de 375 m em que o sensor VIIRS detectou uma anomalia térmica no momento da passagem do satélite (cerca das 01:30 e 13:30, hora local). Um incêndio extenso gera várias detecções; uma queimada pequena ou rápida pode não ser detectada. Os dados vêm do arquivo anual por país do NASA FIRMS (processamento padrão).</p><p>Inclui a tabela <code>resumo_provincia_mes</code> com o número de detecções por província e por mês.</p>",
                'fr' => "<p>Chaque enregistrement est un pixel de 375 m où le capteur VIIRS a détecté une anomalie thermique au passage du satellite (vers 01 h 30 et 13 h 30, heure locale). Un grand incendie produit plusieurs détections ; un petit brûlage rapide peut ne pas être détecté. Les données proviennent de l’archive annuelle par pays de NASA FIRMS (traitement standard).</p><p>Comprend le tableau <code>resumo_provincia_mes</code> avec le nombre de détections par province et par mois.</p>",
                'en' => "<p>Each record is a 375 m pixel where the VIIRS sensor detected a thermal anomaly at satellite overpass (around 01:30 and 13:30 local time). A large fire produces several detections; a small, quick burn may be missed. Data come from NASA FIRMS’ annual per-country archive (standard processing).</p><p>Includes the <code>resumo_provincia_mes</code> table with the number of detections per province and month.</p>",
            ],
            'processing' => [
                'pt' => "Descarga do CSV anual {$this->sensor}_{$y}_Mozambique do arquivo FIRMS; leitura em streaming; atribuição de província (Natural Earth) e distrito (geoBoundaries) a cada ponto por inclusão em polígono, usando limites simplificados (tolerância 0,001°, cerca de 100 m); renomeação dos campos para nomes curtos em português; conversão para CSV, GeoJSON, GeoPackage e Shapefile; contagem por província e mês.",
                'fr' => "Téléchargement du CSV annuel {$this->sensor}_{$y}_Mozambique de l’archive FIRMS ; lecture en continu ; attribution de la province (Natural Earth) et du district (geoBoundaries) à chaque point par inclusion dans un polygone, avec des limites simplifiées (tolérance 0,001°, environ 100 m) ; renommage des champs ; conversion en CSV, GeoJSON, GeoPackage et Shapefile ; décompte par province et par mois.",
                'en' => "Downloaded the annual {$this->sensor}_{$y}_Mozambique CSV from the FIRMS archive; streamed it; assigned province (Natural Earth) and district (geoBoundaries) to each point by point-in-polygon using simplified boundaries (0.001° tolerance, about 100 m); renamed fields to short Portuguese names; converted to CSV, GeoJSON, GeoPackage and Shapefile; counted by province and month.",
            ],
            'limitations' => [
                'pt' => 'Uma detecção não é um incêndio confirmado no terreno: pode corresponder a queimadas agrícolas, fogo de pastagem ou fontes industriais (campo tipo). Nuvens e fumo impedem detecções. O número de detecções não mede área ardida. Pontos junto aos limites podem ser atribuídos à província ou ao distrito vizinho.',
                'fr' => 'Une détection n’est pas un incendie confirmé au sol : elle peut correspondre à des brûlis, des feux de pâturage ou des sources industrielles (champ tipo). Nuages et fumée empêchent la détection. Le nombre de détections ne mesure pas la surface brûlée. Les points proches des limites peuvent être attribués à la province ou au district voisin.',
                'en' => 'A detection is not a fire confirmed on the ground: it may be agricultural burning, grassland fire or an industrial source (tipo field). Clouds and smoke prevent detection. The number of detections does not measure burned area. Points near boundaries may be assigned to the neighbouring province or district.',
            ],
            'theme' => 'fire', 'region' => 'mozambique', 'year_start' => $y, 'year_end' => $y,
            'source_name' => 'NASA FIRMS (VIIRS S-NPP 375 m, VNP14IMG)', 'source_url' => 'https://firms.modaps.eosdis.nasa.gov/country/',
            'licence_spdx' => 'LicenseRef-NASA-Open-Data', 'licence_url' => 'https://www.earthdata.nasa.gov/engage/open-data-services-software/data-information-policy',
            'attribution' => 'We acknowledge the use of imagery from the NASA LANCE FIRMS (https://earthdata.nasa.gov/firms), part of the NASA Earth Science Data and Information System (ESDIS).',
            'citation' => 'NASA FIRMS. VIIRS (S-NPP) I Band 375 m Active Fire product (VNP14IMG), annual country archive. https://firms.modaps.eosdis.nasa.gov/',
            'version' => "Arquivo anual por país, {$y}", 'accessed_at' => now()->toDateString(), 'resolution' => '375 m', 'crs' => 'EPSG:4326',
            'hosting' => 'hosted', 'update_frequency' => 'static',
        ];
    }

    public function build(Dataset $dataset): void
    {
        $csv = $this->fetch($this->url(), basename($this->url()));
        $provinces = array_map(fn ($p) => $p + ['bbox' => Geometry::bbox($p['geom']), 'g' => Geometry::simplify($p['geom'], 0.001, 5)], (new ProvincesBuilder)->provinces());
        $districts = array_map(fn ($d) => $d + ['bbox' => Geometry::bbox($d['geom']), 'g' => Geometry::simplify($d['geom'], 0.001, 5)], (new DistrictsBuilder)->districts($provinces));

        $locate = function (array $areas, float $x, float $y): ?array {
            foreach ($areas as $a) {
                [$x0, $y0, $x1, $y1] = $a['bbox'];
                if ($x >= $x0 && $x <= $x1 && $y >= $y0 && $y <= $y1 && Geometry::contains($a['g'], $x, $y)) {
                    return $a;
                }
            }

            return null;
        };

        $summary = [];
        $fields = [
            Field::date('data'), Field::string('hora', 4), Field::string('satelite', 6), Field::string('sensor', 6),
            Field::string('confianca', 8), Field::float('brilho_i4', 2, 8), Field::float('brilho_i5', 2, 8),
            Field::float('frp', 2, 10), Field::string('dianoite', 1), Field::int('tipo', 2),
            Field::string('provincia', 40), Field::string('distrito', 60),
        ];
        $features = (function () use ($csv, $locate, $provinces, $districts, &$summary) {
            $fh = fopen($csv, 'rb');
            $head = fgetcsv($fh, 0, ',', '"', '');
            $idx = array_flip($head);
            foreach (['latitude', 'longitude', 'acq_date', 'acq_time', 'confidence', 'bright_ti4', 'bright_ti5', 'frp', 'daynight', 'satellite'] as $col) {
                if (! isset($idx[$col])) {
                    throw new \RuntimeException("Coluna em falta no CSV do FIRMS: {$col}");
                }
            }
            while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
                if (count($r) < count($head)) {
                    continue;
                }
                $x = (float) $r[$idx['longitude']];
                $y = (float) $r[$idx['latitude']];
                // Distrito primeiro (limites mais finos); a província vem do distrito quando o há
                $d = $locate($districts, $x, $y);
                $dist = $d['name'] ?? '';
                $prov = $d['province'] ?? ($locate($provinces, $x, $y)['name'] ?? '');
                $date = $r[$idx['acq_date']];
                $month = substr($date, 0, 7);
                $summary[$prov ?: '(fora)'][$month] = ($summary[$prov ?: '(fora)'][$month] ?? 0) + 1;
                yield [[
                    'data' => $date,
                    'hora' => str_pad($r[$idx['acq_time']], 4, '0', STR_PAD_LEFT),
                    'satelite' => $r[$idx['satellite']],
                    'sensor' => $r[$idx['instrument']] ?? 'VIIRS',
                    'confianca' => $r[$idx['confidence']],
                    'brilho_i4' => (float) $r[$idx['bright_ti4']],
                    'brilho_i5' => (float) $r[$idx['bright_ti5']],
                    'frp' => (float) $r[$idx['frp']],
                    'dianoite' => $r[$idx['daynight']],
                    'tipo' => isset($idx['type']) ? (int) $r[$idx['type']] : null,
                    'provincia' => $prov,
                    'distrito' => $dist,
                ], ['type' => 'Point', 'coordinates' => [$x, $y]]];
            }
            fclose($fh);
        })();

        $base = "mz_focos_{$this->sensor}_{$this->year}";
        $dir = $this->workDir();
        $files = $this->writeAll($dir, $base, 'focos_incendio', $fields, 'Point', $features);
        $docs = [
            'data/hora' => ['pt' => 'data e hora UTC da passagem (HHMM)', 'fr' => 'date et heure UTC du passage (HHMM)', 'en' => 'overpass date and UTC time (HHMM)'],
            'confianca' => ['pt' => 'confiança VIIRS: l (baixa), n (nominal), h (alta)', 'fr' => 'confiance VIIRS : l (faible), n (nominale), h (élevée)', 'en' => 'VIIRS confidence: l (low), n (nominal), h (high)'],
            'brilho_i4' => ['pt' => 'temperatura de brilho banda I4 (K)', 'fr' => 'température de brillance bande I4 (K)', 'en' => 'I4 band brightness temperature (K)'],
            'brilho_i5' => ['pt' => 'temperatura de brilho banda I5 (K)', 'fr' => 'température de brillance bande I5 (K)', 'en' => 'I5 band brightness temperature (K)'],
            'frp' => ['pt' => 'potência radiativa do fogo (MW)', 'fr' => 'puissance radiative du feu (MW)', 'en' => 'fire radiative power (MW)'],
            'dianoite' => ['pt' => 'D = dia, N = noite', 'fr' => 'D = jour, N = nuit', 'en' => 'D = day, N = night'],
            'tipo' => ['pt' => '0 vegetação presumida, 1 vulcão, 2 outra fonte terrestre estática, 3 mar', 'fr' => '0 végétation présumée, 1 volcan, 2 autre source terrestre fixe, 3 mer', 'en' => '0 presumed vegetation fire, 1 volcano, 2 other static land source, 3 offshore'],
            'provincia' => ['pt' => 'província (Natural Earth)', 'fr' => 'province (Natural Earth)', 'en' => 'province (Natural Earth)'],
            'distrito' => ['pt' => 'distrito (geoBoundaries)', 'fr' => 'district (geoBoundaries)', 'en' => 'district (geoBoundaries)'],
        ];
        $this->packageAll($dataset, $files, $base, $docs);

        // Tabela resumo: província × mês
        ksort($summary);
        $months = [];
        foreach ($summary as $rows) {
            $months += array_flip(array_keys($rows));
        }
        $months = array_keys($months);
        sort($months);
        $sumPath = "{$dir}/resumo_provincia_mes_{$this->year}.csv";
        $fh = fopen($sumPath, 'wb');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['provincia', ...$months, 'total'], ',', '"', '');
        foreach ($summary as $prov => $rows) {
            $vals = array_map(fn ($m) => $rows[$m] ?? 0, $months);
            fputcsv($fh, [$prov, ...$vals, array_sum($vals)], ',', '"', '');
        }
        fclose($fh);
        (new DatasetPackager)->package($dataset, 'csv', [$sumPath], "resumo_provincia_mes_{$this->year}.zip",
            ['pt' => 'Resumo por província e mês', 'fr' => 'Résumé par province et par mois', 'en' => 'Summary by province and month'], [], 0);
        $total = array_sum(array_map('array_sum', $summary));
        $this->info("Resumo: {$total} detecções em ".count($summary).' províncias');

        // Pré-visualização: contorno das províncias (os pontos são demasiados para a ficha)
        $dataset->update(['preview_geojson' => json_encode(['type' => 'FeatureCollection', 'features' => array_map(fn ($p) => [
            'type' => 'Feature', 'properties' => [], 'geometry' => Geometry::simplify($p['geom'], 0.03, 3),
        ], $provinces)])]);
    }
}
