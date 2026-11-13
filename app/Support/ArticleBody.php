<?php

namespace App\Support;

use App\Support\Charts\Figures;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Prepara o texto de tutoriais e pesquisas para leitura (sem alterar o que está gravado):
 * - [grafico:nome] num parágrafo próprio → gráfico com os dados da fonte (+ legenda "Figura N." seguinte);
 * - imagem seguida de legenda → <figure> com <figcaption>;
 * - tabelas com deslocamento horizontal no telemóvel;
 * - resumo, referências e "Como citar" com aspecto próprio.
 * Funciona com o HTML que o editor do painel grava (sem classes nem SVG).
 */
class ArticleBody
{
    public static function render(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }
        $key = 'article-body:v5:'.md5($html).':'.md5(json_encode(array_keys(Figures::CATALOGUE))).':'.(Figures::data()['imported_at'] ?? '-');

        try {
            return Cache::remember($key, now()->addWeek(), fn () => self::transform($html));
        } catch (Throwable $e) {
            report($e);

            return $html;
        }
    }

    protected static function transform(string $html): string
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);
        $charts = [];

        // 1. Gráficos: <p>[grafico:nome]</p> (+ legenda seguinte)
        foreach (iterator_to_array($xp->query('//p | //div[not(*)]')) as $p) {
            if (! preg_match('/^\s*\[grafico:([a-z0-9-]+)\]\s*$/u', $p->textContent, $m)) {
                continue;
            }
            $chart = Figures::render($m[1]);
            if ($chart === null) {
                $p->parentNode->removeChild($p);

                continue;
            }
            $fig = $doc->createElement('figure');
            $fig->setAttribute('class', 'figure figure--chart');
            $slot = count($charts);
            $charts[$slot] = $chart;
            $fig->appendChild($doc->createComment("chart-slot-{$slot}"));
            $next = self::nextElement($p);
            if ($next && self::isCaption($next)) {
                $fig->appendChild(self::toCaption($doc, $next));
                $next->parentNode->removeChild($next);
            }
            $p->parentNode->replaceChild($fig, $p);
        }

        // 2. Imagens com legenda: <div><img><div>Figura…</div></div>  ou  <p><img></p><p>Figura…</p>
        foreach (iterator_to_array($xp->query('//img')) as $img) {
            if (self::closest($img, 'figure')) {
                continue;
            }
            $holder = $img->parentNode;
            while ($holder instanceof DOMElement && in_array($holder->nodeName, ['a', 'p', 'span'], true) && self::onlyChild($holder)) {
                $holder = $holder->parentNode;
            }
            $mediaNode = $img->parentNode->nodeName === 'a' ? $img->parentNode : $img;
            // caso <div><img><div>legenda</div></div>
            if ($holder instanceof DOMElement && $holder->nodeName === 'div' && $holder->getAttribute('id') !== '__root') {
                $cap = null;
                foreach ($holder->childNodes as $c) {
                    if ($c instanceof DOMElement && $c !== $mediaNode && $c->getElementsByTagName('img')->length === 0 && self::isCaption($c)) {
                        $cap = $c;
                    }
                }
                if ($cap && $holder->getElementsByTagName('img')->length === 1) {
                    $fig = $doc->createElement('figure');
                    $fig->setAttribute('class', 'figure');
                    $fig->appendChild($mediaNode->parentNode->removeChild($mediaNode));
                    $fig->appendChild(self::toCaption($doc, $cap));
                    $holder->parentNode->replaceChild($fig, $holder);

                    continue;
                }
            }
            // caso <p><img></p><p>Figura…</p>
            $block = $img;
            while ($block->parentNode instanceof DOMElement && $block->parentNode->getAttribute('id') !== '__root' && self::onlyChild($block->parentNode) && in_array($block->parentNode->nodeName, ['p', 'a', 'span'], true)) {
                $block = $block->parentNode;
            }
            $next = self::nextElement($block);
            if ($next && self::isCaption($next)) {
                $fig = $doc->createElement('figure');
                $fig->setAttribute('class', 'figure');
                $block->parentNode->insertBefore($fig, $block);
                $fig->appendChild($mediaNode->parentNode->removeChild($mediaNode));
                $fig->appendChild(self::toCaption($doc, $next));
                $next->parentNode->removeChild($next);
                if ($block !== $img && trim($block->textContent) === '' && $block->getElementsByTagName('img')->length === 0) {
                    $block->parentNode->removeChild($block);
                }
            }
        }

        // 3. Tabelas: contentor com deslocamento horizontal
        foreach (iterator_to_array($xp->query('//table')) as $t) {
            $parent = $t->parentNode;
            if ($parent instanceof DOMElement && str_contains($parent->getAttribute('class'), 'table-wrap')) {
                continue;
            }
            if ($parent instanceof DOMElement && $parent->nodeName === 'div' && $parent->getAttribute('id') !== '__root' && self::onlyChild($parent)) {
                $parent->setAttribute('class', trim($parent->getAttribute('class').' table-wrap'));
            } else {
                $w = $doc->createElement('div');
                $w->setAttribute('class', 'table-wrap');
                $parent->replaceChild($w, $t);
                $w->appendChild($t);
            }
            $wrap = $t->parentNode;
            $wrap->setAttribute('tabindex', '0');
            $wrap->setAttribute('role', 'region');
            $cap = $t->getElementsByTagName('caption')->item(0);
            $wrap->setAttribute('aria-label', $cap ? trim($cap->textContent) : 'Tabela');
            // Legenda visível fora da zona que desliza (a <caption> fica para leitores de ecrã)
            if ($cap) {
                $p = $doc->createElement('p');
                $p->setAttribute('class', 'table-caption');
                $p->setAttribute('aria-hidden', 'true');
                foreach ($cap->childNodes as $c) {
                    $p->appendChild($c->cloneNode(true));
                }
                $wrap->parentNode->insertBefore($p, $wrap);
                $cap->setAttribute('class', 'visually-hidden');
                $wrap->setAttribute('class', $wrap->getAttribute('class').' has-caption');
            }
        }

        // 4. Resumo (div que começa por RESUMO / ABSTRACT)
        foreach (iterator_to_array($xp->query('//div[strong or b]')) as $d) {
            $first = self::firstElement($d);
            if ($first && in_array($first->nodeName, ['strong', 'b'], true) && preg_match('/^\s*(RESUMO|ABSTRACT|RÉSUMÉ)\s*$/iu', $first->textContent)) {
                $d->setAttribute('class', 'abstract');
                $first->setAttribute('class', 'abstract__title');
            }
        }

        // 5. "Como citar" e referências
        foreach (iterator_to_array($xp->query('//div[h2 or h3]')) as $d) {
            $h = self::firstElement($d);
            if ($h && preg_match('/como citar|how to cite|comment citer/iu', $h->textContent)) {
                $d->setAttribute('class', 'cite-box');
            }
        }
        foreach (iterator_to_array($xp->query('//h2')) as $h) {
            if (preg_match('/refer[êe]ncias|references|références|bibliografia/iu', $h->textContent)) {
                $n = self::nextElement($h);
                if ($n && $n->nodeName === 'div') {
                    $n->setAttribute('class', 'references');
                }
            }
        }

        // 6. Âncoras nos títulos de secção (para o índice)
        $used = [];
        foreach (iterator_to_array($xp->query('//h2')) as $h) {
            $id = $h->getAttribute('id') ?: (\Illuminate\Support\Str::slug($h->textContent) ?: 'seccao');
            $base = $id;
            for ($n = 2; isset($used[$id]); $n++) {
                $id = $base.'-'.$n;
            }
            $used[$id] = true;
            $h->setAttribute('id', $id);
        }

        // 7. Ligações externas abrem em segurança
        foreach (iterator_to_array($xp->query('//a[@target="_blank"]')) as $a) {
            $a->setAttribute('rel', 'noopener');
        }

        $root = $doc->getElementById('__root');
        $out = '';
        foreach ($root->childNodes as $n) {
            $out .= $doc->saveHTML($n);
        }
        foreach ($charts as $i => $chart) {
            $out = str_replace("<!--chart-slot-{$i}-->", $chart, $out);
        }

        return $out;
    }

    /** Índice a partir do HTML já preparado: [[id, texto], …] dos títulos H2. */
    public static function toc(string $rendered): array
    {
        preg_match_all('#<h2[^>]*\sid="([^"]+)"[^>]*>(.*?)</h2>#su', $rendered, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => [$x[1], trim(html_entity_decode(strip_tags($x[2]), ENT_QUOTES, 'UTF-8'))], $m);
    }

    protected static function isCaption(DOMNode $n): bool
    {
        return $n instanceof DOMElement && in_array($n->nodeName, ['p', 'div', 'figcaption', 'em', 'small', 'span'], true)
            && $n->getElementsByTagName('img')->length === 0
            && preg_match('/^\s*(Figura|Fig\.|Figure|Imagem|Gráfico|Mapa|Fonte|Source)\b/iu', $n->textContent)
            && mb_strlen(trim($n->textContent)) < 700;
    }

    protected static function toCaption(DOMDocument $doc, DOMElement $from): DOMElement
    {
        $cap = $doc->createElement('figcaption');
        while ($from->firstChild) {
            $cap->appendChild($from->firstChild);
        }
        // "Figura N." em destaque
        $first = $cap->firstChild;
        if ($first && $first->nodeType === XML_TEXT_NODE && preg_match('/^(\s*(?:Figura|Figure|Fig\.)\s*\d+[.:]?)(.*)$/su', $first->nodeValue, $m)) {
            $strong = $doc->createElement('strong', trim($m[1]));
            $cap->insertBefore($strong, $first);
            $first->nodeValue = $m[2];
        }

        return $cap;
    }

    protected static function nextElement(DOMNode $n): ?DOMElement
    {
        for ($s = $n->nextSibling; $s; $s = $s->nextSibling) {
            if ($s instanceof DOMElement) {
                return $s;
            }
            if (trim($s->textContent) !== '') {
                return null;
            }
        }

        return null;
    }

    protected static function firstElement(DOMNode $n): ?DOMElement
    {
        foreach ($n->childNodes as $c) {
            if ($c instanceof DOMElement) {
                return $c;
            }
            if (trim($c->textContent) !== '') {
                return null;
            }
        }

        return null;
    }

    protected static function onlyChild(DOMNode $parent): bool
    {
        $count = 0;
        foreach ($parent->childNodes as $c) {
            if ($c instanceof DOMElement) {
                $count++;
            } elseif (trim($c->textContent) !== '') {
                return false;
            }
        }

        return $count === 1;
    }

    protected static function closest(DOMNode $n, string $tag): ?DOMElement
    {
        for ($p = $n->parentNode; $p instanceof DOMElement; $p = $p->parentNode) {
            if ($p->nodeName === $tag) {
                return $p;
            }
        }

        return null;
    }
}
