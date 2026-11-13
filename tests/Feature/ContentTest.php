<?php

namespace Tests\Feature;

use App\Models\Redirect;
use App\Models\Study;
use App\Models\Tutorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    protected function seedContent(): void
    {
        Tutorial::create([
            'slug' => ['pt' => 'gee-ndvi'], 'legacy_slug' => 'gee-ndvi', 'title' => ['pt' => 'Calcular NDVI no Earth Engine'],
            'summary' => ['pt' => 'Resumo do tutorial'], 'body' => ['pt' => '<p>Texto</p><pre><code class="language-javascript">var x = 1;</code></pre>'],
            'platform' => 'gee', 'status' => 'published', 'published_at' => '2026-10-30 10:00:00',
        ]);
        Study::create([
            'slug' => 'perda-arborea-mocambique', 'title' => ['pt' => 'Perda de cobertura arbórea em Moçambique'],
            'summary' => ['pt' => 'Resumo da pesquisa'], 'body' => ['pt' => '<p>Método e resultados</p>'],
            'status' => 'published', 'published_at' => '2026-11-02 10:00:00',
        ]);
        Redirect::create(['from_path' => '/noticia-antiga', 'status_code' => 410, 'source' => 'migration']);
    }

    public function test_two_categories_and_legacy_urls(): void
    {
        $this->seedContent();

        $this->get('/tutoriais')->assertOk()->assertSee('Calcular NDVI no Earth Engine');
        $this->get('/pesquisas')->assertOk()->assertSee('Perda de cobertura arbórea em Moçambique');

        // URLs do WordPress mantidos (português, na raiz)
        $html = $this->get('/gee-ndvi')->assertOk()->getContent();
        $this->assertStringContainsString('<h1>Calcular NDVI no Earth Engine</h1>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://teste.local/gee-ndvi">', $html);
        $this->get('/gee-ndvi/')->assertOk(); // com barra final (o .htaccess do servidor faz 301 para sem barra)
        $this->get('/perda-arborea-mocambique')->assertOk()->assertSee('Método e resultados', false)->assertSee('"ScholarlyArticle"', false);

        // Outros idiomas: mesmo conteúdo com aviso de idioma
        $this->get('/en/tutorials/gee-ndvi')->assertOk()->assertSee('Available in Português only');
        $this->get('/fr/recherches/perda-arborea-mocambique')->assertOk()->assertSee('Disponible uniquement en Português');
        // Em PT o URL da categoria redirecciona para o URL antigo
        $this->get('/tutoriais/gee-ndvi')->assertRedirect('https://teste.local/gee-ndvi');
        $this->get('/pesquisas/perda-arborea-mocambique')->assertRedirect('https://teste.local/perda-arborea-mocambique');

        $this->get('/noticia-antiga')->assertStatus(410);
        $this->get('/')->assertOk()->assertSee('Calcular NDVI no Earth Engine')->assertSee('Perda de cobertura arbórea em Moçambique');
    }

    public function test_navigation_has_only_the_two_content_categories(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('>Tutoriais</a>', $html);
        $this->assertStringContainsString('>Pesquisas</a>', $html);
        $this->assertStringNotContainsString('Cursos', $html);
        $this->assertStringNotContainsString('Notícias', $html);
        $this->assertStringNotContainsString('Estudos publicados', $html);
    }
}
