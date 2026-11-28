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
