<ul class="src-list" aria-label="{{ __('pages.legal.sources_table') }}">
    @foreach ($sources as $s)
        <li class="src-item">
            <div class="src-item__head">
                <a class="src-item__name" href="{{ $s['url'] }}" rel="noopener" lang="en">{{ $s['name'] }}</a>
                <span class="src-item__tags">
                    <span class="src-tag">{{ __('pages.licences.uses.'.$s['use']) }}</span>
                    <span class="src-tag src-tag--status">{{ __('pages.licences.statuses.'.$s['status']) }}</span>
                    @if (empty($s['verified']) && $s['status'] !== 'software')
                        <span class="badge">{{ __('pages.licences.unverified') }}</span>
                    @endif
                </span>
            </div>
            <dl class="src-item__body">
                <div><dt>{{ __('pages.licences.licence') }}</dt><dd><code lang="en">{{ $s['licence'] }}</code></dd></div>
                <div><dt>{{ __('pages.licences.attribution') }}</dt><dd lang="en">{{ $s['attribution'] }}</dd></div>
            </dl>
        </li>
    @endforeach
</ul>
