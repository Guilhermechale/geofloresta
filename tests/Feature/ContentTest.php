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

    public function test_old_wordpress_language_tag_and_author_urls_redirect(): void
    {
        $this->seedContent();

        // Versões inglesa/francesa do WordPress: /en/{slug-pt} -> conteúdo nessa língua
        $this->get('/en/gee-ndvi')->assertRedirect('https://teste.local/en/tutorials/gee-ndvi')->assertStatus(301);
        $this->get('/fr/perda-arborea-mocambique/')->assertRedirect('https://teste.local/fr/recherches/perda-arborea-mocambique');
        $this->get('/en/sobre')->assertRedirect('https://teste.local/en/about');
        $this->get('/en/category/centro-de-conhecimento')->assertRedirect('https://teste.local/en/tutorials');
        $this->get('/tag/incendios-florestais')->assertRedirect('https://teste.local/procurar?q=incendios%20florestais');
        $this->get('/author/guilhermechale')->assertRedirect('https://teste.local/sobre');

        // O que não existe continua a dar 404, e o 410 da migração mantém-se
        $this->get('/en/nao-existe')->assertNotFound();
        $this->get('/noticia-antiga')->assertStatus(410);
    }

    public function test_tutorial_template_has_all_parts(): void
    {
        $this->seedContent();
        Tutorial::first()->update([
            'objectives' => ['pt' => "Calcular o NDVI\nExportar o resultado"],
            'prerequisites' => ['pt' => "Conta Google\nAcesso ao Earth Engine"],
            'body' => ['pt' => '<h2>Passo 1: Abrir o editor</h2><p>Texto</p><h2>Passo 2: Calcular</h2><p>Mais</p><h2>Passo 3: Exportar</h2><p>Fim</p>'],
            'exercise' => ['pt' => '<ol><li>Troque a área de interesse</li></ol>'],
            'takeaways' => ['pt' => "O NDVI mede o vigor da vegetação"],
            'sources' => "Documentação do Earth Engine | https://developers.google.com/earth-engine\nLivro sem ligação",
            'duration_minutes' => 30,
        ]);
        $html = $this->get('/gee-ndvi')->assertOk()->getContent();
        foreach (['O que vai aprender', 'Calcular o NDVI', 'Antes de começar', 'Acesso ao Earth Engine', 'Neste tutorial', 'id="passo-1-abrir-o-editor"',
            'Exercício prático', 'Troque a área de interesse', 'Pontos-chave', 'Fontes e documentação', 'href="https://developers.google.com/earth-engine"', 'Livro sem ligação', '30 min'] as $s) {
            $this->assertStringContainsString($s, $html, $s);
        }
    }

    public function test_research_template_has_abstract_references_and_citations(): void
    {
        $this->seedContent();
        Study::first()->update([
            'authors' => 'Guilherme Francisco Fernando Chale', 'article_type' => 'review', 'year' => 2026, 'venue' => 'GEOFLORESTA, Beira',
            'abstract' => ['pt' => 'Este estudo analisa a perda.'], 'keywords' => ['pt' => 'Moçambique; Miombo'],
            'body' => ['pt' => '<h2>1. Introdução</h2><p>a</p><h2>2. Métodos</h2><p>b</p><h2>3. Resultados</h2><p>c</p>'],
            'references' => "Hansen, M. C. (2013). High-resolution global maps. Science. https://doi.org/10.1126/science.1244693\nFAO. (2020). Global Forest Resources Assessment.",
        ]);
        $html = $this->get('/perda-arborea-mocambique')->assertOk()->getContent();
        foreach (['Artigo de revisão', 'Guilherme Francisco Fernando Chale', 'Resumo', 'Este estudo analisa a perda.', 'Miombo', 'Neste artigo',
            'Referências', 'href="https://doi.org/10.1126/science.1244693"', 'Como citar este artigo', 'CHALE, Guilherme Francisco Fernando.', 'Chale, G. F. F. (2026)', 'Chale GFF.'] as $s) {
            $this->assertStringContainsString($s, $html, $s);
        }
        $this->get('/pesquisas')->assertOk()->assertSee('Artigo de revisão');
    }

    public function test_cover_images_in_lists_and_articles(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $img = new \Imagick();
        $img->newImage(1600, 900, 'green');
        $img->setImageFormat('webp');
        \Illuminate\Support\Facades\Storage::disk('public')->put('conteudo/capa-teste.webp', $img->getImagesBlob());

        $this->seedContent();
        Tutorial::first()->update(['cover_image' => 'conteudo/capa-teste.webp', 'cover_alt' => 'Mapa NDVI']);
        Study::first()->update(['cover_image' => 'conteudo/capa-teste.webp']);

        $this->get('/tutoriais')->assertOk()->assertSee('thumbs/480/conteudo/capa-teste.webp', false);
        $this->get('/pesquisas')->assertOk()->assertSee('thumbs/480/conteudo/capa-teste.webp', false);
        $this->get('/gee-ndvi')->assertOk()->assertSee('thumbs/1200/conteudo/capa-teste.webp', false)->assertSee('alt="Mapa NDVI"', false);
        $this->get('/')->assertOk()->assertSee('thumbs/320/conteudo/capa-teste.webp', false);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('thumbs/480/conteudo/capa-teste.webp');
        $this->assertSame(480, getimagesize(\Illuminate\Support\Facades\Storage::disk('public')->path('thumbs/480/conteudo/capa-teste.webp'))[0]);

        // Sem ficheiro: nenhuma imagem quebrada
        Tutorial::first()->update(['cover_image' => 'conteudo/nao-existe.webp']);
        $this->get('/tutoriais')->assertOk()->assertDontSee('nao-existe.webp', false);
    }

    public function test_about_page_shows_founder_photo(): void
    {
        $this->seed(\Database\Seeders\ContentSeeder::class);
        $this->get('/sobre')->assertOk()
            ->assertSee('img/fundador-guilherme-chale.webp', false)
            ->assertSee('Guilherme Francisco Fernando Chale');
        $this->assertFileExists(public_path('img/fundador-guilherme-chale.webp'));
    }

    public function test_about_page_has_meta_description(): void
    {
        $this->seed(\Database\Seeders\ContentSeeder::class);
        foreach (['/sobre' => 'pt', '/fr/a-propos' => 'fr', '/en/about' => 'en'] as $url => $l) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match('/<meta name="description" content="([^"]*)"/', $html, $m);
            $this->assertNotEmpty($m[1] ?? '', "descrição vazia em {$url}");
            $this->assertStringContainsString(e(__('pages.about.description', [], $l)), $html);
        }
    }

    public function test_research_has_translated_urls_and_old_urls_redirect(): void
    {
        $s = Study::create([
            'slug' => 'perfil-dos-vetores-de-desflorestamento',
            'title' => ['pt' => 'O perfil dos vetores de desflorestamento', 'fr' => 'Le profil des moteurs de la déforestation', 'en' => 'The profile of deforestation drivers'],
            'summary' => ['pt' => 'Resumo', 'fr' => 'Résumé', 'en' => 'Summary'], 'body' => ['pt' => '<p>Texto</p>', 'fr' => '<p>Texte</p>', 'en' => '<p>Text</p>'],
            'status' => 'published', 'published_at' => '2026-11-02 10:00:00',
        ]);
        $this->assertSame(['fr' => 'le-profil-des-moteurs-de-la-deforestation', 'en' => 'the-profile-of-deforestation-drivers'], $s->fresh()->slugs);

        $this->get('/en/research/the-profile-of-deforestation-drivers')->assertOk()->assertSee('The profile of deforestation drivers');
        $this->get('/fr/recherches/le-profil-des-moteurs-de-la-deforestation')->assertOk();
        // Endereço antigo (slug português) -> novo, com 301
        $this->get('/en/research/perfil-dos-vetores-de-desflorestamento')->assertStatus(301)->assertRedirect('https://teste.local/en/research/the-profile-of-deforestation-drivers');
        $this->get('/fr/recherches/perfil-dos-vetores-de-desflorestamento')->assertStatus(301)->assertRedirect('https://teste.local/fr/recherches/le-profil-des-moteurs-de-la-deforestation');
        // PT mantém o URL do WordPress; listagem e sitemap usam os novos endereços
        $this->get('/perfil-dos-vetores-de-desflorestamento')->assertOk();
        $this->get('/en/research')->assertSee('/en/research/the-profile-of-deforestation-drivers', false);
        $this->get('/sitemap.xml')->assertSee('/fr/recherches/le-profil-des-moteurs-de-la-deforestation', false);
        // Mudar o título depois não muda o endereço
        $s->fresh()->update(['title' => ['pt' => 'O perfil dos vetores de desflorestamento', 'fr' => 'Autre titre', 'en' => 'Another title']]);
        $this->assertSame('the-profile-of-deforestation-drivers', $s->fresh()->slugs['en']);
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
