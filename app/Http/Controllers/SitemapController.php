<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Dataset;
use App\Models\Study;
use App\Models\Tutorial;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class SitemapController extends Controller
{
    public const MAIN = ['home', 'services', 'data', 'maps', 'learn', 'about', 'contact', 'search'];

    public const LEGAL = ['privacy', 'cookies', 'terms', 'licences', 'accessibility', 'sitemap'];

    /** Mapa do site para pessoas (/mapa-do-site). */
    public function html(): View
    {
        return view('pages.sitemap', [
            'datasets' => $this->safe(fn () => Dataset::published()->get()),
            'tutorials' => $this->safe(fn () => Tutorial::published()->get()),
            'studies' => $this->safe(fn () => Study::published()->get()),
            'courses' => $this->safe(fn () => Course::published()->get()),
        ]);
    }

    /** sitemap.xml com as três versões de cada página e ligações hreflang. */
    public function xml(): Response
    {
        $entries = [];

        foreach (array_merge(self::MAIN, self::LEGAL) as $name) {
            $urls = [];
            foreach (Locales::all() as $l) {
                if (Route::has("{$l}.{$name}")) {
                    $urls[$l] = lroute($name, [], $l);
                }
            }
            $entries[] = ['urls' => $urls, 'lastmod' => null];
        }

        foreach ([Dataset::class, Tutorial::class, Study::class, Course::class] as $model) {
            foreach ($this->safe(fn () => $model::published()->get()) as $item) {
                $urls = [];
                foreach (Locales::all() as $l) {
                    try {
                        $urls[$l] = $item->url($l);
                    } catch (\Throwable) {
                        // rota de detalhe ainda não existe
                    }
                }
                if ($urls) {
                    $entries[] = ['urls' => $urls, 'lastmod' => $item->updated_at];
                }
            }
        }

        return response()->view('sitemap-xml', ['entries' => $entries])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    protected function safe(callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return collect();
        }
    }
}
