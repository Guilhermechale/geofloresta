{{-- Cabeçalho de secção: etiqueta numerada, título, frase curta e ligação opcional («Ver todos»). --}}
@props(['id', 'num' => null, 'eyebrow', 'title', 'text' => null, 'href' => null, 'link' => null])
<header {{ $attributes->merge(['class' => 'sec-title']) }}>
    <div class="sec-title__main">
        <p class="sec-title__eyebrow">@if ($num)<span class="sec-title__num">{{ $num }}</span>@endif{{ $eyebrow }}</p>
        <h2 id="{{ $id }}" class="sec-title__h">{{ $title }}</h2>
        @if ($text)<p class="sec-title__text">{{ $text }}</p>@endif
    </div>
    @if ($href && $link)<a class="link-arrow sec-title__link" href="{{ $href }}">{{ $link }}</a>@endif
</header>
