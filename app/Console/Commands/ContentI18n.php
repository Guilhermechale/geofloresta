<?php

namespace App\Console\Commands;

use App\Models\Study;
use App\Models\Tutorial;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Tradução de tutoriais e pesquisas sem tocar na estrutura:
 *   export → JSON com os textos em português (campos + blocos de texto do HTML, sem código);
 *   import → lê o JSON traduzido e reconstrói o HTML com a mesma estrutura, imagens, gráficos e código.
 */
class ContentI18n extends Command
{
    protected $signature = 'geofloresta:i18n {action : export|import} {dir : pasta dos ficheiros JSON} {--lang=* : fr, en} {--only= : ex. tutorial-1,study-2}';

    protected $description = 'Exporta/importa os textos de tutoriais e pesquisas para tradução';

    protected const BLOCKS = ['p', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'figcaption', 'td', 'th', 'caption', 'summary', 'dt', 'dd', 'blockquote'];

    protected const FIELDS = [
        'tutorial' => ['plain' => ['title', 'summary', 'objectives', 'prerequisites', 'takeaways'], 'html' => ['body', 'exercise']],
        'study' => ['plain' => ['title', 'summary', 'abstract', 'keywords'], 'html' => ['body']],
    ];

    public function handle(): int
    {
        $dir = rtrim($this->argument('dir'), '/');
        @mkdir($dir, 0775, true);
        $only = array_filter(explode(',', (string) $this->option('only')));

        foreach (['tutorial' => Tutorial::class, 'study' => Study::class] as $type => $cls) {
            foreach ($cls::orderBy('id')->get() as $m) {
                $key = "{$type}-{$m->id}";
                if ($only && ! in_array($key, $only, true)) {
                    continue;
                }
                $this->argument('action') === 'export' ? $this->export($m, $type, "{$dir}/{$key}.pt.json") : $this->import($m, $type, $dir, $key);
            }
        }
        if ($this->argument('action') === 'import') {
            Cache::flush();
        }

        return self::SUCCESS;
    }

    protected function export($m, string $type, string $file): void
    {
        $out = ['fields' => [], 'html' => []];
        foreach (self::FIELDS[$type]['plain'] as $f) {
            $out['fields'][$f] = (string) $m->getTranslation($f, 'pt', false);
        }
        foreach (self::FIELDS[$type]['html'] as $f) {
            $out['html'][$f] = $this->segments((string) $m->getTranslation($f, 'pt', false));
        }
        file_put_contents($file, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $n = array_sum(array_map('count', $out['html']));
        $this->line(basename($file).": {$n} blocos");
    }

    protected function import($m, string $type, string $dir, string $key): void
    {
        foreach ($this->option('lang') ?: ['fr', 'en'] as $lang) {
            $file = "{$dir}/{$key}.{$lang}.json";
            if (! is_file($file)) {
                continue;
            }
            $tr = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            foreach (self::FIELDS[$type]['plain'] as $f) {
                if (isset($tr['fields'][$f]) && $tr['fields'][$f] !== '') {
                    $m->setTranslation($f, $lang, $tr['fields'][$f]);
                }
            }
            foreach (self::FIELDS[$type]['html'] as $f) {
                $pt = (string) $m->getTranslation($f, 'pt', false);
                if ($pt === '' || empty($tr['html'][$f])) {
                    continue;
                }
                [$html, $missing] = $this->rebuild($pt, $tr['html'][$f]);
                if ($missing) {
                    $this->warn("{$key} {$lang} {$f}: {$missing} blocos sem tradução (ficam em português)");
                }
                $m->setTranslation($f, $lang, $html);
            }
            if ($type === 'tutorial') {
                $m->setTranslation('slug', $lang, Str::slug($tr['fields']['title'] ?? $m->getTranslation('title', 'pt')));
            }
            $this->line("{$key} {$lang}: ".($tr['fields']['title'] ?? ''));
        }
        $m->saveQuietly();
    }

    /* ---------- HTML ---------- */

    /** @return array<string, string> id => HTML interior do bloco (ou texto / alt) */
    protected function segments(string $html): array
    {
        $segs = [];
        foreach ($this->nodes($html)[1] as $i => [$kind, $node]) {
            $segs["s{$i}"] = $kind === 'block' ? $this->inner($node) : ($kind === 'alt' ? $node->getAttribute('alt') : $node->nodeValue);
        }

        return $segs;
    }

    protected function rebuild(string $html, array $tr): array
    {
        [$doc, $nodes, $root] = $this->nodes($html);
        $missing = 0;
        foreach ($nodes as $i => [$kind, $node]) {
            $t = $tr["s{$i}"] ?? null;
            if ($t === null || trim($t) === '') {
                $missing++;

                continue;
            }
            if ($kind === 'block') {
                while ($node->firstChild) {
                    $node->removeChild($node->firstChild);
                }
                $tmp = new DOMDocument();
                libxml_use_internal_errors(true);
                $tmp->loadHTML('<?xml encoding="utf-8"?><div>'.$t.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();
                foreach ($tmp->documentElement->childNodes as $c) {
                    $node->appendChild($doc->importNode($c, true));
                }
            } elseif ($kind === 'alt') {
                $node->setAttribute('alt', $t);
            } else {
                // manter os espaços à volta do texto original
                preg_match('/^(\s*).*?(\s*)$/su', $node->nodeValue, $sp);
                $node->nodeValue = ($sp[1] ?? '').trim($t).($sp[2] ?? '');
            }
        }
        $out = '';
        foreach ($root->childNodes as $n) {
            $out .= $doc->saveHTML($n);
        }

        return [$out, $missing];
    }

    /** Lista ordenada e determinística dos nós a traduzir. */
    protected function nodes(string $html): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);
        $root = $doc->getElementById('__root');
        $list = [];
        $tags = implode(' or ', array_map(fn ($t) => "self::{$t}", self::BLOCKS));
        $desc = implode(' or ', array_map(fn ($t) => ".//{$t}", self::BLOCKS));

        foreach ($xp->query("//*[({$tags}) and not({$desc}) and not(ancestor::pre) and not(ancestor::code) and not(ancestor::svg)]") as $el) {
            $text = trim($el->textContent);
            if ($text === '' || preg_match('/^\[grafico:[a-z0-9-]+\]$/', $text) || ! preg_match('/\p{L}{2}/u', $text)) {
                continue;
            }
            $list[] = ['block', $el];
        }
        $inBlock = fn ($n) => $xp->query("ancestor::*[{$tags}]", $n)->length > 0;
        foreach ($xp->query('//text()[normalize-space() and not(ancestor::pre) and not(ancestor::code) and not(ancestor::svg) and not(ancestor::script) and not(ancestor::style)]') as $t) {
            if (! $inBlock($t) && preg_match('/\p{L}{2}/u', $t->nodeValue)) {
                $list[] = ['text', $t];
            }
        }
        foreach ($xp->query('//img[@alt!=""]') as $img) {
            $list[] = ['alt', $img];
        }

        return [$doc, $list, $root];
    }

    protected function inner(DOMElement $el): string
    {
        $h = '';
        foreach ($el->childNodes as $c) {
            $h .= $el->ownerDocument->saveHTML($c);
        }

        return trim($h);
    }
}
