<?php

namespace App\Support;

use App\Models\Dataset;
use App\Models\Study;
use App\Models\Tutorial;

/**
 * llms.txt (https://llmstxt.org): resumo do site para agentes de IA, gerado a partir do conteúdo publicado.
 * Em inglês (com os endereços em inglês), com a indicação das versões em português e francês.
 */
class LlmsTxt
{
    public static function build(): string
    {
        $en = fn (string $name, array $p = []) => Locales::route($name, $p, 'en');
        $title = fn ($m) => $m->getTranslation('title', 'en', false) ?: $m->getTranslation('title', $m->displayLocale('en'));
        $summary = fn ($m) => trim((string) ($m->getTranslation('summary', 'en', false) ?: $m->getTranslation('summary', $m->displayLocale('en'), false)));
        $line = fn (string $t, string $url, string $s = '') => '- ['.str_replace(['[', ']'], ['(', ')'], $t).']('.$url.')'.($s !== '' ? ': '.preg_replace('/\s+/', ' ', $s) : '');

        $md = "# GEOFLORESTA\n\n";
        $md .= "> Geotechnology, forestry and environment consultancy. Free open geospatial data for any country, free browser-based map tools "
            ."(AI land cover classification with Random Forest, fire monitor, NDVI, terrain and contour lines, daily satellite imagery), "
            ."and free step-by-step GIS and remote sensing tutorials. The site is available in Portuguese (default, ".url('/').'), French ('.Locales::route('home', [], 'fr').') and English ('.Locales::route('home', [], 'en').").\n\n";
        $md .= "Content may be used for search and in AI answers with a link to the source page; it may not be used to train AI models (see robots.txt Content-Signal). "
            ."Datasets keep the licence and attribution of their original source, stated on each dataset page.\n\n";

        $md .= "## Map tools (free, run in the browser)\n\n";
        foreach (MapTools::keys() as $k) {
            $md .= $line(__('map.tools.'.$k.'.title', [], 'en'), MapTools::url($k, 'en'), __('map.tools.'.$k.'.text', [], 'en'))."\n";
        }
        $md .= $line('All map tools', $en('maps'))."\n";

        $md .= "\n## Main pages\n\n";
        foreach (['data' => 'Open data catalogue (download without registration)', 'countries' => 'Open data by country (any country in the world)', 'learn' => 'Free tutorials', 'studies' => 'Research articles', 'services' => 'Consulting services (GIS, remote sensing, applied AI, environment)', 'about' => 'About GEOFLORESTA', 'contact' => 'Contact and requests for proposals', 'licences' => 'Data sources and licences'] as $route => $label) {
            $md .= $line($label, $en($route))."\n";
        }

        $safe = function (callable $fn) {
            try {
                return $fn();
            } catch (\Throwable) {
                return collect();
            }
        };

        $tutorials = $safe(fn () => Tutorial::published()->orderByDesc('published_at')->get());
        if ($tutorials->isNotEmpty()) {
            $md .= "\n## Tutorials\n\n";
            foreach ($tutorials as $t) {
                $md .= $line($title($t), $t->url('en'), $summary($t))."\n";
            }
        }

        $datasets = $safe(fn () => Dataset::published()->orderBy('theme')->get());
        if ($datasets->isNotEmpty()) {
            $md .= "\n## Datasets\n\n";
            foreach ($datasets as $d) {
                $md .= $line($title($d), $d->url('en'), $summary($d))."\n";
            }
        }

        $studies = $safe(fn () => Study::published()->orderByDesc('published_at')->get());
        if ($studies->isNotEmpty()) {
            $md .= "\n## Research\n\n";
            foreach ($studies as $s) {
                $md .= $line($title($s), $s->url('en'), $summary($s))."\n";
            }
        }

        $md .= "\n## Optional\n\n";
        $md .= $line('Sitemap (all pages in three languages)', url('/sitemap.xml'))."\n";
        $md .= $line('Privacy policy', $en('privacy'))."\n";
        $md .= $line('Terms of use', $en('terms'))."\n";
        $md .= "\nAny page can be requested as Markdown with the HTTP header `Accept: text/markdown`.\n";

        return $md;
    }
}
