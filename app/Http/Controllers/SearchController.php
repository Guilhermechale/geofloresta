<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\Study;
use App\Models\Tutorial;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Pesquisa interna em datasets, tutoriais e estudos (MySQL FULLTEXT; LIKE em SQLite/testes). */
class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $results = collect();

        if (mb_strlen($q) >= 2) {
            $results = $this->search(Dataset::class, 'dataset', $q)
                ->concat($this->search(Tutorial::class, 'tutorial', $q))
                ->concat($this->search(Study::class, 'study', $q))
                ->sortByDesc('score')->values()->take(50);
        }

        return view('pages.search', ['q' => $q, 'results' => $results]);
    }

    protected function search(string $model, string $type, string $q): Collection
    {
        /** @var Builder $query */
        $query = $model::query()->published();

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // Modo booleano: cada palavra com prefixo (ex.: "incend*")
            $terms = collect(preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY))
                ->filter(fn ($t) => mb_strlen($t) >= 2)->map(fn ($t) => '+'.$t.'*')->implode(' ');
            if ($terms === '') {
                return collect();
            }
            $query->selectRaw('*, MATCH(search_text) AGAINST(? IN BOOLEAN MODE) AS score', [$terms])
                ->whereRaw('MATCH(search_text) AGAINST(? IN BOOLEAN MODE)', [$terms]);
        } else {
            $query->select('*')->selectRaw('1 AS score')->where('search_text', 'like', '%'.$q.'%');
        }

        return $query->limit(30)->get()->map(function ($item) use ($type) {
            $locale = $item->displayLocale(app()->getLocale());

            return (object) [
                'type' => $type,
                'title' => $item->getTranslation('title', $locale),
                'summary' => $item->getTranslation('summary', $locale, false),
                'url' => $item->url(),
                'locale' => $locale,
                'score' => (float) $item->score,
            ];
        });
    }
}
