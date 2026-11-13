<div class="table-scroll">
<table class="table">
    <caption class="visually-hidden">{{ __('pages.legal.sources_table') }}</caption>
    <thead>
        <tr>
            <th scope="col">{{ __('pages.licences.name') }}</th>
            <th scope="col">{{ __('pages.licences.use') }}</th>
            <th scope="col">{{ __('pages.licences.licence') }}</th>
            <th scope="col">{{ __('pages.licences.attribution') }}</th>
            <th scope="col">{{ __('pages.licences.status') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($sources as $s)
            <tr>
                <td><a href="{{ $s['url'] }}" rel="noopener" lang="en">{{ $s['name'] }}</a></td>
                <td>{{ __('pages.licences.uses.'.$s['use']) }}</td>
                <td><code lang="en">{{ $s['licence'] }}</code></td>
                <td><small lang="en">{{ $s['attribution'] }}</small></td>
                <td>
                    {{ __('pages.licences.statuses.'.$s['status']) }}
                    @if (empty($s['verified']) && $s['status'] !== 'software')
                        <br><span class="badge">{{ __('pages.licences.unverified') }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
