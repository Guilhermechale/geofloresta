@php
    use App\Models\Dataset;
    use App\Support\Breadcrumbs;
    $crumbs = Breadcrumbs::make([['label' => __('data.title'), 'url' => lroute('data')]]);
    $loc = app()->getLocale();
    $active = array_filter($filters);
@endphp
<x-layout :title="__('data.title')" :description="__('data.description')" :noindex="! empty($active)" :jsonld="[Breadcrumbs::jsonld($crumbs)]">
    <x-page-head :title="__('data.title')" :lede="__('data.lede')" :crumbs="$crumbs" />

    <div class="section section--tight">
        <div class="wrap grid">
            <aside class="span-3" aria-labelledby="filters-title">
                <form method="get" action="{{ lroute('data') }}" class="filters">
                    <h2 id="filters-title" class="meta">{{ __('data.filters') }}</h2>
                    <div class="field">
                        <label for="f-q">{{ __('data.search') }}</label>
                        <input id="f-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100">
                    </div>
                    <div class="field">
                        <label for="f-tema">{{ __('data.theme') }}</label>
                        <select id="f-tema" name="tema">
                            <option value="">{{ __('data.any') }}</option>
                            @foreach ($themes as $t)
                                <option value="{{ $t }}" @selected(($filters['tema'] ?? '') === $t)>{{ __('data.themes.'.$t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="f-regiao">{{ __('data.region') }}</label>
                        <select id="f-regiao" name="regiao">
                            <option value="">{{ __('data.any') }}</option>
                            @foreach ($regions as $r)
                                <option value="{{ $r }}" @selected(($filters['regiao'] ?? '') === $r)>{{ __('data.regions.'.$r) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="f-formato">{{ __('data.format') }}</label>
                        <select id="f-formato" name="formato">
                            <option value="">{{ __('data.any') }}</option>
                            @foreach (Dataset::FORMATS as $k => $label)
                                <option value="{{ $k }}" @selected(($filters['formato'] ?? '') === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <fieldset class="field field--pair">
                        <legend>{{ __('data.period') }}</legend>
                        <label for="f-de" class="visually-hidden">{{ __('data.from') }}</label>
                        <input id="f-de" type="number" name="de" inputmode="numeric" min="1900" max="2100" placeholder="{{ __('data.from') }}" value="{{ $filters['de'] ?? '' }}">
                        <label for="f-ate" class="visually-hidden">{{ __('data.to') }}</label>
                        <input id="f-ate" type="number" name="ate" inputmode="numeric" min="1900" max="2100" placeholder="{{ __('data.to') }}" value="{{ $filters['ate'] ?? '' }}">
                    </fieldset>
                    <button type="submit" class="btn btn--primary">{{ __('data.apply') }}</button>
                    @if ($active)
                        <p><a href="{{ lroute('data') }}">{{ __('data.clear') }}</a></p>
                    @endif
                </form>
            </aside>

            <section class="span-8 start-5" aria-live="polite">
                <div class="notice country-cta">
                    <h2 class="h3">{{ __('countries.title') }}</h2>
                    <p>{{ __('countries.description') }}</p>
                    <a class="btn btn--primary" href="{{ lroute('countries') }}">{{ __('countries.search') }}</a>
                </div>
                <p class="meta">{{ trans_choice('data.count', $datasets->count(), ['count' => $datasets->count()]) }}</p>
                @php
                    $themeImg = ['land-cover' => 'dados', 'forest-loss' => 'ndvi', 'fire' => 'incendios', 'hydrology' => 'satelite', 'biomass' => 'servicos', 'boundaries' => 'limites', 'climate' => 'clima'];
                @endphp
                <div class="tcards tcards--data">
                    @foreach ($datasets as $d)
                        @php $l = $d->displayLocale($loc); @endphp
                        <x-tool-card :href="$d->url()" :image="'img/cards/'.($themeImg[$d->theme] ?? 'dados').'.webp'" :badge="__('data.themes.'.$d->theme)"
                            :title="$d->getTranslation('title', $l)" :lang="$l !== $loc ? $l : null"
                            :text="__('data.regions.'.$d->region).($d->files->isNotEmpty() ? ' · '.$d->files->pluck('format')->unique()->map(fn ($f) => Dataset::FORMATS[$f] ?? $f)->implode(', ') : ' · '.__('data.external'))" />
                    @endforeach
                </div>

                <div class="notice">
                    <p>{{ __('data.custom') }}</p>
                    <a class="link-arrow" href="{{ lroute('contact') }}?assunto=data">{{ __('data.custom_btn') }}</a>
                </div>
            </section>
        </div>
    </div>
</x-layout>
