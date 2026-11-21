@props(['href', 'image' => null, 'title', 'text' => null, 'badge' => null, 'eager' => false, 'lang' => null])
{{-- Cartão clicável: imagem, título e uma linha. Usado em Mapas, na página inicial e nas listas. --}}
<a {{ $attributes->merge(['class' => 'tcard']) }} href="{{ $href }}">
    @if ($image)
        <span class="tcard__media">
            @if (str_starts_with($image, '<'))
                {!! $image !!}
            @else
                <img src="{{ str_starts_with($image, 'http') ? $image : asset($image) }}" width="800" height="500" alt="" decoding="async" @if ($eager) fetchpriority="high" @else loading="lazy" @endif>
            @endif
            @if ($badge)<span class="tcard__badge">{{ $badge }}</span>@endif
        </span>
    @endif
    <span class="tcard__body">
        <span class="tcard__title" @if ($lang) lang="{{ $lang }}" @endif>{{ $title }}</span>
        @if ($text)<span class="tcard__text">{{ $text }}</span>@endif
    </span>
</a>
