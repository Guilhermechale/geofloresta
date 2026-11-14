<?php

namespace App\Http\Controllers;

use App\Models\Study;
use App\Support\Locales;
use App\Support\Preview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Pesquisas: artigos científicos publicados (modelo Study). */
class ResearchController extends Controller
{
    public function index(): View
    {
        return view('research.index', [
            'studies' => Study::published()->orderByDesc('published_at')->get(),
        ]);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $locale = app()->getLocale();
        $base = fn () => Study::query()->when(! Preview::active($request), fn ($b) => $b->published());
        // FR/EN: procura pelo endereço traduzido; se não existir, aceita o slug português (endereço antigo)
        $s = in_array($locale, Study::LOCALISED, true) ? $base()->where("slugs->{$locale}", $slug)->first() : null;
        $s ??= $base()->where('slug', $slug)->firstOrFail();

        // PT: o URL canónico é o do WordPress (/slug). FR/EN: endereço antigo -> novo, com 301.
        if (! Preview::active($request) && ($locale === 'pt' || $s->localSlug($locale) !== $slug)) {
            return redirect()->to($s->url($locale), 301);
        }

        return self::render($s);
    }

    public static function render(Study $s): View
    {
        Locales::setAlternates(collect(Locales::all())->mapWithKeys(fn ($l) => [$l => $s->url($l)])->all());

        return view('research.show', ['study' => $s, 'locale' => $s->displayLocale(app()->getLocale())]);
    }
}
