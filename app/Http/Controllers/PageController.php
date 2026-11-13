<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Service;
use App\Support\Preview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Páginas de texto editáveis no painel: Sobre e as cinco páginas legais. */
class PageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $key = $request->route()->defaults['key'];
        $page = Page::where('key', $key)
            ->when(! Preview::active($request), fn ($q) => $q->published())
            ->firstOrFail();

        $locale = $page->displayLocale(app()->getLocale());

        return view($key === 'about' ? 'pages.about' : 'pages.legal', [
            'page' => $page,
            'locale' => $locale,
            'fallback' => $locale !== app()->getLocale(),
            'servicesCount' => $key === 'about' ? Service::published()->count() : null,
        ]);
    }
}
