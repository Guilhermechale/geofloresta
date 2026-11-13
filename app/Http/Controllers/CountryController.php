<?php

namespace App\Http\Controllers;

use App\Geo\World;
use App\Models\Dataset;
use App\Support\OpenData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Dados abertos por país: qualquer país do mundo, com focos de incêndio, limites, floresta, água, população… */
class CountryController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:60']]);
        $q = trim((string) ($f['q'] ?? ''));
        $needle = $q !== '' ? strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $q) ?: $q) : null;
        $groups = World::byContinent();
        if ($needle) {
            foreach ($groups as $k => $list) {
                $groups[$k] = array_values(array_filter($list, function ($c) use ($needle) {
                    $hay = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', implode(' ', $c['names'])) ?: '').' '.strtolower($c['iso']);

                    return str_contains($hay, $needle);
                }));
            }
            $groups = array_filter($groups);
        }
        unset($groups['Seven seas (open ocean)'], $groups['Antarctica']);
        $fires = World::fires();

        return view('countries.index', [
            'groups' => $groups,
            'q' => $q,
            'fires' => $fires['countries'] ?? [],
            'firesUpdated' => $fires['updated_at'] ?? null,
            'top' => array_slice($fires['countries'] ?? [], 0, 10, true),
            'total' => count(World::all()),
        ]);
    }

    public function show(string $iso): View
    {
        $c = World::find($iso);
        abort_unless($c, 404);
        $fires = World::fires();

        return view('countries.show', [
            'c' => $c,
            'name' => World::name($c),
            'sections' => OpenData::forCountry($c),
            'firesUpdated' => $fires['updated_at'] ?? null,
            'local' => $c['iso'] === 'MOZ'
                ? Dataset::query()->published()->orderByDesc('featured')->limit(8)->get()
                : collect(),
        ]);
    }
}
