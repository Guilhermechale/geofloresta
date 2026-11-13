@php
    use App\Support\Consent;
    $categories = Consent::categories();
    $showBanner = Consent::needsBanner(request());
    $policy = '<a href="'.e(lroute('cookies')).'">'.e(__('cookies.policy_link')).'</a>';
    $analyticsText = Consent::analyticsAvailable() ? __('cookies.banner_analytics') : '';
@endphp
{{-- Banner: só no HTML quando é preciso perguntar (sem flash, sem deslocar o layout: fica fixo por cima) --}}
@if ($showBanner)
    <section class="cookie-banner" data-cookie-banner aria-labelledby="cookie-banner-title" role="region">
        <div class="wrap cookie-banner__inner">
            <div class="cookie-banner__text">
                <h2 id="cookie-banner-title" class="h4">{{ __('cookies.banner_title') }}</h2>
                <p>{{ __('cookies.banner_text', ['analytics' => $analyticsText]) }} <a href="{{ lroute('cookies') }}">{{ __('cookies.policy_link') }}</a>.</p>
            </div>
            <div class="cookie-banner__actions">
                <button type="button" class="btn" data-consent="all">{{ __('cookies.accept') }}</button>
                <button type="button" class="btn" data-consent="none">{{ __('cookies.reject') }}</button>
                <button type="button" class="btn" data-cookie-prefs>{{ __('cookies.customise') }}</button>
            </div>
        </div>
    </section>
@endif

{{-- Painel "Personalizar": <dialog> nativo (foco preso, Esc fecha) --}}
<dialog class="cookie-dialog" data-cookie-dialog aria-labelledby="cookie-dialog-title">
    <form method="dialog" class="cookie-dialog__form" data-cookie-form>
        <div class="cookie-dialog__head">
            <h2 id="cookie-dialog-title" class="h3">{{ __('cookies.panel_title') }}</h2>
            <button type="submit" value="cancel" class="icon-btn" aria-label="{{ __('cookies.close') }}" formnovalidate>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <p class="small">{!! __('cookies.panel_text', ['link' => $policy]) !!}</p>
        <ul class="consent-list">
            @foreach ($categories as $cat)
                <li class="consent-item">
                    <div>
                        <h3 class="h4" id="cat-{{ $cat }}">{{ __("cookies.categories.$cat.title") }}</h3>
                        <p class="small muted" id="cat-{{ $cat }}-d">{{ __("cookies.categories.$cat.text") }}</p>
                    </div>
                    @if ($cat === 'necessary')
                        <span class="badge">{{ __('cookies.always_on') }}</span>
                    @else
                        <label class="switch">
                            <input type="checkbox" role="switch" name="{{ $cat }}" aria-labelledby="cat-{{ $cat }}" aria-describedby="cat-{{ $cat }}-d">
                            <span class="switch__track" aria-hidden="true"></span>
                        </label>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="cookie-dialog__actions">
            <button type="button" class="btn" data-consent="all">{{ __('cookies.accept') }}</button>
            <button type="button" class="btn" data-consent="none">{{ __('cookies.reject') }}</button>
            <button type="button" class="btn btn--primary" data-consent="save">{{ __('cookies.save') }}</button>
        </div>
    </form>
</dialog>
<p class="visually-hidden" role="status" aria-live="polite" data-consent-status data-saved-text="{{ __('cookies.saved') }}"></p>
