<?php

namespace Tests\Feature;

use App\Geo\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CountriesTest extends TestCase
{
    use RefreshDatabase;

    protected function world(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $c = fn ($iso, $pt, $en, $fr, $cont, $bbox) => ['iso' => $iso, 'iso2' => substr($iso, 0, 2), 'names' => ['pt' => $pt, 'en' => $en, 'fr' => $fr], 'continent' => $cont, 'subregion' => '', 'bbox' => $bbox];
        Storage::disk('local')->put(World::LIST, json_encode([
            'MOZ' => $c('MOZ', 'Moçambique', 'Mozambique', 'Mozambique', 'Africa', [30.2, -26.9, 40.8, -10.5]),
            'BRA' => $c('BRA', 'Brasil', 'Brazil', 'Brésil', 'South America', [-74, -34, -34.8, 5.3]),
            'PRT' => $c('PRT', 'Portugal', 'Portugal', 'Portugal', 'Europe', [-9.5, 36.9, -6.2, 42.2]),
        ]));
        Storage::disk('local')->put(World::FIRES_SUMMARY, json_encode([
            'updated_at' => '2026-11-13T06:00:00Z', 'sources' => [],
            'countries' => ['BRA' => ['n24' => 120, 'n7' => 900, 'latest' => '2026-11-13T04:10:00Z'], 'MOZ' => ['n24' => 40, 'n7' => 300, 'latest' => '2026-11-13T01:00:00Z']],
        ]));
        Storage::disk('public')->put(World::firesPath('BRA', 'csv'), "data_hora_utc,satelite\n");
        Storage::disk('public')->put(World::firesPath('BRA', 'json'), json_encode(['updated_at' => 'x', 'sats' => ['VIIRS NOAA-20'], 'rows' => [[-50, -10, '2026-11-13T04:10', 0, 'n', 3.2]]]));
        Storage::disk('public')->put(World::outlinePath('BRA'), json_encode(['type' => 'FeatureCollection', 'features' => []]));
        Http::fake([
            'www.geoboundaries.org/*/ADM1/' => Http::response(['gjDownloadURL' => 'https://github.com/x/geoBoundaries-BRA-ADM1.geojson', 'staticDownloadLink' => 'https://github.com/x/BRA-ADM1-all.zip', 'boundaryLicense' => 'CC BY 4.0', 'admUnitCount' => 27, 'boundaryYearRepresented' => 2020]),
            'www.geoboundaries.org/*' => Http::response('', 404),
            'gfw2-data.s3.amazonaws.com/*' => Http::response('', 200),
            'hub.worldpop.org/*' => Http::response(['data' => [['popyear' => '2020', 'files' => ['https://data.worldpop.org/bra_ppp_2020.tif']]]]),
        ]);
    }

    public function test_country_list_in_three_languages_with_search(): void
    {
        $this->world();
        $this->get('/dados/paises')->assertOk()->assertSee('Dados por país')->assertSee('Brasil')->assertSee('Portugal')->assertSee('América do Sul');
        $this->get('/dados/paises?q=bras')->assertOk()->assertSee('Brasil')->assertDontSee('>Portugal<', false);
        $this->get('/en/data/countries')->assertOk()->assertSee('Data by country')->assertSee('Brazil');
        $this->get('/fr/donnees/pays')->assertOk()->assertSee('Données par pays')->assertSee('Brésil');
    }

    public function test_country_page_lists_open_sources_and_downloads(): void
    {
        $this->world();
        $html = $this->get('/dados/paises/bra')->assertOk()
            ->assertSee('Dados abertos: Brasil')
            ->assertSee('geoBoundaries-BRA-ADM1.geojson')
            ->assertSee('HydroRIVERS_v10_sa_shp.zip')
            ->assertSee('hybas_sa_lev01-12_v1c.zip')
            ->assertSee('country_stats/download/BRA.xlsx')
            ->assertSee('bra_ppp_2020.tif')
            ->assertSee('storage/geo/fires/BRA.csv')
            ->assertSee('?pais=bra')
            ->getContent();
        $this->assertStringContainsString('"@type":"DataCatalog"', $html);
        $this->get('/en/data/countries/bra')->assertOk()->assertSee('Open data: Brazil');
        $this->get('/dados/paises/xyz')->assertNotFound();
    }

    public function test_map_works_for_any_country(): void
    {
        $this->world();
        $html = $this->get('/mapas?pais=bra')->assertOk()->assertSee('Usar o país inteiro')->getContent();
        $this->assertStringContainsString('"iso":"BRA"', $html);
        $this->assertStringContainsString('"compact":true', $html);
        $this->assertStringContainsString('geo\/fires\/BRA.json', str_replace('/', '\/', $html));
        $this->assertStringContainsString('<option value="bra" selected>Brasil</option>', $html);
        $this->assertStringNotContainsString('name="province"', $html);
        // País desconhecido: volta a Moçambique
        $this->get('/mapas?pais=zzz')->assertOk()->assertSee('"iso":"MOZ"', false);
    }

    public function test_territories_do_not_take_the_code_of_their_country(): void
    {
        $iso = fn (array $p) => \App\Console\Commands\PrepareWorld::isoOf($p);
        $this->assertSame('AUS', $iso(['iso_a3' => 'AUS', 'iso_a3_eh' => 'AUS', 'adm0_a3' => 'AUS']));
        $this->assertSame('ATC', $iso(['iso_a3' => '-99', 'iso_a3_eh' => 'AUS', 'adm0_a3' => 'ATC'])); // Ilhas Ashmore e Cartier
        $this->assertSame('FRA', $iso(['iso_a3' => '-99', 'iso_a3_eh' => 'FRA', 'adm0_a3' => 'FRA']));
        $this->assertSame('KOS', $iso(['iso_a3' => '-99', 'iso_a3_eh' => 'XKX', 'adm0_a3' => 'KOS']));
    }

    public function test_sitemap_lists_country_pages(): void
    {
        $this->world();
        $this->get('/sitemap.xml')->assertOk()->assertSee('/dados/paises/bra')->assertSee('/en/data/countries/prt');
    }
}
