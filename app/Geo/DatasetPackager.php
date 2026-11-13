<?php

namespace App\Geo;

use App\Models\Dataset;
use App\Models\DatasetFile;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Empacota ficheiros de dados num ZIP com LEIAME.txt (PT/FR/EN), LICENCA.txt e CITACAO.txt,
 * e regista-o como DatasetFile (tamanho e SHA-256 do ZIP final).
 * Os nomes dos ficheiros não se traduzem.
 */
class DatasetPackager
{
    public const LICENCE_NAMES = [
        'CC-BY-4.0' => ['Creative Commons Attribution 4.0 International', 'https://creativecommons.org/licenses/by/4.0/'],
        'CC-BY-3.0-IGO' => ['Creative Commons Attribution 3.0 IGO', 'https://creativecommons.org/licenses/by/3.0/igo/'],
        'CC0-1.0' => ['Creative Commons Zero 1.0 Universal', 'https://creativecommons.org/publicdomain/zero/1.0/'],
        'ODbL-1.0' => ['Open Data Commons Open Database License 1.0', 'https://opendatacommons.org/licenses/odbl/1-0/'],
        'LicenseRef-Public-Domain' => ['Public domain', null],
        'LicenseRef-NASA-Open-Data' => ['NASA Earth Science Data and Information Policy (open data)', 'https://www.earthdata.nasa.gov/engage/open-data-services-software/data-information-policy'],
    ];

    public const FORMAT_NOTES = [
        'shp' => [
            'pt' => 'Shapefile: formato antigo com limitações — nomes de campo até 10 caracteres, texto até 254 bytes, vários ficheiros (.shp .shx .dbf .prj .cpg) que têm de ficar juntos, máximo 2 GB. Prefira o GeoPackage quando possível.',
            'fr' => 'Shapefile : format ancien avec des limites — noms de champs de 10 caractères au plus, texte de 254 octets au plus, plusieurs fichiers (.shp .shx .dbf .prj .cpg) à conserver ensemble, 2 Go maximum. Préférez le GeoPackage si possible.',
            'en' => 'Shapefile: legacy format with limitations — field names of up to 10 characters, text up to 254 bytes, several files (.shp .shx .dbf .prj .cpg) that must stay together, 2 GB maximum. Prefer GeoPackage where possible.',
        ],
        'csv' => [
            'pt' => 'CSV em UTF-8, separador vírgula, ponto decimal. Coordenadas em graus decimais WGS 84 (EPSG:4326).',
            'fr' => 'CSV en UTF-8, séparateur virgule, point décimal. Coordonnées en degrés décimaux WGS 84 (EPSG:4326).',
            'en' => 'UTF-8 CSV, comma separator, decimal point. Coordinates in WGS 84 decimal degrees (EPSG:4326).',
        ],
        'geojson' => ['pt' => 'GeoJSON (RFC 7946), WGS 84.', 'fr' => 'GeoJSON (RFC 7946), WGS 84.', 'en' => 'GeoJSON (RFC 7946), WGS 84.'],
        'gpkg' => ['pt' => 'GeoPackage (OGC), WGS 84 (EPSG:4326). Abre directamente no QGIS.', 'fr' => 'GeoPackage (OGC), WGS 84 (EPSG:4326). S’ouvre directement dans QGIS.', 'en' => 'GeoPackage (OGC), WGS 84 (EPSG:4326). Opens directly in QGIS.'],
        'cog' => ['pt' => 'GeoTIFF optimizado para a nuvem (COG).', 'fr' => 'GeoTIFF optimisé pour le cloud (COG).', 'en' => 'Cloud Optimized GeoTIFF (COG).'],
    ];

    /**
     * @param string[] $files caminhos absolutos dos ficheiros de dados
     * @param array{pt?:string,fr?:string,en?:string}|null $label
     * @param array<string,string> $fieldDocs descrição dos campos (nome => descrição PT/EN)
     */
    public function package(Dataset $dataset, string $format, array $files, string $zipName, ?array $label = null, array $fieldDocs = [], int $sort = 0): DatasetFile
    {
        $dir = 'files/'.$dataset->getTranslation('slug', 'pt');
        Storage::disk('datasets')->makeDirectory($dir);
        $zipPath = Storage::disk('datasets')->path($dir.'/'.$zipName);
        @unlink($zipPath);

        $hashes = [];
        foreach ($files as $f) {
            $hashes[basename($f)] = ['sha256' => hash_file('sha256', $f), 'size' => filesize($f)];
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Não foi possível criar {$zipPath}");
        }
        foreach ($files as $f) {
            $zip->addFile($f, basename($f));
            $zip->setCompressionName(basename($f), ZipArchive::CM_DEFLATE);
        }
        $zip->addFromString('LEIAME.txt', $this->readme($dataset, $format, $hashes, $fieldDocs));
        $zip->addFromString('LICENCA.txt', $this->licence($dataset));
        $zip->addFromString('CITACAO.txt', $this->citation($dataset));
        $zip->close();

        $file = DatasetFile::updateOrCreate(
            ['dataset_id' => $dataset->id, 'format' => $format, 'filename' => $zipName],
            ['disk' => 'datasets', 'path' => $dir.'/'.$zipName, 'label' => $label ?? [], 'sort' => $sort],
        );
        // Recalcula sempre (o caminho pode não ter mudado, mas o conteúdo sim)
        $file->forceFill(['size_bytes' => filesize($zipPath), 'sha256' => hash_file('sha256', $zipPath)])->saveQuietly();

        return $file;
    }

    protected function readme(Dataset $d, string $format, array $hashes, array $fieldDocs): string
    {
        $sep = str_repeat('=', 72);
        $out = [];
        foreach (['pt' => 'PORTUGUÊS', 'fr' => 'FRANÇAIS', 'en' => 'ENGLISH'] as $l => $name) {
            $L = fn ($pt, $fr, $en) => ['pt' => $pt, 'fr' => $fr, 'en' => $en][$l];
            $t = fn ($field) => trim(strip_tags(html_entity_decode((string) ($d->getTranslation($field, $l, false) ?: $d->getTranslation($field, 'pt', false)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $out[] = $sep."\n".$name."\n".$sep;
            $out[] = $t('title');
            $out[] = $t('summary');
            if ($desc = $t('description')) {
                $out[] = wordwrap($desc, 90);
            }
            $out[] = implode("\n", array_filter([
                $L('Fonte', 'Source', 'Source').': '.$d->source_name.' — '.$d->source_url,
                $L('Licença', 'Licence', 'Licence').': '.$d->licence_spdx.($d->licence_url ? ' — '.$d->licence_url : ''),
                $d->version ? $L('Versão', 'Version', 'Version').': '.$d->version : null,
                $d->period() ? $L('Período', 'Période', 'Period').': '.$d->period() : null,
                $d->resolution ? $L('Resolução', 'Résolution', 'Resolution').': '.$d->resolution : null,
                $L('Sistema de coordenadas', 'Système de coordonnées', 'Coordinate system').': '.($d->crs ?: 'EPSG:4326'),
                $d->accessed_at ? $L('Data de acesso à fonte', 'Date d’accès à la source', 'Source accessed on').': '.$d->accessed_at->toDateString() : null,
                $L('Ficha', 'Fiche', 'Record').': '.$this->safeUrl($d, $l),
            ]));
            $out[] = $L('Atribuição obrigatória', 'Attribution obligatoire', 'Required attribution').":\n".$d->attribution;
            if ($p = $t('processing')) {
                $out[] = $L('Processamento', 'Traitement', 'Processing').":\n".wordwrap($p, 90);
            }
            if ($lim = $t('limitations')) {
                $out[] = $L('Limitações', 'Limites', 'Limitations').":\n".wordwrap($lim, 90);
            }
            if (isset(self::FORMAT_NOTES[$format][$l])) {
                $out[] = $L('Formato', 'Format', 'Format').': '.self::FORMAT_NOTES[$format][$l];
            }
            if ($fieldDocs) {
                $out[] = $L('Campos', 'Champs', 'Fields').":\n".implode("\n", array_map(
                    fn ($k, $v) => '  '.str_pad($k, 12).(is_array($v) ? ($v[$l] ?? $v['en'] ?? reset($v)) : $v),
                    array_keys($fieldDocs), $fieldDocs,
                ));
            }
            $out[] = '';
        }
        $out[] = $sep."\nSHA-256\n".$sep;
        foreach ($hashes as $name => $h) {
            $out[] = $h['sha256'].'  '.$name.'  ('.$h['size'].' bytes)';
        }
        $out[] = "\nGEOFLORESTA — https://geofloresta.com — ".now()->toDateString();

        return implode("\n\n", $out)."\n";
    }

    protected function licence(Dataset $d): string
    {
        [$name, $url] = self::LICENCE_NAMES[$d->licence_spdx] ?? [$d->licence_spdx, $d->licence_url];
        $url = $d->licence_url ?: $url;

        return implode("\n\n", array_filter([
            'SPDX: '.$d->licence_spdx,
            $name.($url ? "\n".$url : ''),
            "PT: Estes ficheiros são derivados (recorte, conversão de formato e/ou tabelas) de dados de {$d->source_name} e são distribuídos nas mesmas condições da licença de origem. Ao usá-los, cite a fonte com a atribuição abaixo.",
            "FR: Ces fichiers sont dérivés (extrait, conversion de format et/ou tableaux) de données de {$d->source_name} et sont diffusés aux mêmes conditions que la licence d’origine. En les utilisant, citez la source avec l’attribution ci-dessous.",
            "EN: These files are derived (extract, format conversion and/or tables) from data by {$d->source_name} and are distributed under the same terms as the original licence. When using them, credit the source with the attribution below.",
            "Attribution:\n".$d->attribution,
            'Sem garantias / Sans garantie / No warranty.',
        ]))."\n";
    }

    protected function citation(Dataset $d): string
    {
        $title = $d->getTranslation('title', 'pt');
        $year = now()->year;

        return implode("\n\n", array_filter([
            "Fonte original / Source originale / Original source:\n".($d->citation ?: $d->attribution),
            "Processamento / Traitement / Processing:\nGEOFLORESTA ({$year}). {$title}".($d->version ? ". {$d->version}" : '').'. '.$this->safeUrl($d, 'pt').'. '.($d->accessed_at ? 'Fonte acedida em '.$d->accessed_at->toDateString().'.' : ''),
        ]))."\n";
    }

    protected function safeUrl(Dataset $d, string $locale): string
    {
        try {
            return $d->url($locale);
        } catch (\Throwable) {
            return rtrim(config('app.url'), '/').$d->publicPath($locale, $d->getTranslation('slug', $locale, false) ?: $d->getTranslation('slug', 'pt'));
        }
    }
}
