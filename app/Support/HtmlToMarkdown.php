<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Converte o conteúdo principal (<main>) de uma página em Markdown simples, para agentes de IA
 * que pedem «Accept: text/markdown». Ignora menus, formulários, scripts e elementos decorativos.
 */
class HtmlToMarkdown
{
    private const SKIP = ['script', 'style', 'noscript', 'svg', 'form', 'button', 'input', 'select', 'textarea', 'nav', 'dialog', 'canvas', 'iframe', 'template'];

    public static function page(string $html, string $url): string
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);

        $title = trim($xp->query('//title')->item(0)?->textContent ?? '');
        $desc = trim($xp->query('//meta[@name="description"]/@content')->item(0)?->nodeValue ?? '');
        $main = $xp->query('//main')->item(0) ?? $xp->query('//body')->item(0);

        $md = '# '.$title."\n\n";
        if ($desc !== '') {
            $md .= '> '.$desc."\n\n";
        }
        $md .= 'Source: '.$url."\n\n";
        $md .= $main ? (new self())->block($main) : '';

        return trim(preg_replace("/\n{3,}/", "\n\n", $md))."\n";
    }

    private function block(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $c) {
            $out .= $this->node($c);
        }

        return $out;
    }

    private function inline(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $c) {
            $out .= $this->node($c, true);
        }

        return preg_replace('/[ \t\r\n]+/', ' ', $out);
    }

    private function node(DOMNode $n, bool $inline = false): string
    {
        if ($n->nodeType === XML_TEXT_NODE) {
            return $inline ? $n->textContent : (trim($n->textContent) === '' ? '' : preg_replace('/\s+/', ' ', $n->textContent));
        }
        if (! $n instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($n->tagName);
        if (in_array($tag, self::SKIP, true) || $n->hasAttribute('hidden') || $n->getAttribute('aria-hidden') === 'true') {
            return '';
        }

        switch ($tag) {
            case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                $t = trim($this->inline($n));

                return $t === '' ? '' : "\n\n".str_repeat('#', (int) $tag[1]).' '.$t."\n\n";
            case 'p': case 'figcaption': case 'caption':
                $t = trim($this->inline($n));

                return $t === '' ? '' : "\n\n".$t."\n\n";
            case 'br':
                return "  \n";
            case 'hr':
                return "\n\n---\n\n";
            case 'strong': case 'b':
                $t = trim($this->inline($n));

                return $t === '' ? '' : '**'.$t.'**';
            case 'em': case 'i':
                $t = trim($this->inline($n));

                return $t === '' ? '' : '*'.$t.'*';
            case 'code':
                return '`'.$n->textContent.'`';
            case 'pre':
                return "\n\n```\n".rtrim($n->textContent)."\n```\n\n";
            case 'a':
                $t = trim($this->inline($n));
                $href = $n->getAttribute('href');
                if ($t === '' || $href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                    return $t;
                }

                return '['.$t.']('.$href.')';
            case 'img':
                $alt = trim($n->getAttribute('alt'));

                return $alt === '' ? '' : '!['.$alt.']('.$n->getAttribute('src').')';
            case 'ul': case 'ol':
                $out = "\n\n";
                $k = 0;
                foreach ($n->childNodes as $li) {
                    if ($li instanceof DOMElement && strtolower($li->tagName) === 'li') {
                        $t = trim(preg_replace('/\s*\n\s*/', ' ', $this->block($li)));
                        if ($t !== '') {
                            $out .= ($tag === 'ol' ? (++$k).'. ' : '- ').$t."\n";
                        }
                    }
                }

                return $out."\n";
            case 'blockquote':
                return "\n\n> ".trim($this->inline($n))."\n\n";
            case 'table':
                return $this->table($n);
            default:
                return $inline ? $this->inline($n) : $this->block($n);
        }
    }

    private function table(DOMElement $t): string
    {
        $rows = [];
        foreach ((new DOMXPath($t->ownerDocument))->query('.//tr', $t) as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $c) {
                if ($c instanceof DOMElement && in_array(strtolower($c->tagName), ['td', 'th'], true)) {
                    $cells[] = str_replace('|', '\|', trim($this->inline($c)));
                }
            }
            if ($cells) {
                $rows[] = $cells;
            }
        }
        if (! $rows) {
            return '';
        }
        $w = max(array_map('count', $rows));
        $line = fn ($r) => '| '.implode(' | ', array_pad($r, $w, '')).' |';
        $out = "\n\n".$line($rows[0])."\n|".str_repeat(' --- |', $w)."\n";
        foreach (array_slice($rows, 1) as $r) {
            $out .= $line($r)."\n";
        }

        return $out."\n";
    }
}
