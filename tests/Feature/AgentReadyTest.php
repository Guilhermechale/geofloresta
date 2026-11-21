<?php

namespace Tests\Feature;

use App\Models\Tutorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Preparação do site para agentes de IA: robots.txt, llms.txt, cabeçalho Link, Markdown e dados estruturados. */
class AgentReadyTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_declares_content_signals_in_production(): void
    {
        $this->app['env'] = 'production';
        $body = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString("User-agent: *\nContent-Signal: search=yes, ai-input=yes, ai-train=no", $body);
        $this->assertStringContainsString('/llms.txt', $body);
        $this->assertStringContainsString('Sitemap: ', $body);
    }

    public function test_llms_txt_summarises_the_site(): void
    {
        Tutorial::create([
            'slug' => ['pt' => 'gee-ndvi', 'en' => 'gee-ndvi-en'], 'title' => ['pt' => 'NDVI no Earth Engine', 'en' => 'NDVI in Earth Engine'],
            'summary' => ['pt' => 'Resumo', 'en' => 'Summary'], 'body' => ['pt' => '<p>x</p>'], 'platform' => 'gee', 'status' => 'published', 'published_at' => now(),
        ]);
        $r = $this->get('/llms.txt')->assertOk();
        $this->assertStringStartsWith('text/markdown', $r->headers->get('Content-Type'));
        $md = $r->getContent();
        $this->assertStringStartsWith("# GEOFLORESTA\n\n> ", $md);
        $this->assertStringContainsString('[Land use and land cover (AI)](https://teste.local/en/maps/land-cover)', $md);
        $this->assertStringContainsString('[NDVI in Earth Engine](https://teste.local/en/tutorials/gee-ndvi-en): Summary', $md);
        $this->assertStringContainsString('## Main pages', $md);
    }

    public function test_pages_advertise_llms_and_serve_markdown_on_request(): void
    {
        Storage::fake('public');
        $html = $this->get('/');
        $html->assertOk();
        $this->assertStringContainsString('</llms.txt>; rel="describedby"', 'x'.str_replace('https://teste.local', '', $html->headers->get('Link')));
        $this->assertStringContainsString('rel="terms-of-service"', $html->headers->get('Link'));
        $this->assertStringContainsString('Accept', $html->headers->get('Vary'));
        $this->assertStringContainsString('rel="terms-of-service"', $html->getContent());

        // Um navegador continua a receber HTML
        $this->get('/', ['Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8'])->assertHeader('Content-Type', 'text/html; charset=utf-8');

        // Um agente que pede Markdown recebe Markdown do conteúdo principal
        $md = $this->get('/', ['Accept' => 'text/markdown, text/html;q=0.5']);
        $md->assertOk();
        $this->assertStringStartsWith('text/markdown', $md->headers->get('Content-Type'));
        $body = $md->getContent();
        $this->assertStringStartsWith('# GEOFLORESTA', $body);
        $this->assertStringNotContainsString('<html', $body);
        $this->assertStringContainsString('(https://teste.local/mapas/uso-do-solo)', $body);
        $this->assertSame('noindex', $md->headers->get('X-Robots-Tag'));
    }

    public function test_structured_data_on_home_services_and_map_tools(): void
    {
        Storage::fake('public');
        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"WebSite"', $home);
        $this->assertStringContainsString('"@type":"SearchAction"', $home);
        $this->assertStringContainsString('"@type":"ItemList"', $home);
        $this->assertStringContainsString('"urlTemplate":"https://teste.local/procurar?q={search_term_string}"', $home);

        $tool = $this->get('/mapas/uso-do-solo')->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"WebApplication"', $tool);
        $this->assertStringContainsString('"isAccessibleForFree":true', $tool);

        $this->assertStringContainsString('"@type":"ItemList"', $this->get('/mapas')->assertOk()->getContent());
    }
}
