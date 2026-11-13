{{--
  Página de erro autónoma (500, 503): sem base de dados, sem sessão e sem ficheiros externos,
  para funcionar mesmo quando a aplicação falha ou está em manutenção. Mostra os três idiomas.
--}}
@php
    $path = trim(request()->path(), '/');
    $first = explode('/', $path)[0] ?? '';
    $lang = in_array($first, ['fr', 'en'], true) ? $first : 'pt';
    $order = array_unique([$lang, 'pt', 'fr', 'en']);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="color-scheme" content="light dark">
<title>{{ __("errors.$code.title", [], $lang) }} · GEOFLORESTA</title>
<link rel="icon" href="/favicon.ico" sizes="any">
<style>
:root{--bg:#F4F1E8;--ink:#14201A;--muted:#4B5A50;--rule:#CFC8B6;--accent:#B4441A}
@media (prefers-color-scheme:dark){:root{--bg:#0B1912;--ink:#E9E6DA;--muted:#A9B3A6;--rule:#2A4134;--accent:#F06A33}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:400 1.0625rem/1.6 "IBM Plex Sans",system-ui,-apple-system,"Segoe UI",sans-serif}
main{max-width:46rem;margin:0 auto;padding:4rem 1rem}
.meta{font:500 .8125rem/1.4 "IBM Plex Mono",ui-monospace,Consolas,monospace;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
h1{font:400 clamp(2rem,1.4rem + 2.4vw,3.25rem)/1.12 Newsreader,Georgia,serif;margin:.25rem 0 1rem}
section+section{border-top:1px solid var(--rule);margin-top:2rem;padding-top:1.5rem}
section+section h2{font:400 1.25rem/1.3 Newsreader,Georgia,serif;margin:0 0 .25rem}
a{color:var(--accent)}
</style>
</head>
<body>
<main>
    <p class="meta">GEOFLORESTA · {{ __('errors.code', ['code' => $code], $lang) }}</p>
    @foreach ($order as $i => $l)
        <section lang="{{ $l }}">
            @if ($i === 0)
                <h1>{{ __("errors.$code.title", [], $l) }}</h1>
            @else
                <h2>{{ __("errors.$code.title", [], $l) }}</h2>
            @endif
            <p>{{ __("errors.$code.text", [], $l) }}</p>
        </section>
    @endforeach
    <p><a href="/">geofloresta.com</a> · <a href="mailto:{{ config('geofloresta.contact_email') }}">{{ config('geofloresta.contact_email') }}</a></p>
</main>
</body>
</html>
