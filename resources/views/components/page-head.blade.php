@props(['title', 'lede' => null, 'crumbs' => [], 'meta' => null])
<header class="page-head">
    <div class="wrap">
        @if (count($crumbs) > 1)
            <nav aria-label="{{ __('pages.breadcrumb') }}">
                <ol class="breadcrumb meta">
                    @foreach ($crumbs as $c)
                        <li>@if (! $loop->last)<a href="{{ $c['url'] }}">{{ $c['label'] }}</a>@else<span aria-current="page">{{ $c['label'] }}</span>@endif</li>
                    @endforeach
                </ol>
            </nav>
        @endif
        @if ($meta)<p class="meta">{{ $meta }}</p>@endif
        <h1>{{ $title }}</h1>
        @if ($lede)<p class="lede muted">{{ $lede }}</p>@endif
        {{ $slot }}
    </div>
</header>
