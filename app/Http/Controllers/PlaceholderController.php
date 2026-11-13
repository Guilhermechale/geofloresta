<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Página temporária do ambiente de teste (substituída até ao M8). */
class PlaceholderController extends Controller
{
    public function __invoke(Request $request): View
    {
        $page = $request->route()->defaults['page'] ?? 'home';

        return view('pages.placeholder', ['page' => $page]);
    }
}
