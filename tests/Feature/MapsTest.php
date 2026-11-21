<?php

namespace Tests\Feature;

use App\Console\Commands\UpdateFirms;
use App\Console\Commands\UpdateGibsLayers;
use App\Support\FireSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MapsTest extends TestCase
{
    use RefreshDatabase;

    protected function layers(): void
    {
        $sofala = ['type' => 'Polygon', 'coordinates' => [[[33, -21], [36, -21], [36, -17], [33, -17], [33, -21]]]];
        $beira = ['type' => 'Polygon', 'coordinates' => [[[34.5, -20], [35, -20], [35, -19.5], [34.5, -19.5], [34.5, -20]]]];
        Storage::disk('public')->put('geo/mz-provincias.geojson', json_encode(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['nome' => 'Sofala', 'codigo' => 'MZ-S'], 'geometry' => $sofala],
        ]]));
        Storage::disk('public')->put('geo/mz-distritos.geojson', json_encode(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['nome' => 'Beira', 'provincia' => 'Sofala'], 'geometry' => $beira],
        ]]));
    }

    public function test_firms_command_builds_cache_without_key(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->layers();
        $now = now('UTC');
        $d = fn ($h) => $now->copy()->subHours($h);
        $csv = "latitude,longitude,bright_ti4,scan,track,acq_date,acq_time,satellite,confidence,version,bright_ti5,frp,daynight\n"
            ."-19.8,34.8,330,0.4,0.4,{$d(2)->toDateString()},{$d(2)->format('Hi')},N20,n,2.0NRT,290,5.2,D\n"     // Beira, 2 h
            ."-18.0,35.5,330,0.4,0.4,{$d(30)->toDateString()},{$d(30)->format('Hi')},N20,h,2.0NRT,290,12.1,N\n"  // Sofala, 30 h
            ."-25.0,25.0,330,0.4,0.4,{$d(1)->toDateString()},{$d(1)->format('Hi')},N20,n,2.0NRT,290,3,D\n";      // fora de Moçambique
        Http::fake([
            '*J1_VIIRS*' => Http::response($csv),
            '*J2_VIIRS*' => Http::response("latitude,longitude,bright_ti4,scan,track,acq_date,acq_time,satellite,confidence,version,bright_ti5,frp,daynight\n"),
            '*SUOMI*' => Http::response('', 404),
        ]);

        $this->artisan('geofloresta:firms')->assertSuccessful();

        $fc = json_decode(Storage::disk('public')->get(UpdateFirms::PUBLIC_PATH), true);
        $this->assertCount(2, $fc['features']);
        $first = $fc['features'][0]['properties'];
        $this->assertSame(['Sofala', 'Beira', 'VIIRS NOAA-20'], [$first['prov'], $first['dist'], $first['sat']]);
        $s = FireSummary::read();
        $this->assertSame(1, $s['count_24h']);
        $this->assertSame(2, $s['count_7d']);
        $this->assertSame(['Sofala' => 2], $s['by_province_7d']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/api/'));
    }

    public function test_firms_keeps_previous_cache_when_all_sources_fail(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->layers();
        Storage::disk('local')->put(FireSummary::PATH, json_encode(['count_24h' => 9, 'updated_at' => '2026-11-01T00:00:00Z']));
        Http::fake(['*' => Http::response('', 500)]);
        $this->artisan('geofloresta:firms')->assertFailed();
        $this->assertSame(9, FireSummary::read()['count_24h']);
    }

    public function test_gibs_command_reads_capabilities(): void
    {
        Storage::fake('local');
        $layer = fn ($id, $set, $fmt, $ext, $time) => <<<XML
            <Layer><ows:Title>{$id}</ows:Title><ows:Identifier>{$id}</ows:Identifier>
            <Dimension><ows:Identifier>Time</ows:Identifier><Default>{$time}</Default></Dimension>
            <TileMatrixSetLink><TileMatrixSet>{$set}</TileMatrixSet></TileMatrixSetLink><Format>{$fmt}</Format>
            <ResourceURL format="{$fmt}" resourceType="tile" template="https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/{$id}/default/{Time}/{TileMatrixSet}/{TileMatrix}/{TileRow}/{TileCol}.{$ext}"/></Layer>
            XML;
        $xml = '<?xml version="1.0"?><Capabilities xmlns="http://www.opengis.net/wmts/1.0" xmlns:ows="http://www.opengis.net/ows/1.1"><Contents>'
            .$layer('VIIRS_NOAA20_CorrectedReflectance_TrueColor', 'GoogleMapsCompatible_Level9', 'image/jpeg', 'jpeg', '2026-11-12')
            .$layer('MODIS_Terra_CorrectedReflectance_TrueColor', 'GoogleMapsCompatible_Level9', 'image/jpeg', 'jpeg', '2026-11-10')
            .$layer('MODIS_Terra_NDVI_8Day', 'GoogleMapsCompatible_Level9', 'image/png', 'png', '2026-11-05')
            .'</Contents></Capabilities>';
        Http::fake([UpdateGibsLayers::CAPABILITIES => Http::response($xml)]);

        $this->artisan('geofloresta:gibs')->assertSuccessful();
        $cfg = UpdateGibsLayers::read();
        $this->assertSame('VIIRS_NOAA20_CorrectedReflectance_TrueColor', $cfg['layers']['truecolor']['id']); // o mais recente
        $this->assertSame(9, $cfg['layers']['truecolor']['maxzoom']);
        $this->assertStringEndsWith('/{time}/GoogleMapsCompatible_Level9/{z}/{y}/{x}.jpeg', $cfg['layers']['truecolor']['tiles']);
        $this->assertSame('MODIS_Terra_NDVI_8Day', $cfg['layers']['ndvi']['id']);
        $this->assertArrayNotHasKey('thermal', $cfg['layers']);
    }

    public function test_maps_page_in_three_languages(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        // Sem cache: a página funciona e explica
        $this->get('/mapas/incendios')->assertOk()->assertSee('Ainda não há focos em cache');

        $this->layers();
        Storage::disk('local')->put(FireSummary::PATH, json_encode([
            'count_24h' => 3, 'count_7d' => 10, 'by_province_24h' => ['Sofala' => 3], 'by_province_7d' => ['Sofala' => 10],
            'latest_acq' => '2026-11-12T23:28:00Z', 'updated_at' => '2026-11-13T04:42:00Z', 'sources' => [],
        ]));
        $html = $this->get('/mapas/incendios')->assertOk()->getContent();
        $this->assertStringContainsString('id="map-config"', $html);
        $this->assertStringContainsString('tiles.openfreemap.org/styles/positron', $html);
        $this->assertStringContainsString('OpenFreeMap © OpenMapTiles Data from OpenStreetMap', $html);
        $this->assertStringContainsString("Global Imagery Browse Services (GIBS)", $html);
        $this->assertStringContainsString('<option value="Sofala">Sofala</option>', $html);
        $this->assertStringNotContainsString('FIRMS_MAP_KEY', $html);
        $this->get('/en/maps/fires')->assertOk()->assertSee('Detections by province');
        $this->get('/fr/cartes/incendies')->assertOk()->assertSee('Foyers par province');
        // A página inicial leva ao monitor por um cartão
        $this->get('/')->assertOk()->assertSee('https://teste.local/mapas/incendios', false);
    }

    public function test_maps_page_has_relief_layers_and_dem_download(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $r = $this->get('/mapas/relevo')->assertOk();
        $html = $r->getContent();
        // Camadas de relevo e bloco de download por área
        $this->assertStringContainsString('name="hillshade"', $html);
        $this->assertStringContainsString('name="relief"', $html);
        $this->assertStringContainsString('data-dem-build', $html);
        $this->assertStringContainsString('Curvas de nível (GeoJSON)', $html);
        $this->assertStringContainsString('elevation-tiles-prod.s3.amazonaws.com/terrarium/{z}/{x}/{y}.png', $html);
        $this->assertStringContainsString('United States 3DEP (formerly NED) and global GMTED2010 and SRTM', $html);
        // A política de segurança permite os mosaicos de elevação (imagens e pedidos do trabalhador)
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression('/img-src[^;]*elevation-tiles-prod\.s3\.amazonaws\.com/', $csp);
        $this->assertMatchesRegularExpression('/connect-src[^;]*elevation-tiles-prod\.s3\.amazonaws\.com/', $csp);
        $this->assertMatchesRegularExpression("/worker-src 'self'/", $csp);
        $this->get('/en/maps/terrain')->assertOk()->assertSee('Relief (hillshade)')->assertSee('Contour lines (GeoJSON)');
        $this->get('/fr/cartes/relief')->assertOk()->assertSee('Relief (ombrage)')->assertSee('Courbes de niveau (GeoJSON)');
    }

    public function test_maps_page_has_land_cover_classifier_with_field_validation_warning(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $r = $this->get('/mapas/uso-do-solo')->assertOk();
        $html = $r->getContent();
        $this->assertStringContainsString('data-lulc-run', $html);
        $this->assertStringContainsString('Uso e cobertura do solo (inteligência artificial)', $html);
        $this->assertStringContainsString('Mapa não validado com dados de campo.', $html);
        $this->assertStringContainsString('Impact Observatory, Microsoft e Esri', $html);
        $this->assertStringContainsString('name="lulc_trees"', $html);
        // Guia «Como usar», estado da área e botões de descarga claros
        $this->assertStringContainsString('Como usar', $html);
        $this->assertStringContainsString('De 1 a 1 000 km²: píxel de 20 m', $html);
        $this->assertStringContainsString('10 000 km² (máximo)', $html);
        $this->assertStringContainsString('Pedir proposta', $html);
        $this->assertStringContainsString('data-lulc-area', $html);
        $this->assertStringContainsString('Baixar mapa (GeoTIFF)', $html);
        $this->assertStringContainsString('Baixar tabela (CSV)', $html);
        $this->assertMatchesRegularExpression('/class="btn btn--primary" data-lulc-run disabled/', $html);
        // Os textos do painel chegam ao JavaScript (classes incluídas)
        $this->assertStringContainsString('"lulc_classes":["Água","Árvores"', $html);
        // A política de segurança permite apenas os três serviços de dados abertos usados pelo trabalhador
        $csp = $r->headers->get('Content-Security-Policy');
        foreach (['earth-search\.aws\.element84\.com', 'sentinel-cogs\.s3\.us-west-2\.amazonaws\.com', 'ic\.imagery1\.arcgis\.com'] as $host) {
            $this->assertMatchesRegularExpression("/connect-src[^;]*{$host}/", $csp);
        }
        $this->get('/en/maps/land-cover')->assertOk()->assertSee('Map not validated with field data.')->assertSee('Classify');
        $this->get('/fr/cartes/occupation-du-sol')->assertOk()->assertSee('Carte non validée par des données de terrain.')->assertSee('Classer');
    }

    public function test_maps_hub_shows_tool_cards_and_each_tool_only_its_own_panels(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->layers();
        Storage::disk('local')->put(FireSummary::PATH, json_encode([
            'count_24h' => 3, 'count_7d' => 10, 'by_province_24h' => ['Sofala' => 3], 'by_province_7d' => ['Sofala' => 10],
            'latest_acq' => '2026-11-12T23:28:00Z', 'updated_at' => '2026-11-13T04:42:00Z', 'sources' => [],
        ]));

        // Página de escolha: cinco cartões, sem informação de incêndios
        $hub = $this->get('/mapas')->assertOk()->getContent();
        foreach (['uso-do-solo', 'incendios', 'vegetacao-ndvi', 'relevo', 'imagem-de-satelite'] as $slug) {
            $this->assertStringContainsString('href="https://teste.local/mapas/'.$slug.'"', $hub);
        }
        $this->assertStringContainsString('img/cards/uso-do-solo.webp', $hub);
        $this->assertStringNotContainsString('Focos por província', $hub);
        $this->assertStringNotContainsString('map-config', $hub);

        // Uso do solo: painel de classificação, sem estatísticas nem camada de incêndios
        $lulc = $this->get('/mapas/uso-do-solo')->assertOk()->getContent();
        $this->assertStringContainsString('data-tool="lulc"', $lulc);
        $this->assertStringContainsString('"tool":"lulc"', $lulc);
        $this->assertStringNotContainsString('Focos por província', $lulc);
        $this->assertStringNotContainsString('name="fires"', $lulc);
        $this->assertStringContainsString('aria-current="page"', $lulc);

        // NDVI liga a camada de vegetação; relevo liga o sombreamento
        $layer = fn ($id) => ['id' => $id, 'maxzoom' => 9, 'default_time' => '2026-11-19', 'tiles' => 'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/'.$id.'/default/{time}/x/{z}/{y}/{x}.png'];
        Storage::disk('local')->put(\App\Console\Commands\UpdateGibsLayers::PATH, json_encode(['layers' => ['truecolor' => $layer('VIIRS'), 'ndvi' => $layer('MODIS_Terra_NDVI_8Day')]]));
        $this->assertMatchesRegularExpression('/name="ndvi" checked/', $this->get('/mapas/vegetacao-ndvi')->getContent());
        $this->assertMatchesRegularExpression('/name="truecolor" checked/', $this->get('/mapas/imagem-de-satelite')->getContent());
        $this->assertDoesNotMatchRegularExpression('/name="truecolor" checked/', $this->get('/mapas/vegetacao-ndvi')->getContent());
        $this->assertMatchesRegularExpression('/name="hillshade" checked/', $this->get('/mapas/relevo')->getContent());

        // Ferramenta desconhecida: 404; endereços traduzidos e no sitemap
        $this->get('/mapas/nao-existe')->assertNotFound();
        $this->get('/en/maps/satellite-image')->assertOk();
        $this->get('/sitemap.xml')->assertOk()->assertSee('/mapas/uso-do-solo')->assertSee('/en/maps/land-cover')->assertSee('/fr/cartes/incendies');
    }
}
