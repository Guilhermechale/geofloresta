<?php

namespace App\Http\Controllers;

use App\Support\FireSummary;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.home', [
            'fire' => FireSummary::read(),
        ]);
    }
}
