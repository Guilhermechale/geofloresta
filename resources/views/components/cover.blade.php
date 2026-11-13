@props(['path' => null, 'alt' => '', 'width' => 640, 'eager' => false, 'class' => 'cover'])
@php $img = \App\Support\Cover::image($path, (int) $width); @endphp
@if ($img)
    <img {{ $attributes->merge(['class' => $class]) }} src="{{ $img['src'] }}" width="{{ $img['width'] }}" height="{{ $img['height'] }}" alt="{{ $alt }}" decoding="async" @if ($eager) fetchpriority="high" @else loading="lazy" @endif>
@endif
