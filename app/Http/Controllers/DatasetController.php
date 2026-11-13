<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Support\Locales;
use App\Support\Preview;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DatasetController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tema' => ['nullable', 'in:'.implode(',', Dataset::THEMES)],
            'regiao' => ['nullable', 'in:'.implode(',', Dataset::REGIONS)],
            'formato' => ['nullable', 'in:'.implode(',', array_keys(Dataset::FORMATS))],
            'de' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'ate' => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ]);

        $q = Dataset::query()->published()->with('files')
            ->when($f['q'] ?? null, fn (Builder $b, $s) => $b->where('search_text', 'like', '%'.$s.'%'))
            ->when($f['tema'] ?? null, fn (Builder $b, $v) => $b->where('theme', $v))
            ->when($f['regiao'] ?? null, fn (Builder $b, $v) => $b->where('region', $v))
            ->when($f['formato'] ?? null, fn (Builder $b, $v) => $b->whereHas('files', fn ($w) => $w->where('format', $v)))
            ->when($f['de'] ?? null, fn (Builder $b, $v) => $b->where('year_end', '>=', $v))
            ->when($f['ate'] ?? null, fn (Builder $b, $v) => $b->where('year_start', '<=', $v))
            ->orderByDesc('featured')->orderByRaw("CASE WHEN hosting = 'hosted' THEN 0 ELSE 1 END")->orderBy('theme');

        $all = Dataset::query()->published();

        return view('data.index', [
            'datasets' => $q->get(),
            'filters' => $f,
            'themes' => (clone $all)->distinct()->orderBy('theme')->pluck('theme')->all(),
            'regions' => (clone $all)->distinct()->pluck('region')->all(),
        ]);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $locale = app()->getLocale();
        $preview = Preview::active($request);
        $base = fn () => Dataset::query()->with('files')->when(! $preview, fn ($b) => $b->published());

        $dataset = $base()->where("slug->{$locale}", $slug)->first();
        if (! $dataset) {
            // Slug de outro idioma (ou PT por falta de tradução): redirecciona para o URL canónico deste idioma
            $dataset = $base()->where('slug', 'like', '%"'.str_replace(['%', '_'], ['\%', '\_'], $slug).'"%')->firstOrFail();
            $canonical = $dataset->url($locale);
            if (! $preview && rtrim($canonical, '/') !== rtrim($request->url(), '/')) {
                return redirect()->to($canonical, 301);
            }
        }

        Locales::setAlternates(collect(Locales::all())->mapWithKeys(fn ($l) => [$l => $dataset->url($l)])->all());

        return view('data.show', [
            'dataset' => $dataset,
            'locale' => $dataset->displayLocale($locale),
        ]);
    }
}
