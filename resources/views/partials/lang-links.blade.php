@foreach ($alternates as $lc => $url)
    @if (! $loop->first)<span aria-hidden="true">·</span>@endif
    <a href="{{ $url }}" hreflang="{{ $lc }}" lang="{{ $lc }}" data-set-lang="{{ $lc }}"
       @if ($lc === app()->getLocale()) aria-current="true" @endif
       aria-label="{{ __('site.lang.names.'.$lc) }}">{{ strtoupper($lc) }}</a>
@endforeach
