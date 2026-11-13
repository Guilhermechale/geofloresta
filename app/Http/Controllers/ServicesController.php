<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Study;
use Illuminate\Contracts\View\View;

class ServicesController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.services', [
            'services' => Service::published()->orderBy('sort')->get(),
            'studies' => Study::published()->orderByDesc('published_at')->get(),
        ]);
    }
}
