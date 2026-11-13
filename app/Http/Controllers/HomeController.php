<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Support\FireSummary;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // Destaques: os marcados no painel; senão, os alojados mais recentes
        $datasets = Dataset::published()->with('files')
            ->orderByDesc('featured')->orderByRaw("CASE WHEN hosting = 'hosted' THEN 0 ELSE 1 END")->orderByDesc('updated_at')
            ->limit(3)->get();

        return view('pages.home', [
            'fire' => FireSummary::read(),
            'datasets' => $datasets,
            'tutorials' => \App\Models\Tutorial::published()->orderByDesc('featured')->orderByDesc('published_at')->limit(4)->get(),
            'studies' => \App\Models\Study::published()->orderByDesc('published_at')->limit(3)->get(),
        ]);
    }
}
