<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeforestationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deforestation_monitor_page_in_three_languages_with_its_panel(): void
    {
        Storage::fake('public');
        foreach (['/mapas/desmatamento' => 'Monitor de desmatamento', '/fr/cartes/deforestation' => 'Suivi de la déforestation', '/en/maps/deforestation' => 'Deforestation monitor'] as $url => $title) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString($title, $html);
            $this->assertStringContainsString('data-tool="deforestation"', $html);
            $this->assertStringContainsString('data-dist-run', $html);
            $this->assertStringContainsString('name="dist_radar"', $html);
            $this->assertStringContainsString('hansenBase', $html);
        }
        // a política de segurança autoriza o radar (Planetary Computer) e as imagens Sentinel-2
        $csp = $this->get('/mapas/desmatamento')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://planetarycomputer.microsoft.com', $csp);
        $this->assertStringContainsString('https://sentinel1euwestrtc.blob.core.windows.net', $csp);
        $this->assertStringContainsString('https://sentinel-cogs.s3.us-west-2.amazonaws.com', $csp);
        // cartão na página de escolha e no sitemap
        $this->assertStringContainsString('img/cards/desmatamento.webp', $this->get('/mapas')->getContent());
        $this->get('/sitemap.xml')->assertSee('/mapas/desmatamento')->assertSee('/en/maps/deforestation');
        // os painéis das outras ferramentas ficam escondidos (CSS data-only); o do desmatamento só aparece na sua ferramenta
        $this->assertStringContainsString('data-only="deforestation" data-dist', $this->get('/mapas/uso-do-solo')->getContent());
    }

    public function test_below_the_map_status_examples_specifications_and_citation_on_every_tool(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        // sem verificação guardada: sem barra de estado no monitor de desmatamento (nada inventado)
        $html = $this->get('/mapas/desmatamento')->assertOk()->getContent();
        $this->assertStringNotContainsString('portal-status', $html);
        $this->assertStringContainsString('/mapas/desmatamento?bbox=36.495,-16.555,36.555,-16.495', $html);
        $this->assertStringContainsString('16.52° S · 36.52° E', $html);
        $this->assertStringContainsString('GF-DIST 1.0', $html);
        $this->assertStringContainsString('data-cite-copy', $html);

        // com a vigilância diária: datas reais (AAAA-MM-DD, UTC)
        Storage::disk('local')->put('fontes/vigilancia.json', json_encode([
            'checked_at' => '2026-11-28T04:40:00Z', 'ok' => true, 'failed' => [], 'checks' => ['terrain_tiles' => ['ok' => true]],
            's2_latest' => '2026-11-27T07:54:43Z', 's1_latest' => '2026-11-22T15:59:42Z', 'hansen' => ['version' => 'v1.13', 'last_year' => 2025],
        ]));
        $this->travelTo('2026-11-28 12:00:00');
        $html = $this->get('/en/maps/deforestation')->getContent();
        $this->assertStringContainsString('portal-status--ok', $html);
        $this->assertStringContainsString('<dt>Sentinel-1 RTC</dt><dd>2026-11-22</dd>', $html);
        $this->assertStringContainsString('<dd>2026-11-28 04:40 UTC</dd>', $html);
        $this->assertStringContainsString('v1.13 · 2001–2025', $html);

        // fonte em falha: estado honesto
        Storage::disk('local')->put('fontes/vigilancia.json', json_encode(['checked_at' => now()->toIso8601String(), 'ok' => false, 'failed' => ['sentinel1_token'], 'checks' => [], 's2_latest' => now()->toIso8601String()]));
        $this->assertStringContainsString('Fonte com falha', $this->get('/mapas/desmatamento')->getContent());

        // todas as ferramentas têm exemplos e especificações; sem frases de apresentação antigas
        foreach (['uso-do-solo' => 'Esri / Impact Observatory', 'incendios' => 'VIIRS · S-NPP, NOAA-20, NOAA-21', 'vegetacao-ndvi' => 'NDVI, composição de 8 dias', 'relevo' => 'Terrain Tiles · SRTM', 'imagem-de-satelite' => 'Reflectância corrigida'] as $slug => $spec) {
            $page = $this->get('/mapas/'.$slug)->assertOk()->getContent();
            $this->assertStringContainsString('class="portal"', $page, $slug);
            $this->assertStringContainsString($spec, $page, $slug);
            $this->assertStringContainsString('/mapas/'.$slug.'?bbox=', $page, $slug);
            $this->assertStringNotContainsString('Experimente com um exemplo', $page);
        }
    }

    public function test_hansen_bridge_passes_byte_ranges_only_for_valid_tiles(): void
    {
        Http::fake(['storage.googleapis.com/*' => fn (Request $req) => str_contains($req->url(), '80S_170W')
            ? Http::response('', 404)
            : Http::response("II*\0rest", 206, ['Content-Range' => 'bytes 0-7/110189294'])]);
        $r = $this->get('/dados-abertos/hansen/10S_030E', ['Range' => 'bytes=0-7']);
        $r->assertStatus(206)->assertHeader('Content-Range', 'bytes 0-7/110189294')->assertHeader('Content-Type', 'image/tiff');
        $this->assertSame("II*\0rest", $r->getContent());
        Http::assertSent(fn (Request $req) => str_contains($req->url(), 'Hansen_GFC-2025-v1.13_lossyear_10S_030E.tif') && $req->header('Range')[0] === 'bytes=0-7');

        $this->get('/dados-abertos/hansen/10S_030E')->assertStatus(416);                                   // sem intervalo
        $this->get('/dados-abertos/hansen/10S_030E', ['Range' => 'bytes=0-99999999'])->assertStatus(416); // demasiado grande
        $this->get('/dados-abertos/hansen/10S_030E', ['Range' => 'bytes=0-1,5-9'])->assertStatus(416);    // vários intervalos
        $this->get('/dados-abertos/hansen/..%2Fetc', ['Range' => 'bytes=0-7'])->assertNotFound();          // nome inválido
        $this->get('/dados-abertos/hansen/80S_170W', ['Range' => 'bytes=0-7'])->assertNotFound();      // oceano
    }

    public function test_daily_source_check_mails_once_when_a_source_fails_and_when_it_recovers(): void
    {
        Http::preventStrayRequests();
        Storage::fake('local');
        Mail::fake();
        $now = now('UTC')->subDay()->toIso8601String();
        $tokenDown = false;
        $tiff = fn () => Http::response("II*\0".str_repeat('x', 12), 206);
        Http::fake([
            'earth-search.aws.element84.com/*' => Http::response(['features' => [['properties' => ['datetime' => $now], 'assets' => ['nir' => ['href' => 'https://sentinel-cogs.s3.us-west-2.amazonaws.com/x/B08.tif']]]]]),
            'sentinel-cogs.s3.us-west-2.amazonaws.com/*' => $tiff,
            'planetarycomputer.microsoft.com/api/stac/*' => Http::response(['features' => [['properties' => ['datetime' => $now], 'assets' => ['vh' => ['href' => 'https://sentinel1euwestrtc.blob.core.windows.net/x/vh.tiff']]]]]),
            'planetarycomputer.microsoft.com/api/sas/*' => function () use (&$tokenDown) { return $tokenDown ? Http::response('down', 503) : Http::response(['token' => 'st=abc']); },
            'sentinel1euwestrtc.blob.core.windows.net/*' => $tiff,
            'storage.googleapis.com/*' => fn (Request $r) => str_contains($r->url(), 'download.html') ? Http::response('', 404) : $tiff(),
            'elevation-tiles-prod.s3.amazonaws.com/*' => Http::response("\x89PNG....", 200),
            'gibs.earthdata.nasa.gov/*' => Http::response("\xFF\xD8\xFF....", 200),
        ]);
        $this->artisan('geofloresta:check-sources')->assertSuccessful();
        Mail::assertNothingOutgoing();
        $state = fn () => json_decode(Storage::disk('local')->get('fontes/vigilancia.json'), true);
        $this->assertTrue($state()['ok']);

        // o token do radar deixa de funcionar: falha registada e e-mail enviado ao administrador
        $tokenDown = true;
        $this->artisan('geofloresta:check-sources')->assertFailed();
        $this->assertSame(['sentinel1_token', 'sentinel1_image'], $state()['failed']);
        $this->assertNotNull($state()['mailed_at']);
        // no dia seguinte continua a falhar: não repete o e-mail antes de 3 dias
        $mailed = $state()['mailed_at'];
        $this->artisan('geofloresta:check-sources')->assertFailed();
        $this->assertSame($mailed, $state()['mailed_at']);
        // volta a funcionar: novo e-mail («voltaram a funcionar»)
        $tokenDown = false;
        $this->artisan('geofloresta:check-sources')->assertSuccessful();
        $this->assertNotSame($mailed, $state()['mailed_at']);
    }
}
