<?php

namespace Tests\Feature;

use App\Models\Tutorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialAndVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_has_video_facade_without_loading_youtube(): void
    {
        $r = $this->get('/')->assertOk();
        $html = $r->getContent();
        $this->assertStringContainsString('data-video-facade', $html);
        $this->assertStringContainsString('data-video-id="1annxj00NGI"', $html);
        $this->assertStringContainsString('img/video-como-usar.webp', $html);
        // Antes do clique não há nenhum pedido ao YouTube na página
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('youtube.com/embed', $html);
        // Dados estruturados: vídeo e perfis nas redes
        $this->assertStringContainsString('"@type":"VideoObject"', $html);
        $this->assertStringContainsString('"duration":"PT1M36S"', $html);
        $this->assertStringContainsString('"sameAs":["https://www.youtube.com/@geofloresta.oficial","https://www.linkedin.com/company/geofloresta/"', $html);
        $this->assertMatchesRegularExpression('/frame-src[^;]*https:\/\/www\.youtube-nocookie\.com/', $r->headers->get('Content-Security-Policy'));
        $this->get('/en')->assertOk()->assertSee('How to use GEOFLORESTA')->assertSee('data-video-lang="en"', false);
        $this->get('/fr')->assertOk()->assertSee('Comment utiliser GEOFLORESTA');
        // Só a página inicial tem o VideoObject
        $this->assertStringNotContainsString('VideoObject', $this->get('/contacto')->getContent());
    }

    public function test_social_links_in_footer_contact_and_tutorials(): void
    {
        $footer = $this->get('/servicos')->assertOk()->getContent();
        foreach (['youtube.com/@geofloresta.oficial', 'linkedin.com/company/geofloresta', 'facebook.com/share/14ryi5F7Thc', 'tiktok.com/@geofloresta.oficial', 'whatsapp.com/channel/0029VbDxkEgEQIakSWvSS31N'] as $u) {
            $this->assertStringContainsString($u, $footer);
        }
        $this->assertStringContainsString('aria-label="GEOFLORESTA no YouTube (abre noutro separador)"', $footer);
        $this->assertStringContainsString('rel="noopener me"', $footer);

        $this->get('/contacto')->assertOk()->assertSee('Siga a GEOFLORESTA')->assertSee('Canal no WhatsApp');

        Tutorial::create([
            'slug' => ['pt' => 'teste'], 'title' => ['pt' => 'Tutorial de teste'], 'body' => ['pt' => '<p>Texto</p>'],
            'platform' => 'qgis', 'status' => 'published', 'published_at' => '2026-11-01 10:00:00',
        ]);
        $this->get('/tutoriais/teste')->assertOk()->assertSee('Receba os novos tutoriais no WhatsApp')->assertSee('Seguir o canal no WhatsApp');
    }
}
