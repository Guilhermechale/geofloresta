{{-- Vídeo-guia de uma ferramenta de mapas. Fachada: nada é pedido ao YouTube antes de o visitante carregar em play. --}}
@props(['id', 'cover', 'key' => 'video', 'anchor' => 'video'])
@php $loc = app()->getLocale(); @endphp
<section class="section section--tight tool-video" id="{{ $anchor }}" aria-labelledby="{{ $anchor }}-title">
    <div class="wrap grid">
        <div class="span-4 tool-video__text">
            <p class="meta">GEOFLORESTA · 1:16</p>
            <h2 id="{{ $anchor }}-title">{{ __('map.'.$key.'.title') }}</h2>
            <p>{{ __('map.'.$key.'.text') }}</p>
            <p><a class="link-arrow" href="https://www.youtube.com/watch?v={{ $id }}" rel="noopener" target="_blank">{{ __('social.video_youtube') }}</a></p>
        </div>
        <div class="span-7 start-6">
            <div class="video-facade video-facade--tool" data-video-facade data-video-id="{{ $id }}" data-video-lang="{{ $loc }}" data-video-title="{{ __('map.'.$key.'.frame') }}">
                <button type="button" class="video-facade__btn" data-video-play aria-label="{{ __('map.'.$key.'.play') }}">
                    <img src="{{ asset($cover) }}" width="1280" height="720" alt="{{ __('map.'.$key.'.alt') }}" loading="lazy" decoding="async">
                    <span class="video-facade__play" aria-hidden="true"><svg viewBox="0 0 68 48" width="68" height="48"><path d="M66.5 7.7c-.8-2.9-3-5.2-5.9-6C55.3.3 34 .3 34 .3s-21.3 0-26.6 1.4c-2.9.8-5.1 3.1-5.9 6C.1 13 .1 24 .1 24s0 11 1.4 16.3c.8 2.9 3 5.2 5.9 6 5.3 1.4 26.6 1.4 26.6 1.4s21.3 0 26.6-1.4c2.9-.8 5.1-3.1 5.9-6 1.4-5.3 1.4-16.3 1.4-16.3s0-11-1.4-16.3z" fill="#f06a33"/><path d="M27 34l18-10-18-10z" fill="#fff"/></svg></span>
                </button>
            </div>
            <p class="small muted video-facade__notice">{{ __('social.video_notice') }}</p>
        </div>
    </div>
</section>
