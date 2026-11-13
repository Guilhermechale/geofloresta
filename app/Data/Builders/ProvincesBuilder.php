<?php

namespace App\Data\Builders;

use App\Data\DatasetBuilder;
use App\Geo\Geometry;
use App\Geo\Writers\Field;
use App\Models\Dataset;

/** Províncias de Moçambique — Natural Earth Admin 1 (1:10m), domínio público. */
class ProvincesBuilder extends DatasetBuilder
{
    public const URL = 'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson/ne_10m_admin_1_states_provinces.geojson';

    public function key(): string
    {
        return 'provinces';
    }

    public function definition(): array
    {
        return [
            'slug' => ['pt' => 'provincias-de-mocambique', 'fr' => 'provinces-du-mozambique', 'en' => 'provinces-of-mozambique'],
            'title' => ['pt' => 'Províncias de Moçambique (limites)', 'fr' => 'Provinces du Mozambique (limites)', 'en' => 'Provinces of Mozambique (boundaries)'],
            'summary' => [
                'pt' => 'Limites das províncias de Moçambique, com nome, código ISO 3166-2, área e centróide, prontos para SIG.',
                'fr' => 'Limites des provinces du Mozambique, avec nom, code ISO 3166-2, superficie et centroïde, prêts pour les SIG.',
                'en' => 'Boundaries of the provinces of Mozambique, with name, ISO 3166-2 code, area and centroid, ready for GIS.',
            ],
            'description' => [
                'pt' => '<p>Polígonos das províncias de Moçambique extraídos do conjunto Admin 1 (escala 1:10 000 000) do Natural Earth. São limites de referência para mapas de escala nacional e regional e servem de base aos recortes por província dos outros conjuntos de dados deste catálogo.</p>',
                'fr' => '<p>Polygones des provinces du Mozambique extraits du jeu Admin 1 (échelle 1:10 000 000) de Natural Earth. Ce sont des limites de référence pour les cartes nationales et régionales ; elles servent de base aux extraits par province des autres jeux de ce catalogue.</p>',
                'en' => '<p>Polygons of the provinces of Mozambique extracted from the Natural Earth Admin 1 dataset (1:10,000,000 scale). They are reference boundaries for national and regional maps and the basis for the per-province extracts of the other datasets in this catalogue.</p>',
            ],
            'processing' => [
                'pt' => 'Filtro dos registos com adm0_a3 = MOZ; conversão para GeoJSON, GeoPackage, Shapefile e CSV (atributos e centróide); área calculada em km² sobre o elipsóide WGS 84 (aproximação esférica, erro inferior a 1 %). Sem simplificação das geometrias nos ficheiros para descarregar.',
                'fr' => 'Filtrage des enregistrements adm0_a3 = MOZ ; conversion en GeoJSON, GeoPackage, Shapefile et CSV (attributs et centroïde) ; superficie calculée en km² (approximation sphérique, erreur inférieure à 1 %). Pas de simplification des géométries dans les fichiers téléchargeables.',
                'en' => 'Filtered records with adm0_a3 = MOZ; converted to GeoJSON, GeoPackage, Shapefile and CSV (attributes and centroid); area computed in km² (spherical approximation, error below 1%). No geometry simplification in the downloadable files.',
            ],
            'limitations' => [
                'pt' => 'Escala 1:10 000 000: não serve para demarcação legal nem para trabalhos à escala do distrito ou da localidade. Os limites oficiais são os do Instituto Nacional de Estatística (INE) e do CENACARTA.',
                'fr' => 'Échelle 1:10 000 000 : ne convient pas à la délimitation légale ni aux travaux à l’échelle du district. Les limites officielles sont celles de l’INE et du CENACARTA.',
                'en' => 'Scale 1:10,000,000: not suitable for legal demarcation or district-level work. Official boundaries are those of the National Statistics Institute (INE) and CENACARTA.',
            ],
            'theme' => 'boundaries', 'region' => 'mozambique',
            'source_name' => 'Natural Earth', 'source_url' => 'https://www.naturalearthdata.com/downloads/10m-cultural-vectors/10m-admin-1-states-provinces/',
            'licence_spdx' => 'LicenseRef-Public-Domain', 'licence_url' => 'https://www.naturalearthdata.com/about/terms-of-use/',
            'attribution' => 'Made with Natural Earth. Free vector and raster map data @ naturalearthdata.com.',
            'citation' => 'Natural Earth. Admin 1 – States, Provinces (1:10m). https://www.naturalearthdata.com/',
            'version' => 'Natural Earth 1:10m Admin 1',
            'accessed_at' => now()->toDateString(), 'resolution' => '1:10 000 000', 'crs' => 'EPSG:4326',
            'hosting' => 'hosted', 'update_frequency' => 'static',
        ];
    }

    /** Lê e devolve as províncias (para reutilizar noutros geradores). */
    public function provinces(): array
    {
        $src = json_decode(file_get_contents($this->fetch(self::URL, 'ne_10m_admin_1.geojson')), true, 512, JSON_THROW_ON_ERROR);
        $out = [];
        foreach ($src['features'] as $f) {
            $p = $f['properties'];
            if (($p['adm0_a3'] ?? null) !== 'MOZ') {
                continue;
            }
            $geom = Geometry::toMulti($f['geometry']);
            $name = $p['name'];
            $code = $p['iso_3166_2'] ?? '';
            // O Natural Earth dá o código MZ-L tanto à província como à cidade de Maputo.
            // A cidade é a unidade pequena (ou type_en = City): ISO 3166-2 MZ-MPM.
            if ($name === 'Maputo' && (($p['type_en'] ?? '') === 'City' || Geometry::areaKm2($geom) < 1000)) {
                $name = 'Cidade de Maputo';
                $code = 'MZ-MPM';
            }
            $name = ['Zambezia' => 'Zambézia'][$name] ?? $name; // grafia oficial
            $out[] = ['name' => $name, 'code' => $code, 'geom' => $geom];
        }
        usort($out, fn ($a, $b) => strcmp($a['code'], $b['code']));

        return $out;
    }

    public function build(Dataset $dataset): void
    {
        $provinces = $this->provinces();
        $this->info(count($provinces).' províncias');
        $fields = [
            Field::string('nome', 60), Field::string('codigo', 8), Field::float('area_km2', 1, 12),
            Field::float('lon', 5, 12), Field::float('lat', 5, 12),
        ];
        $features = (function () use ($provinces) {
            foreach ($provinces as $p) {
                [$lon, $lat] = Geometry::centroid($p['geom']);
                yield [['nome' => $p['name'], 'codigo' => $p['code'], 'area_km2' => Geometry::areaKm2($p['geom']), 'lon' => $lon, 'lat' => $lat], $p['geom']];
            }
        })();
        $dir = $this->workDir();
        $files = $this->writeAll($dir, 'mz_provincias_naturalearth', 'mz_provincias', $fields, 'MultiPolygon', $features);
        $this->packageAll($dataset, $files, 'mz_provincias_naturalearth', [
            'nome' => ['pt' => 'nome da província', 'fr' => 'nom de la province', 'en' => 'province name'],
            'codigo' => ['pt' => 'código ISO 3166-2', 'fr' => 'code ISO 3166-2', 'en' => 'ISO 3166-2 code'],
            'area_km2' => ['pt' => 'área (km²)', 'fr' => 'superficie (km²)', 'en' => 'area (km²)'],
            'lon/lat' => ['pt' => 'centróide (WGS 84)', 'fr' => 'centroïde (WGS 84)', 'en' => 'centroid (WGS 84)'],
        ]);

        // Camada leve para os mapas e pré-visualização
        $web = ['type' => 'FeatureCollection', 'features' => array_map(fn ($p) => [
            'type' => 'Feature', 'properties' => ['nome' => $p['name'], 'codigo' => $p['code']],
            'geometry' => Geometry::simplify($p['geom'], 0.003, 4),
        ], $provinces)];
        $this->publishWebLayer('mz-provincias.geojson', $web);
        $preview = ['type' => 'FeatureCollection', 'features' => array_map(fn ($p) => [
            'type' => 'Feature', 'properties' => ['nome' => $p['name']], 'geometry' => Geometry::simplify($p['geom'], 0.03, 3),
        ], $provinces)];
        $dataset->update(['preview_geojson' => json_encode($preview)]);
    }
}
