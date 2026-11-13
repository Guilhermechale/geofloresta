<?php

namespace App\Console\Commands;

use App\Models\Redirect;
use App\Models\Study;
use App\Models\Tutorial;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;

/**
 * Migra o conteúdo do WordPress (API REST pública) para as duas categorias do site novo:
 *   Tutoriais e Centro de Conhecimento -> Tutoriais (URL antigo mantido: /slug)
 *   Artigos Científicos                -> Pesquisas (URL antigo mantido: /slug)
 *   Notícias                           -> 410 (retirado de propósito)
 * Imagens e ficheiros das uploads são copiados (imagens em WebP) e o HTML é limpo.
 * Idempotente: pode correr várias vezes.
 */
class MigrateWordPress extends Command
{
    protected $signature = 'geofloresta:migrate-wp {--source=https://geofloresta.com} {--uploads= : pasta local wp-content/uploads}';

    protected $description = 'Migra tutoriais e artigos científicos do WordPress; notícias passam a 410';

    protected string $source;

    protected string $uploads;

    public function handle(): int
    {
        $this->source = rtrim($this->option('source'), '/');
        $this->uploads = $this->option('uploads') ?: getenv('HOME').'/domains/geofloresta.com/public_html/wp-content/uploads';

        $cats = collect(Http::timeout(60)->get($this->source.'/wp-json/wp/v2/categories', ['per_page' => 100])->json())
            ->mapWithKeys(fn ($c) => [$c['id'] => $c['slug']]);
        $posts = Http::timeout(120)->get($this->source.'/wp-json/wp/v2/posts', ['per_page' => 100, '_embed' => 1])->json();
        if (! is_array($posts)) {
            $this->error('A API do WordPress não respondeu.');

            return self::FAILURE;
        }

        $n = ['tutorial' => 0, 'pesquisa' => 0, 'noticia' => 0];
        foreach ($posts as $p) {
            $slugs = collect($p['categories'])->map(fn ($id) => $cats[$id] ?? '')->all();
            $slug = $p['slug'];
            $title = html_entity_decode(strip_tags($p['title']['rendered']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $summary = trim(html_entity_decode(strip_tags($p['excerpt']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $summary = Str::limit(preg_replace('/\s+/u', ' ', $summary), 380);
            $published = $p['date_gmt'].'Z';
            $modified = substr($p['modified_gmt'], 0, 10);

            if (in_array('noticias', $slugs, true) && ! array_intersect($slugs, ['tutoriais', 'centro-de-conhecimento', 'artigos-cientificos'])) {
                Redirect::updateOrCreate(['from_path' => '/'.$slug], ['status_code' => 410, 'to_url' => null, 'source' => 'migration', 'note' => 'Notícia do WordPress: '.Str::limit($title, 150)]);
                $n['noticia']++;
                continue;
            }

            $body = $this->cleanHtml($p['content']['rendered']);
            [$cover, $coverAlt] = $this->featured($p);

            if (in_array('artigos-cientificos', $slugs, true)) {
                Study::updateOrCreate(['slug' => $slug], [
                    'title' => ['pt' => $title], 'summary' => ['pt' => $summary], 'body' => ['pt' => $body],
                    'year' => (int) substr($p['date'], 0, 4), 'original_locale' => 'pt',
                    'cover_image' => $cover, 'cover_alt' => $coverAlt,
                    'status' => 'published', 'published_at' => $published,
                ]);
                $n['pesquisa']++;
            } else {
                Tutorial::updateOrCreate(['legacy_slug' => $slug], [
                    'slug' => ['pt' => $slug], 'title' => ['pt' => $title], 'summary' => ['pt' => $summary], 'body' => ['pt' => $body],
                    'platform' => $this->platform($title.' '.strip_tags($body)), 'level' => 'beginner', 'original_locale' => 'pt',
                    'cover_image' => $cover, 'cover_alt' => $coverAlt, 'content_updated_at' => $modified,
                    'status' => 'published', 'published_at' => $published,
                ]);
                $n['tutorial']++;
            }
            $this->line("  {$slug}");
        }

        // Páginas e arquivos do WordPress sem equivalente directo
        $map = [
            '/inicio' => ['/', 301], '/blog' => ['/', 301],
            '/category/tutoriais' => ['/tutoriais', 301], '/category/centro-de-conhecimento' => ['/tutoriais', 301],
            '/category/artigos-cientificos' => ['/pesquisas', 301], '/category/noticias' => [null, 410],
            '/feed' => [null, 410], '/comments/feed' => [null, 410],
        ];
        foreach ($map as $from => [$to, $code]) {
            Redirect::updateOrCreate(['from_path' => $from], ['to_url' => $to, 'status_code' => $code, 'source' => 'migration']);
        }

        $this->info("Migrados: {$n['tutorial']} tutoriais, {$n['pesquisa']} pesquisas; {$n['noticia']} notícias com 410.");

        return self::SUCCESS;
    }

    protected function platform(string $text): string
    {
        $t = mb_strtolower($text);

        return match (true) {
            str_contains($t, 'earth engine') => 'gee',
            str_contains($t, 'colab') => 'colab',
            str_contains($t, 'qgis') || str_contains($t, 'shapefile') || str_contains($t, 'overpass') => 'qgis',
            (bool) preg_match('/\b(rstudio|linguagem r|pacote r|em r)\b/u', $t) => 'r',
            default => 'other',
        };
    }

    protected function featured(array $p): array
    {
        $m = $p['_embedded']['wp:featuredmedia'][0] ?? null;
        if (! $m || empty($m['source_url'])) {
            return [null, null];
        }
        $path = $this->localise($m['source_url']);

        return [$path ? Str::after($path, '/storage/') : null, html_entity_decode(strip_tags($m['alt_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?: null];
    }

    /** Copia um ficheiro das uploads (ou externo) para o disco público; imagens em WebP. Devolve o URL /storage/... */
    protected function localise(string $url): ?string
    {
        $url = html_entity_decode($url);
        $rel = null;
        if (preg_match('#/wp-content/uploads/(.+)$#', parse_url($url, PHP_URL_PATH) ?? '', $m)) {
            $rel = rawurldecode($m[1]);
        }
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $base = Str::slug(pathinfo(parse_url($url, PHP_URL_PATH) ?? 'ficheiro', PATHINFO_FILENAME)) ?: 'ficheiro';
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
        $target = 'conteudo/wp/'.$base.($isImage ? '.webp' : '.'.$ext);
        $disk = Storage::disk('public');
        if ($disk->exists($target)) {
            return '/storage/'.$target;
        }

        $local = $rel ? $this->uploads.'/'.$rel : null;
        $bytes = $local && is_file($local) ? file_get_contents($local) : null;
        if ($bytes === null) {
            try {
                $r = Http::timeout(60)->get($url);
                $bytes = $r->successful() ? $r->body() : null;
            } catch (\Throwable) {
                $bytes = null;
            }
        }
        if ($bytes === null) {
            $this->warn("    não foi possível copiar {$url}");

            return null;
        }
        if ($isImage && $ext !== 'gif') {
            try {
                $img = new Imagick;
                $img->readImageBlob($bytes);
                $img->autoOrient();
                $img->stripImage();
                if ($img->getImageWidth() > 1800) {
                    $img->resizeImage(1800, 0, Imagick::FILTER_LANCZOS, 1);
                }
                $img->setImageFormat('webp');
                $img->setImageCompressionQuality(82);
                $bytes = $img->getImagesBlob();
            } catch (\Throwable) {
                $target = 'conteudo/wp/'.$base.'.'.$ext;
            }
        } elseif ($ext === 'gif') {
            $target = 'conteudo/wp/'.$base.'.gif';
        }
        $disk->put($target, $bytes);

        return '/storage/'.$target;
    }

    /** Limpa o HTML do WordPress: sem scripts, anúncios, estilos inline, classes do tema; imagens locais em WebP. */
    protected function cleanHtml(string $html): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);

        foreach (iterator_to_array($xp->query('//script|//style|//noscript|//ins[contains(@class,"adsbygoogle")]|//form|//button|//*[contains(@class,"sharedaddy") or contains(@class,"jp-relatedposts") or contains(@class,"wp-block-buttons")]')) as $n) {
            $n->parentNode?->removeChild($n);
        }

        foreach (iterator_to_array($xp->query('//img|//video|//source|//a')) as $el) {
            /** @var DOMElement $el */
            $attr = $el->tagName === 'a' ? 'href' : 'src';
            $v = $el->getAttribute($attr);
            if ($v === '') {
                continue;
            }
            if (str_contains($v, '/wp-content/uploads/') || ($el->tagName !== 'a' && preg_match('#^https?://#', $v))) {
                $local = $this->localise($v);
                if ($local) {
                    $el->setAttribute($attr, $local);
                } elseif ($el->tagName === 'img') {
                    $el->parentNode?->removeChild($el);
                    continue;
                }
            } elseif (preg_match('#^https?://(www\.)?geofloresta\.com(/.*)?$#', $v, $m)) {
                $el->setAttribute($attr, rtrim($m[2] ?? '/', '/') ?: '/'); // ligações internas relativas
            }
            if ($el->tagName === 'img') {
                foreach (['srcset', 'sizes', 'decoding', 'fetchpriority'] as $a) {
                    $el->removeAttribute($a);
                }
                $el->setAttribute('loading', 'lazy');
                $path = Str::after($el->getAttribute('src'), '/storage/');
                if ($dim = \App\Support\ImageOptimizer::dimensions($path)) {
                    $el->setAttribute('width', (string) $dim['width']);
                    $el->setAttribute('height', (string) $dim['height']);
                }
                if (! $el->hasAttribute('alt')) {
                    $el->setAttribute('alt', '');
                }
            }
            if ($el->tagName === 'a' && preg_match('#^https?://#', $el->getAttribute('href'))) {
                $el->setAttribute('rel', 'noopener');
            }
        }

        // Atributos de apresentação do tema: fora (excepto a linguagem nos blocos de código)
        foreach (iterator_to_array($xp->query('//*[@style or @class or @id or @data-id]')) as $el) {
            /** @var DOMElement $el */
            if ($el->getAttribute('id') === 'root') {
                continue;
            }
            $lang = preg_match('/(?:language|lang)-([a-z0-9]+)/i', $el->getAttribute('class'), $m) ? 'language-'.strtolower($m[1]) : null;
            foreach (['style', 'class', 'id', 'data-id'] as $a) {
                $el->removeAttribute($a);
            }
            if ($lang && in_array($el->tagName, ['code', 'pre'], true)) {
                $el->setAttribute('class', $lang);
            }
        }
        // Títulos: o H1 é o título da página; dentro do texto passam a H2
        foreach (iterator_to_array($xp->query('//h1')) as $h) {
            $h2 = $doc->createElement('h2');
            while ($h->firstChild) {
                $h2->appendChild($h->firstChild);
            }
            $h->parentNode->replaceChild($h2, $h);
        }
        // Divs vazias do construtor de páginas
        foreach (iterator_to_array($xp->query('//div[not(normalize-space()) and not(.//img) and not(.//video) and not(.//iframe)]')) as $d) {
            $d->parentNode?->removeChild($d);
        }

        $root = $doc->getElementById('root') ?? $xp->query('//div')->item(0);
        $out = '';
        foreach ($root->childNodes as $c) {
            $out .= $doc->saveHTML($c);
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", $out));
    }
}
