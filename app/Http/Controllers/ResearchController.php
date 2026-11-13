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
        $s = Study::query()->when(! Preview::active($request), fn ($b) => $b->published())->where('slug', $slug)->firstOrFail();
        // Em português o URL canónico é o do WordPress (/slug)
        if (app()->getLocale() === 'pt' && ! Preview::active($request)) {
            return redirect()->to($s->url('pt'), 301);
        }

        return self::render($s);
    }

    public static function render(Study $s): View
    {
        Locales::setAlternates(collect(Locales::all())->mapWithKeys(fn ($l) => [$l => $s->url($l)])->all());

        return view('research.show', ['study' => $s, 'locale' => $s->displayLocale(app()->getLocale())]);
    }
}
