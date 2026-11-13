<?php

namespace Tests\Feature;

use App\Console\Commands\ImportGfw;
use App\Support\ArticleBody;
use App\Support\Charts\Figures;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticleBodyTest extends TestCase
{
    protected function fakeGfw(): void
    {
        Storage::fake('local');
        Cache::flush();
        $years = range(2001, 2025);
        $series = fn ($base) => array_map(fn ($i) => $base + $i * 1000, array_keys($years));
        Storage::disk('local')->put(ImportGfw::PATH, json_encode([
            'imported_at' => '2026-11-13',
            'years' => $years,
            'loss' => [
                10 => ['extent_2000' => 70e6, 'extent_2010' => 68e6, 'annual' => $series(200000)],
                30 => ['extent_2000' => 28e6, 'extent_2010' => 27e6, 'annual' => $series(150000)],
                50 => ['extent_2000' => 11e6, 'extent_2010' => 10e6, 'annual' => $series(80000)],
            ],
            'drivers' => [
                'Agricultura permanente' => $series(100000),
                'Agricultura itinerante' => $series(30000),
                'Incêndios florestais' => $series(1000),
            ],
            'provinces_loss_30' => ['Zambézia' => 900000, 'Niassa' => 700000],
        ]));
    }

    public function test_chart_marker_becomes_figure_with_caption_and_data_table(): void
    {
        $this->fakeGfw();
        $html = ArticleBody::render('<p>Texto.</p><p>[grafico:gfw-perda-anual-limiares]</p><p>Figura 1. Perda anual. Fonte: Hansen et al. (2013).</p><p>Depois.</p>');

        $this->assertStringContainsString('<figure class="figure figure--chart">', $html);
        $this->assertStringContainsString('chart__svg--wide', $html);
        $this->assertStringContainsString('chart__svg--compact', $html);
        $this->assertStringContainsString('<figcaption><strong>Figura 1.</strong> Perda anual.', $html);
        $this->assertStringContainsString('Limiar 50%', $html);
        $this->assertStringContainsString('data-tip="2001:', $html);
        $this->assertStringContainsString('Ver os dados', $html);
        $this->assertStringNotContainsString('[grafico:', $html);
        $this->assertStringContainsString('<p>Depois.</p>', $html);
        $this->assertStringContainsString('viewBox="0 0 760', $html); // maiúsculas preservadas no SVG

        foreach (array_keys(Figures::CATALOGUE) as $key) {
            $this->assertNotNull(Figures::render($key), $key);
        }
    }

    public function test_unknown_chart_is_removed_and_missing_data_is_safe(): void
    {
        Storage::fake('local');
        Cache::flush();
        $html = ArticleBody::render('<p>[grafico:nao-existe]</p><p>[grafico:gfw-perda-anual-limiares]</p><p>Fim</p>');
        $this->assertStringNotContainsString('[grafico:', $html);
        $this->assertStringContainsString('<p>Fim</p>', $html);
    }

    public function test_images_tables_abstract_and_citation_are_structured(): void
    {
        $html = ArticleBody::render(
            '<div><strong>RESUMO</strong><p>Resumo do estudo.</p></div>'
            .'<div><img src="/storage/a.webp" alt="Mapa"><div>Figura 2. Mapa da Beira.</div></div>'
            .'<p><img src="/storage/b.webp" alt="Gráfico"></p><p>Figura 3. Outro.</p>'
            .'<div><table><caption>Tabela 1.</caption><tr><td>1</td></tr></table></div>'
            .'<table><tr><td>2</td></tr></table>'
            .'<h2>8. REFERÊNCIAS</h2><div><p>Hansen (2013).</p></div>'
            .'<div><h3>Como citar este artigo</h3><p>ABNT…</p></div>'
        );

        $this->assertStringContainsString('<div class="abstract">', $html);
        $this->assertSame(2, substr_count($html, '<figure class="figure">'));
        $this->assertStringContainsString('<figcaption><strong>Figura 2.</strong> Mapa da Beira.</figcaption>', $html);
        $this->assertStringContainsString('<figcaption><strong>Figura 3.</strong> Outro.</figcaption>', $html);
        $this->assertSame(2, substr_count($html, 'table-wrap'));
        $this->assertStringContainsString('<div class="references">', $html);
        $this->assertStringContainsString('<div class="cite-box">', $html);
    }
}
