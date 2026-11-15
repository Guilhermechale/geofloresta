@props(['variant' => 'icons'])
@php
    use App\Support\Social;
    $links = Social::links();
@endphp
@if ($links)
    {{-- icons: só ícones (rodapé) · list: ícone + nome (Contacto) --}}
    <ul {{ $attributes->class(['social', 'social--'.$variant]) }}>
        @foreach ($links as $network => $url)
            <li>
                <a href="{{ $url }}" rel="noopener me" target="_blank" class="social__link social__link--{{ $network }}"
                   @if ($variant === 'icons') aria-label="{{ __('social.open', ['network' => __('social.'.$network)]) }}" title="{{ __('social.'.$network) }}" @endif>
                    {!! Social::svg($network) !!}
                    @if ($variant !== 'icons')<span>{{ __('social.'.$network) }}</span>@endif
                </a>
            </li>
        @endforeach
    </ul>
@endif
