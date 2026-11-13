<?php

namespace App\Console\Commands;

use App\Models\Study;
use App\Models\Tutorial;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Passa os tutoriais e as pesquisas existentes para o modelo único de cada grupo
 * (resources/content/reformat/*.php). Usar --dry para ver o que muda sem gravar.
 */
class ReformatContent extends Command
{
    protected $signature = 'geofloresta:reformat {--dry : mostra o resultado sem gravar} {--only= : ex. tutorial-2,study-4}';

    protected $description = 'Enquadra tutoriais e pesquisas no modelo único de cada grupo';

    /** Português de Moçambique nos tutoriais (palavras inteiras). */
    protected const PT_MZ = [
        'monitoramento' => 'monitorização', 'Monitoramento' => 'Monitorização', 'monitorar' => 'monitorizar', 'Monitorar' => 'Monitorizar',
        'umidade' => 'humidade', 'fumaça' => 'fumo', 'arquivo' => 'ficheiro', 'arquivos' => 'ficheiros', 'Arquivo' => 'Ficheiro',
        'baixar' => 'descarregar', 'Baixar' => 'Descarregar', 'baixado' => 'descarregado', 'baixados' => 'descarregados',
        'tela' => 'ecrã', 'contato' => 'contacto', 'registrar' => 'registar', 'estoque' => 'stock', 'estoques' => 'stocks',
        'projeto' => 'projecto', 'projetos' => 'projectos', 'interativo' => 'interactivo', 'interativa' => 'interactiva',
        'ativo' => 'activo', 'ativos' => 'activos', 'Ativos' => 'Activos', 'ação' => 'acção', 'direto' => 'directo', 'Direto' => 'Directo',
        'diretamente' => 'directamente', 'exatamente' => 'exactamente', 'estresse' => 'stress', 'equipe' => 'equipa',
    ];

    /** Concordância depois da troca de palavras (ex.: monitoramento m. → monitorização f.). */
    public const POST_FIX = [
        'O monitorização' => 'A monitorização', 'No monitorização' => 'Na monitorização', 'do monitorização básico' => 'da monitorização básica',
        'monitorização contínuo' => 'monitorização contínua', 'monitorização detalhado' => 'monitorização detalhada',
        'e a fumo de' => 'e o fumo de', 'aberta na ecrã' => 'aberta no ecrã',
    ];

    public function handle(): int
    {
        $only = array_filter(explode(',', (string) $this->option('only')));
        $dry = (bool) $this->option('dry');

        foreach (require resource_path('content/reformat/tutorials.php') as $id => $spec) {
            if ($only && ! in_array("tutorial-{$id}", $only, true)) {
                continue;
            }
            $t = Tutorial::find($id);
            if (! $t) {
                continue;
            }
            $body = $this->cleanTutorial($t->getTranslation('body', 'pt'), $spec);
            $this->report("Tutorial #{$id}", $spec['title'], $body);
            if (! $dry) {
                $t->setTranslation('title', 'pt', $spec['title']);
                $t->setTranslation('summary', 'pt', $spec['summary']);
                $t->setTranslation('objectives', 'pt', $spec['objectives']);
                $t->setTranslation('prerequisites', 'pt', $spec['prerequisites']);
                $t->setTranslation('body', 'pt', $body);
                $t->setTranslation('exercise', 'pt', trim($spec['exercise']));
                $t->setTranslation('takeaways', 'pt', $spec['takeaways']);
                $t->forceFill([
                    'sources' => $spec['sources'], 'platform' => $spec['platform'], 'level' => $spec['level'],
                    'duration_minutes' => $spec['duration'], 'content_updated_at' => now()->toDateString(),
                ]);
                $t->setTranslation('seo_title', 'pt', null);
                $t->saveQuietly();
            }
        }

        foreach (require resource_path('content/reformat/studies.php') as $id => $spec) {
            if ($only && ! in_array("study-{$id}", $only, true)) {
                continue;
            }
            $s = Study::find($id);
            if (! $s) {
                continue;
            }
            [$body, $abstract, $keywords, $refs] = $this->cleanStudy($s->getTranslation('body', 'pt'), $spec);
            $this->report("Pesquisa #{$id}", $spec['title'], $body);
            $this->line('   resumo: '.mb_strlen($abstract).' car. · palavras-chave: '.$keywords.' · referências: '.count($refs));
            if (! $dry) {
                $s->setTranslation('title', 'pt', $spec['title']);
                $s->setTranslation('abstract', 'pt', $abstract);
                $s->setTranslation('keywords', 'pt', $keywords);
                $s->setTranslation('body', 'pt', $body);
                $s->forceFill(['authors' => $spec['authors'], 'article_type' => $spec['article_type'], 'venue' => $spec['venue'], 'references' => implode("\n", $refs)]);
                $s->setTranslation('seo_title', 'pt', null);
                $s->saveQuietly();
            }
        }

        if (! $dry) {
            Cache::flush();
        }

        return self::SUCCESS;
    }

    protected function report(string $what, string $title, string $body): void
    {
        preg_match_all('#<h2[^>]*>(.*?)</h2>#su', $body, $m);
        $this->info("{$what}: {$title}");
        $this->line('   secções: '.implode(' | ', array_map(fn ($h) => trim(strip_tags($h)), $m[1])));
    }

    /* ---------- tutoriais ---------- */

    protected function cleanTutorial(string $html, array $spec): string
    {
        [$doc, $xp, $root] = $this->load($html);

        // Cabeçalho, etiquetas, rodapé e meta do WordPress
        foreach (iterator_to_array($xp->query('//header | //footer')) as $n) {
            $n->parentNode->removeChild($n);
        }
        foreach (iterator_to_array($xp->query('//span')) as $sp) {
            $t = trim($sp->textContent);
            if (preg_match('/^(Tags:|Tópicos:|🏷️\s*Tópicos:)$/u', $t)) {
                $sp->parentNode->parentNode->removeChild($sp->parentNode);
            } elseif (preg_match('/^(Centro de Conhecimento|•|⏱️.*|Guia Educacional|Florestas & Incêndios|Sensoriamento Remoto|Geotecnologia e Observação da Terra|Clima & Hidrologia|SIG & Hidrologia|\d{1,2} de \p{L}+ de \d{4})$/u', $t)) {
                $sp->parentNode->removeChild($sp);
            }
        }
        // Caixas "Pontos chave" / "O que você vai aprender" (passaram para os objectivos)
        foreach (iterator_to_array($xp->query('//h3')) as $h) {
            if (preg_match('/^(Pontos chave|O que você vai aprender neste guia)$/u', trim($h->textContent)) && $h->parentNode?->parentNode) {
                $box = $h->parentNode->parentNode;
                $box->parentNode->removeChild($box);
            }
        }
        // Secções que passaram para campos próprios
        foreach ($spec['remove_sections'] ?? [] as $title) {
            $this->removeSection($xp, $title);
        }
        // Números decorativos dos cartões (<span>01</span><h4>…) e H4 → H3
        foreach (iterator_to_array($xp->query('//span')) as $sp) {
            if (preg_match('/^\d{1,3}$/', trim($sp->textContent)) && $this->nextEl($sp)?->nodeName === 'h4') {
                $sp->parentNode->removeChild($sp);
            }
        }
        foreach (iterator_to_array($xp->query('//h4')) as $h4) {
            $this->rename($doc, $h4, 'h3');
        }
        foreach ($spec['promote_h3'] ?? [] as $title) {
            foreach (iterator_to_array($xp->query('//h3')) as $h) {
                if (trim($h->textContent) === $title) {
                    $this->rename($doc, $h, 'h2');
                }
            }
        }
        // Títulos: emojis fora, textos novos
        $map = $spec['headings'] ?? [];
        foreach (iterator_to_array($xp->query('//h2 | //h3')) as $h) {
            $text = trim(preg_replace('/^[\p{So}\p{Sk}\x{FE0F}\s]+/u', '', $h->textContent));
            if (isset($map[$text])) {
                $text = $map[$text];
            }
            if ($text !== $h->textContent) {
                while ($h->firstChild) {
                    $h->removeChild($h->firstChild);
                }
                $h->appendChild($doc->createTextNode($text));
            }
        }
        // Texto adicional depois de um título
        foreach ($spec['append_after'] ?? [] as $after => $add) {
            foreach (iterator_to_array($xp->query('//h3 | //h2')) as $h) {
                if (trim($h->textContent) === $after) {
                    $anchor = $h->parentNode->nodeName === 'div' && $h->parentNode->getAttribute('id') !== '__root' ? $h->parentNode : $h;
                    $anchor = $anchor === $h ? ($this->nextEl($h) ?? $h) : $anchor;
                    $frag = $this->fragment($doc, $add);
                    $anchor->parentNode->insertBefore($frag, $anchor->nextSibling);
                    break;
                }
            }
        }
        $out = $this->save($doc, $root);
        if (! empty($spec['prepend'])) {
            $out = $spec['prepend']."\n".$out;
        }
        foreach ($spec['replace'] ?? [] as $a => $b) {
            $out = str_replace($a, $b, $out);
        }
        // Português de Moçambique (fora do código)
        $words = '/(*UCP)\b('.implode('|', array_map('preg_quote', array_keys(self::PT_MZ))).')\b/u';
        $out = preg_replace_callback('#(<pre\b.*?</pre>|<code\b.*?</code>|<[^>]*>)|([^<]+)#su', function ($m) use ($words) {
            if (($m[1] ?? '') !== '') {
                return $m[1];
            }

            return preg_replace_callback($words, fn ($w) => self::PT_MZ[$w[1]], $m[2]);
        }, $out);
        $out = strtr($out, self::POST_FIX);

        return $this->tidy($out);
    }

    /* ---------- pesquisas ---------- */

    protected function cleanStudy(string $html, array $spec): array
    {
        [$doc, $xp, $root] = $this->load($html);
        $abstract = '';
        $keywords = '';
        $refs = [];

        // Resumo e palavras-chave
        foreach (iterator_to_array($xp->query('//strong')) as $st) {
            if (preg_match('/^\s*RESUMO\s*$/u', $st->textContent)) {
                $box = $st->parentNode->nodeName === 'p' ? $st->parentNode->parentNode : $st->parentNode;
                foreach ($xp->query('.//p', $box) as $p) {
                    $txt = trim(preg_replace('/\s+/u', ' ', $p->textContent));
                    if (preg_match('/^Palavras-chave:\s*(.+)$/iu', $txt, $k)) {
                        $keywords = implode('; ', array_map('trim', preg_split('/[;,]\s*/u', rtrim($k[1], '.'))));
                    } elseif ($txt !== '' && ! preg_match('/^RESUMO$/u', $txt)) {
                        $abstract .= ($abstract ? "\n\n" : '').$txt;
                    }
                }
                $box->parentNode->removeChild($box);
                break;
            }
        }
        // Referências
        foreach (iterator_to_array($xp->query('//h2')) as $h) {
            if (preg_match('/REFER[ÊE]NCIAS/iu', $h->textContent)) {
                $n = $this->nextEl($h);
                if ($n && $n->nodeName === 'div') {
                    foreach ($xp->query('.//p', $n) as $p) {
                        $r = trim(preg_replace('/\s+/u', ' ', $p->textContent));
                        if ($r !== '') {
                            $refs[] = $r;
                        }
                    }
                    $n->parentNode->removeChild($n);
                }
                $h->parentNode->removeChild($h);
            }
        }
        // "Como citar" (passa a ser gerado automaticamente)
        foreach (iterator_to_array($xp->query('//h3')) as $h) {
            if (preg_match('/como citar/iu', $h->textContent)) {
                $box = $h->parentNode;
                $box->parentNode->removeChild($box);
            }
        }
        foreach ($spec['remove_sections'] ?? [] as $title) {
            $this->removeSection($xp, $title);
        }
        // Títulos em maiúsculas → só a primeira letra maiúscula
        foreach (iterator_to_array($xp->query('//h2 | //h3 | //h4')) as $h) {
            $t = trim($h->textContent);
            if (preg_match('/\p{L}{3}/u', $t) && mb_strtoupper($t) === $t) {
                $t = preg_replace_callback('/^([\d.\s]*)(\p{L})(.*)$/su', fn ($m) => $m[1].mb_strtoupper($m[2]).mb_strtolower($m[3]), mb_strtolower($t));
                while ($h->firstChild) {
                    $h->removeChild($h->firstChild);
                }
                $h->appendChild($doc->createTextNode($t));
            }
        }

        return [$this->tidy($this->save($doc, $root)), $abstract, $keywords, $refs];
    }

    /* ---------- DOM ---------- */

    protected function load(string $html): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        return [$doc, new DOMXPath($doc), $doc->getElementById('__root')];
    }

    protected function save(DOMDocument $doc, DOMElement $root): string
    {
        // Desembrulhar <article>/<div>/<section> sem atributos que só envolvem conteúdo
        foreach (iterator_to_array((new DOMXPath($doc))->query('//article | //section')) as $w) {
            while ($w->firstChild) {
                $w->parentNode->insertBefore($w->firstChild, $w);
            }
            $w->parentNode->removeChild($w);
        }
        $out = '';
        foreach ($root->childNodes as $n) {
            $out .= $doc->saveHTML($n);
        }

        return $out;
    }

    protected function tidy(string $html): string
    {
        $html = preg_replace('#<p>\s*</p>#u', '', $html);
        $html = preg_replace('#<div>\s*</div>#u', '', $html);
        $html = preg_replace("/\n\s*\n+/u", "\n", $html);

        return trim($html);
    }

    protected function removeSection(DOMXPath $xp, string $title): void
    {
        foreach (iterator_to_array($xp->query('//h2')) as $h) {
            if (trim($h->textContent) !== $title) {
                continue;
            }
            $parent = $h->parentNode;
            for ($n = $h->nextSibling; $n; ) {
                if ($n instanceof DOMElement && $n->nodeName === 'h2') {
                    break;
                }
                $next = $n->nextSibling;
                $parent->removeChild($n);
                $n = $next;
            }
            $parent->removeChild($h);
            if ($parent instanceof DOMElement && $parent->getAttribute('id') !== '__root' && trim($parent->textContent) === '') {
                $parent->parentNode->removeChild($parent);
            }
        }
    }

    protected function rename(DOMDocument $doc, DOMElement $el, string $tag): DOMElement
    {
        $new = $doc->createElement($tag);
        while ($el->firstChild) {
            $new->appendChild($el->firstChild);
        }
        $el->parentNode->replaceChild($new, $el);

        return $new;
    }

    protected function nextEl(DOMNode $n): ?DOMElement
    {
        for ($s = $n->nextSibling; $s; $s = $s->nextSibling) {
            if ($s instanceof DOMElement) {
                return $s;
            }
        }

        return null;
    }

    protected function fragment(DOMDocument $doc, string $html): \DOMDocumentFragment
    {
        $tmp = new DOMDocument();
        libxml_use_internal_errors(true);
        $tmp->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $frag = $doc->createDocumentFragment();
        foreach ($tmp->documentElement->childNodes as $c) {
            $frag->appendChild($doc->importNode($c, true));
        }

        return $frag;
    }
}
