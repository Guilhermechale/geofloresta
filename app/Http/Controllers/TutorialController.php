<?php

namespace App\Http\Controllers;

use App\Models\Tutorial;
use App\Support\Locales;
use App\Support\Preview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TutorialController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'plataforma' => ['nullable', 'in:'.implode(',', array_keys(Tutorial::PLATFORMS))],
            'nivel' => ['nullable', 'in:'.implode(',', Tutorial::LEVELS)],
        ]);
        $tutorials = Tutorial::published()
            ->when($f['plataforma'] ?? null, fn ($q, $v) => $q->where('platform', $v))
            ->when($f['nivel'] ?? null, fn ($q, $v) => $q->where('level', $v))
            ->orderByDesc('published_at')->get();

        return view('learn.index', [
            'tutorials' => $tutorials,
            'filters' => $f,
            'platforms' => Tutorial::published()->distinct()->pluck('platform')->all(),
        ]);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $locale = app()->getLocale();
        $preview = Preview::active($request);
        $q = fn () => Tutorial::query()->with('attachments')->when(! $preview, fn ($b) => $b->published());
        $t = $q()->where("slug->{$locale}", $slug)->first()
            ?? $q()->where('legacy_slug', $slug)->first()
            ?? $q()->where('slug', 'like', '%"'.str_replace(['%', '_'], ['\%', '\_'], $slug).'"%')->firstOrFail();

        $canonical = $t->url($locale);
        if (! $preview && rtrim($canonical, '/') !== rtrim($request->url(), '/')) {
            return redirect()->to($canonical, 301);
        }

        return self::render($t);
    }

    /** Também usado para o URL antigo do WordPress (/{slug}). */
    public static function render(Tutorial $t): View
    {
        Locales::setAlternates(collect(Locales::all())->mapWithKeys(fn ($l) => [$l => $t->url($l)])->all());

        return view('learn.show', ['tutorial' => $t->loadMissing('attachments'), 'locale' => $t->displayLocale(app()->getLocale())]);
    }
}
