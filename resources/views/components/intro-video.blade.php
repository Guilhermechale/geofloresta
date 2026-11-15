@php
    $v = config('geofloresta.intro_video');
    $id = $v['id'] ?? null;
    $loc = app()->getLocale();
    $yt = config('geofloresta.social.youtube');
@endphp
@if ($id)
    <section class="section intro-video" aria-labelledby="intro-video-title">
        <div class="wrap grid">
            <div class="span-4 intro-video__text">
                <p class="meta">GEOFLORESTA · 1:36</p>
                <h2 id="intro-video-title">{{ __('social.video_title') }}</h2>
                <p>{{ __('social.video_text') }}</p>
                <p class="intro-video__links">
                    <a class="link-arrow" href="https://www.youtube.com/watch?v={{ $id }}" rel="noopener" target="_blank">{{ __('social.video_youtube') }}</a>
                    @if ($yt)<a class="link-arrow" href="{{ $yt }}?sub_confirmation=1" rel="noopener" target="_blank">{{ __('social.video_subscribe') }}</a>@endif
                </p>
            </div>
            <div class="span-7 start-6">
                {{-- Fachada: nada é pedido ao YouTube antes de o visitante carregar em play --}}
                <div class="video-facade" data-video-facade data-video-id="{{ $id }}" data-video-lang="{{ $loc }}" data-video-title="{{ __('social.video_frame') }}">
                    <button type="button" class="video-facade__btn" data-video-play aria-label="{{ __('social.video_play') }}">
                        <img src="{{ asset('img/video-como-usar.webp') }}" width="1280" height="720" alt="{{ __('social.video_alt') }}" loading="lazy" decoding="async">
                        <span class="video-facade__play" aria-hidden="true"><svg viewBox="0 0 68 48" width="68" height="48"><path d="M66.5 7.7c-.8-2.9-3-5.2-5.9-6C55.3.3 34 .3 34 .3s-21.3 0-26.6 1.4c-2.9.8-5.1 3.1-5.9 6C.1 13 .1 24 .1 24s0 11 1.4 16.3c.8 2.9 3 5.2 5.9 6 5.3 1.4 26.6 1.4 26.6 1.4s21.3 0 26.6-1.4c2.9-.8 5.1-3.1 5.9-6 1.4-5.3 1.4-16.3 1.4-16.3s0-11-1.4-16.3z" fill="#f06a33"/><path d="M27 34l18-10-18-10z" fill="#fff"/></svg></span>
                    </button>
                </div>
                <p class="small muted video-facade__notice">{{ __('social.video_notice') }}</p>
            </div>
        </div>
    </section>
@endif
