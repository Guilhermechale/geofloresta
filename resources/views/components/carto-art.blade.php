{{-- Motivo cartográfico: graticule de 1° e curvas de nível discretas (decorativo) --}}
<svg class="hero__art" viewBox="0 0 1200 640" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
    <defs>
        <path id="gf-blob" d="M0-100C38-104 78-84 96-52 112-22 104 14 92 44 78 80 46 104 6 102-34 100-74 84-94 50-110 20-104-22-86-52-66-84-32-98 0-100Z"/>
    </defs>
    <g class="graticule" fill="none" stroke-width="1">
        @for ($x = 0; $x <= 1200; $x += 150)
            <line x1="{{ $x }}" y1="0" x2="{{ $x }}" y2="640"/>
        @endfor
        @for ($y = 20; $y <= 640; $y += 150)
            <line x1="0" y1="{{ $y }}" x2="1200" y2="{{ $y }}"/>
        @endfor
    </g>
    <g fill="none" stroke="currentColor" stroke-width="1">
        @foreach ([0.6, 1.1, 1.6, 2.15, 2.75, 3.4, 4.1, 4.9] as $i => $s)
            <use href="#gf-blob" vector-effect="non-scaling-stroke"
                 transform="translate({{ 930 + $i * 6 }} {{ 250 + $i * 4 }}) rotate({{ 8 + $i * 3 }}) scale({{ $s }} {{ $s * 0.82 }})"/>
        @endforeach
        @foreach ([0.5, 0.95, 1.45] as $i => $s)
            <use href="#gf-blob" vector-effect="non-scaling-stroke"
                 transform="translate({{ 1110 - $i * 4 }} {{ 560 }}) rotate({{ -20 - $i * 4 }}) scale({{ $s }} {{ $s * 0.7 }})"/>
        @endforeach
    </g>
</svg>
