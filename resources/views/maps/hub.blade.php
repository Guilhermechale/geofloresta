@php
    use App\Support\Breadcrumbs;
    use App\Support\MapTools;
    $crumbs = Breadcrumbs::make([['label' => __('map.nav'), 'url' => lroute('maps')]]);
@endphp
<x-layout :title="__('map.hub_title')" :description="__('map.hub_lede')" :jsonld="array_merge([Breadcrumbs::jsonld($crumbs)], \App\Support\Schema::mapsHub())">
    <x-page-head :title="__('map.hub_title')" :lede="__('map.hub_lede')" :crumbs="$crumbs" />
    <div class="section section--tight">
        <div class="wrap">
            <div class="tcards tcards--tools">
                @foreach (MapTools::TOOLS as $key => $t)
                    <x-tool-card :href="MapTools::url($key)" :image="$t['image']" :title="__('map.tools.'.$key.'.title')" :text="__('map.tools.'.$key.'.text')" :eager="$loop->first" :class="$loop->first ? 'tcard--wide' : ''" />
                @endforeach
            </div>
        </div>
    </div>
    <x-service-cta />
</x-layout>
