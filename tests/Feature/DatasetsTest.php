<?php

namespace Tests\Feature;

use App\Geo\DatasetPackager;
use App\Geo\Geometry;
use App\Geo\Writers\CsvWriter;
use App\Geo\Writers\Field;
use App\Geo\Writers\GeoJsonWriter;
use App\Geo\Writers\GeoPackageWriter;
use App\Geo\Writers\ShapefileWriter;
use App\Models\Dataset;
use App\Models\DatasetFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SQLite3;
use Tests\TestCase;
use ZipArchive;

class DatasetsTest extends TestCase
{
    use RefreshDatabase;

    /** Quadrado com buraco, exterior no sentido horário (de propósito, para testar a orientação). */
    protected function square(): array
    {
        return ['type' => 'Polygon', 'coordinates' => [
            [[34, -20], [34, -19], [35, -19], [35, -20], [34, -20]],
            [[34.4, -19.6], [34.6, -19.6], [34.6, -19.4], [34.4, -19.4], [34.4, -19.6]],
        ]];
    }

    protected function writeAll(string $dir): array
    {
        $fields = [Field::string('nome', 40), Field::int('n', 6), Field::float('area', 2, 10)];
        $files = [];
        foreach (['geojson' => new GeoJsonWriter, 'gpkg' => new GeoPackageWriter, 'shp' => new ShapefileWriter, 'csv' => new CsvWriter] as $k => $w) {
            $w->open("{$dir}/teste", $fields, 'MultiPolygon', 'teste');
            $w->write(['nome' => 'Sofala — Beira', 'n' => 1, 'area' => 12.5], $this->square());
            $w->write(['nome' => 'Zambézia', 'n' => 2, 'area' => 3.25], ['type' => 'Polygon', 'coordinates' => [[[36, -17], [37, -17], [37, -16], [36, -16], [36, -17]]]]);
            $files[$k] = $w->close();
        }

        return $files;
    }

    public function test_geometry_basics(): void
    {
        $sq = $this->square();
        $this->assertTrue(Geometry::contains($sq, 34.2, -19.8));
        $this->assertFalse(Geometry::contains($sq, 34.5, -19.5), 'dentro do buraco');
        $this->assertFalse(Geometry::contains($sq, 36, -19.5));
        $s = Geometry::simplify($sq, 0.01);
        $ring = $s['coordinates'][0][0];
        $this->assertSame($ring[0], end($ring), 'anel continua fechado');
        $area = Geometry::areaKm2($sq);
        $this->assertGreaterThan(10000, $area); // ~1°×1° perto do equador sul ≈ 11 600 km² menos o buraco
        $this->assertLessThan(12500, $area);
    }

    public function test_writers_produce_valid_files(): void
    {
        $dir = sys_get_temp_dir().'/gf_writers_'.uniqid();
        mkdir($dir);
        $files = $this->writeAll($dir);

        // GeoJSON: válido e exterior anti-horário (RFC 7946)
        $gj = json_decode(file_get_contents("{$dir}/teste.geojson"), true);
        $this->assertCount(2, $gj['features']);
        $this->assertGreaterThan(0, Geometry::signedArea($gj['features'][0]['geometry']['coordinates'][0][0]));
        $this->assertLessThan(0, Geometry::signedArea($gj['features'][0]['geometry']['coordinates'][0][1]), 'buraco no sentido oposto');
        $this->assertSame('Sofala — Beira', $gj['features'][0]['properties']['nome']);

        // GeoPackage: identificação, conteúdo e cabeçalho da geometria
        $db = new SQLite3("{$dir}/teste.gpkg");
        $this->assertSame(1196444487, $db->querySingle('PRAGMA application_id'));
        $this->assertSame(10400, $db->querySingle('PRAGMA user_version'));
        $this->assertSame('ok', $db->querySingle('PRAGMA integrity_check'));
        $this->assertSame(2, $db->querySingle('SELECT COUNT(*) FROM teste'));
        $this->assertSame('MULTIPOLYGON', $db->querySingle("SELECT geometry_type_name FROM gpkg_geometry_columns WHERE table_name='teste'"));
        $this->assertSame('GP', substr($db->querySingle('SELECT geom FROM teste WHERE fid = 1'), 0, 2));
        $this->assertSame(34.0, $db->querySingle("SELECT min_x FROM gpkg_contents WHERE table_name='teste'"));
        $db->close();

        // Shapefile: código 9994, comprimento declarado = tamanho real, tipo 5, 2 registos no DBF
        $shp = file_get_contents("{$dir}/teste.shp");
        $this->assertSame(9994, unpack('N', substr($shp, 0, 4))[1]);
        $this->assertSame(strlen($shp), unpack('N', substr($shp, 24, 4))[1] * 2);
        $this->assertSame(5, unpack('V', substr($shp, 32, 4))[1]);
        $shx = file_get_contents("{$dir}/teste.shx");
        $this->assertSame(100 + 2 * 8, strlen($shx));
        $dbf = file_get_contents("{$dir}/teste.dbf");
        $this->assertSame(2, unpack('V', substr($dbf, 4, 4))[1]);
        $this->assertSame(chr(0x1A), substr($dbf, -1));
        $this->assertSame('UTF-8', file_get_contents("{$dir}/teste.cpg"));
        $this->assertStringContainsString('WGS_1984', file_get_contents("{$dir}/teste.prj"));

        // CSV com BOM e cabeçalho
        $this->assertStringStartsWith("\xEF\xBB\xBFnome,n,area", file_get_contents("{$dir}/teste.csv"));
        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }

    protected function dataset(array $over = []): Dataset
    {
        return Dataset::create(array_merge([
            'slug' => ['pt' => 'limites-teste', 'en' => 'test-boundaries'],
            'title' => ['pt' => 'Limites de teste', 'en' => 'Test boundaries'],
            'summary' => ['pt' => 'Resumo PT', 'en' => 'Summary EN'],
            'description' => ['pt' => '<p>Descrição</p>'],
            'theme' => 'boundaries', 'region' => 'sofala', 'year_start' => 2019, 'year_end' => 2019,
            'source_name' => 'Fonte X', 'source_url' => 'https://exemplo.org', 'licence_spdx' => 'CC-BY-4.0',
            'attribution' => 'Atribuição exacta © Fonte X', 'status' => 'published', 'hosting' => 'hosted',
            'preview_geojson' => json_encode(['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'properties' => [], 'geometry' => $this->square()]]]),
        ], $over));
    }

    public function test_packager_builds_zip_with_readme_licence_citation(): void
    {
        Storage::fake('datasets');
        $d = $this->dataset();
        $dir = sys_get_temp_dir().'/gf_pkg_'.uniqid();
        mkdir($dir);
        $files = $this->writeAll($dir);
        $f = (new DatasetPackager)->package($d, 'shp', $files['shp'], 'teste_shp.zip', ['pt' => 'Sofala'], ['nome' => ['pt' => 'nome', 'en' => 'name']]);

        $this->assertSame(64, strlen($f->sha256));
        $zipPath = Storage::disk('datasets')->path($f->path);
        $this->assertSame(hash_file('sha256', $zipPath), $f->sha256);
        $this->assertSame(filesize($zipPath), (int) $f->size_bytes);
        $zip = new ZipArchive;
        $zip->open($zipPath);
        foreach (['teste.shp', 'teste.shx', 'teste.dbf', 'teste.prj', 'teste.cpg', 'LEIAME.txt', 'LICENCA.txt', 'CITACAO.txt'] as $name) {
            $this->assertNotFalse($zip->locateName($name), "falta {$name}");
        }
        $readme = $zip->getFromName('LEIAME.txt');
        foreach (['PORTUGUÊS', 'FRANÇAIS', 'ENGLISH', 'Atribuição exacta © Fonte X', 'Shapefile', 'SHA-256'] as $s) {
            $this->assertStringContainsString($s, $readme);
        }
        $this->assertStringContainsString('CC-BY-4.0', $zip->getFromName('LICENCA.txt'));
        $zip->close();
        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }

    protected function withFile(Dataset $d): DatasetFile
    {
        Storage::fake('datasets');
        Storage::disk('datasets')->put('files/limites-teste/teste_csv.zip', 'PK-conteudo-de-teste');

        return DatasetFile::create(['dataset_id' => $d->id, 'format' => 'csv', 'disk' => 'datasets',
            'path' => 'files/limites-teste/teste_csv.zip', 'filename' => 'teste_csv.zip', 'label' => ['pt' => 'Tabela']]);
    }

    public function test_catalogue_filters_and_record(): void
    {
        $d = $this->dataset();
        $file = $this->withFile($d);
        $this->dataset(['slug' => ['pt' => 'outro'], 'title' => ['pt' => 'Fogo'], 'theme' => 'fire', 'hosting' => 'external']);
        $this->dataset(['slug' => ['pt' => 'rascunho'], 'title' => ['pt' => 'Rascunho escondido'], 'status' => 'draft']);

        $this->get('/dados')->assertOk()->assertSee('Limites de teste')->assertSee('Fogo')->assertDontSee('Rascunho escondido');
        $this->get('/dados?tema=fire')->assertOk()->assertSee('Fogo')->assertDontSee('Limites de teste');
        $this->get('/dados?formato=csv')->assertOk()->assertSee('Limites de teste')->assertDontSee('>Fogo<', false);
        $this->get('/dados?de=2020')->assertOk()->assertDontSee('Limites de teste');

        $html = $this->get('/dados/limites-teste')->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"Dataset"', $html);
        $this->assertStringContainsString('"temporalCoverage":"2019-01-01/2019-12-31"', $html);
        $this->assertStringContainsString('/descarregar/'.$file->id.'/teste_csv.zip', $html);
        $this->assertStringContainsString('class="preview-map"', $html);
        $this->assertStringContainsString('Atribuição exacta © Fonte X', $html);
        $this->assertStringContainsString('hreflang="en" href="https://teste.local/en/data/test-boundaries"', $html);
        $this->get('/dados/rascunho')->assertNotFound();
    }

    public function test_download_streams_and_counts_anonymously(): void
    {
        $d = $this->dataset();
        $file = $this->withFile($d);
        $r = $this->get("/descarregar/{$file->id}/teste_csv.zip")->assertOk();
        $this->assertStringContainsString('attachment; filename=teste_csv.zip', $r->headers->get('Content-Disposition'));
        $this->assertSame('PK-conteudo-de-teste', $r->streamedContent());
        $this->assertSame(1, $file->fresh()->download_count);

        $this->get("/descarregar/{$file->id}/outro-nome.zip")->assertNotFound();
        $d->update(['status' => 'draft']);
        $this->get("/descarregar/{$file->id}/teste_csv.zip")->assertNotFound();
        $this->assertSame(1, $file->fresh()->download_count);
    }

    public function test_slug_from_other_language_redirects_and_fallback_notice(): void
    {
        $this->dataset();
        $this->get('/en/data/limites-teste')->assertRedirect('https://teste.local/en/data/test-boundaries')->assertStatus(301);
        // Sem tradução FR: mostra o PT com aviso, sem 404
        $this->get('/fr/donnees/limites-teste')->assertOk()->assertSee('Disponible uniquement en Português');
    }
}
