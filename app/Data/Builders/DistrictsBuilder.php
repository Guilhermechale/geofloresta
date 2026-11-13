<?php

namespace App\Data\Builders;

use App\Data\DatasetBuilder;
use App\Geo\Geometry;
use App\Geo\Writers\Field;
use App\Models\Dataset;

/** Distritos de Moçambique — geoBoundaries gbOpen MOZ ADM2 (INE, OCHA ROSEA), CC BY 3.0 IGO. */
class DistrictsBuilder extends DatasetBuilder
{
    public const API = 'https://www.geoboundaries.org/api/current/gbOpen/MOZ/ADM2/';

    public function key(): string
    {
        return 'districts';
    }

    public function definition(): array
    {
        return [
            'slug' => ['pt' => 'distritos-de-mocambique', 'fr' => 'districts-du-mozambique', 'en' => 'districts-of-mozambique'],
            'title' => ['pt' => 'Distritos de Moçambique (limites)', 'fr' => 'Districts du Mozambique (limites)', 'en' => 'Districts of Mozambique (boundaries)'],
            'summary' => [
                'pt' => 'Limites dos distritos de Moçambique (nível administrativo 2), com a província de cada distrito, área e centróide.',
                'fr' => 'Limites des districts du Mozambique (niveau administratif 2), avec la province de chaque district, la superficie et le centroïde.',
                'en' => 'Boundaries of the districts of Mozambique (administrative level 2), with each district’s province, area and centroid.',
            ],
            'description' => [
                'pt' => '<p>Polígonos dos distritos de Moçambique publicados pelo projecto geoBoundaries, com origem no Instituto Nacional de Estatística (INE) e no Escritório Regional da OCHA para a África Austral e Oriental. Servem para recortes e estatísticas por distrito, incluindo a contagem de focos de incêndio deste catálogo.</p>',
                'fr' => '<p>Polygones des districts du Mozambique publiés par le projet geoBoundaries, issus de l’Institut national de la statistique (INE) et du bureau régional de l’OCHA pour l’Afrique australe et orientale. Ils servent aux extraits et statistiques par district, dont le comptage des foyers d’incendie de ce catalogue.</p>',
                'en' => '<p>District polygons of Mozambique published by the geoBoundaries project, sourced from the National Statistics Institute (INE) and OCHA’s Regional Office for Southern and Eastern Africa. Used for district-level extracts and statistics, including this catalogue’s fire counts.</p>',
            ],
            'processing' => [
                'pt' => 'Descarga do GeoJSON gbOpen MOZ ADM2 indicado pela API do geoBoundaries; atribuição da província pelo centróide de cada distrito sobre o conjunto «Províncias de Moçambique» (Natural Earth); cálculo da área em km²; conversão para GeoJSON, GeoPackage, Shapefile e CSV.',
                'fr' => 'Téléchargement du GeoJSON gbOpen MOZ ADM2 indiqué par l’API geoBoundaries ; attribution de la province par le centroïde de chaque district sur le jeu « Provinces du Mozambique » (Natural Earth) ; calcul de la superficie en km² ; conversion en GeoJSON, GeoPackage, Shapefile et CSV.',
                'en' => 'Downloaded the gbOpen MOZ ADM2 GeoJSON given by the geoBoundaries API; assigned each district’s province from its centroid using the “Provinces of Mozambique” dataset (Natural Earth); computed area in km²; converted to GeoJSON, GeoPackage, Shapefile and CSV.',
            ],
            'limitations' => [
                'pt' => 'Os limites representam a divisão de 2019 indicada pela fonte e podem não incluir alterações posteriores. A província é atribuída pelo centróide sobre limites do Natural Earth (escala diferente) e deve ser verificada nos distritos de fronteira. Não serve para demarcação legal.',
                'fr' => 'Les limites représentent le découpage de 2019 indiqué par la source et peuvent ne pas inclure les changements ultérieurs. La province est attribuée par centroïde sur des limites Natural Earth (échelle différente) et doit être vérifiée pour les districts frontaliers. Ne convient pas à la délimitation légale.',
                'en' => 'Boundaries represent the 2019 division stated by the source and may not include later changes. The province is assigned by centroid over Natural Earth boundaries (different scale) and should be checked for border districts. Not suitable for legal demarcation.',
            ],
            'theme' => 'boundaries', 'region' => 'mozambique', 'year_start' => 2019, 'year_end' => 2019,
            'source_name' => 'geoBoundaries (INE Moçambique, OCHA ROSEA)', 'source_url' => 'https://www.geoboundaries.org/countryDownloads.html',
            'licence_spdx' => 'CC-BY-3.0-IGO', 'licence_url' => 'https://creativecommons.org/licenses/by/3.0/igo/',
            'attribution' => 'Mozambique INE (Instituto Nacional de Estatística), OCHA ROSEA, via geoBoundaries (www.geoboundaries.org).',
            'citation' => 'Runfola, D. et al. (2020). geoBoundaries: A global database of political administrative boundaries. PLoS ONE 15(4): e0231866. https://doi.org/10.1371/journal.pone.0231866',
            'version' => 'gbOpen MOZ ADM2', 'accessed_at' => now()->toDateString(), 'crs' => 'EPSG:4326',
            'hosting' => 'hosted', 'update_frequency' => 'static',
        ];
    }

    public function districts(array $provinces): array
    {
        $meta = json_decode(file_get_contents($this->fetch(self::API, 'geoboundaries_moz_adm2.json', true)), true, 512, JSON_THROW_ON_ERROR);
        if (stripos((string) ($meta['boundaryLicense'] ?? ''), 'Attribution 3.0') === false) {
            throw new \RuntimeException('A licença do geoBoundaries MOZ ADM2 mudou: '.($meta['boundaryLicense'] ?? '?').' — rever antes de alojar.');
        }
        $src = json_decode(file_get_contents($this->fetch($meta['gjDownloadURL'], 'geoboundaries_moz_adm2.geojson')), true, 512, JSON_THROW_ON_ERROR);
        $out = [];
        foreach ($src['features'] as $f) {
            $geom = Geometry::toMulti($f['geometry']);
            [$lon, $lat] = Geometry::centroid($geom);
            $prov = self::provinceFor($provinces, $geom, $lon, $lat);
            $out[] = ['name' => $f['properties']['shapeName'], 'id' => $f['properties']['shapeID'], 'province' => $prov, 'geom' => $geom, 'lon' => $lon, 'lat' => $lat];
        }
        usort($out, fn ($a, $b) => [$a['province'], $a['name']] <=> [$b['province'], $b['name']]);

        return $out;
    }

    /**
     * Província de um distrito: a que contém o centróide; senão (ilhas e costa, onde os limites
     * 1:10m do Natural Earth ficam aquém) a que contém algum vértice; senão a de centróide mais próximo.
     */
    public static function provinceFor(array $provinces, array $geom, float $lon, float $lat): string
    {
        foreach ($provinces as $p) {
            if (Geometry::contains($p['geom'], $lon, $lat)) {
                return $p['name'];
            }
        }
        foreach ($geom['coordinates'] as $poly) {
            foreach ($poly[0] as $i => [$x, $y]) {
                if ($i % 5) {
                    continue;
                }
                foreach ($provinces as $p) {
                    if (Geometry::contains($p['geom'], $x, $y)) {
                        return $p['name'];
                    }
                }
            }
        }
        $best = '';
        $bestD = INF;
        foreach ($provinces as $p) {
            [$cx, $cy] = Geometry::centroid($p['geom']);
            $d = ($cx - $lon) ** 2 + ($cy - $lat) ** 2;
            if ($d < $bestD) {
                $bestD = $d;
                $best = $p['name'];
            }
        }

        return $best;
    }

    public function build(Dataset $dataset): void
    {
        $provinces = (new ProvincesBuilder)->provinces();
        $districts = $this->districts($provinces);
        $this->info(count($districts).' distritos');
        $fields = [
            Field::string('nome', 80), Field::string('provincia', 60), Field::string('id_geob', 40),
            Field::float('area_km2', 1, 12), Field::float('lon', 5, 12), Field::float('lat', 5, 12),
        ];
        $features = (function () use ($districts) {
            foreach ($districts as $d) {
                yield [['nome' => $d['name'], 'provincia' => $d['province'], 'id_geob' => $d['id'], 'area_km2' => Geometry::areaKm2($d['geom']), 'lon' => $d['lon'], 'lat' => $d['lat']], $d['geom']];
            }
        })();
        $files = $this->writeAll($this->workDir(), 'mz_distritos_geoboundaries', 'mz_distritos', $fields, 'MultiPolygon', $features);
        $this->packageAll($dataset, $files, 'mz_distritos_geoboundaries', [
            'nome' => ['pt' => 'nome do distrito (como na fonte)', 'fr' => 'nom du district (selon la source)', 'en' => 'district name (as in source)'],
            'provincia' => ['pt' => 'província (pelo centróide)', 'fr' => 'province (par centroïde)', 'en' => 'province (by centroid)'],
            'id_geob' => ['pt' => 'identificador geoBoundaries', 'fr' => 'identifiant geoBoundaries', 'en' => 'geoBoundaries ID'],
            'area_km2' => ['pt' => 'área (km²)', 'fr' => 'superficie (km²)', 'en' => 'area (km²)'],
            'lon/lat' => ['pt' => 'centróide (WGS 84)', 'fr' => 'centroïde (WGS 84)', 'en' => 'centroid (WGS 84)'],
        ]);

        $this->publishWebLayer('mz-distritos.geojson', ['type' => 'FeatureCollection', 'features' => array_map(fn ($d) => [
            'type' => 'Feature', 'properties' => ['nome' => $d['name'], 'provincia' => $d['province']],
            'geometry' => Geometry::simplify($d['geom'], 0.002, 4),
        ], $districts)]);
        $dataset->update(['preview_geojson' => json_encode(['type' => 'FeatureCollection', 'features' => array_map(fn ($d) => [
            'type' => 'Feature', 'properties' => [], 'geometry' => Geometry::simplify($d['geom'], 0.02, 3),
        ], $districts)])]);
    }
}
