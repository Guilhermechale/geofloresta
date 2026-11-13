<div class="table-scroll">
<table class="table">
    <caption class="visually-hidden">{{ __('pages.legal.cookie_table') }}</caption>
    <thead>
        <tr>
            <th scope="col">{{ __('cookies.table.name') }}</th>
            <th scope="col">{{ __('cookies.table.type') }}</th>
            <th scope="col">{{ __('cookies.table.category') }}</th>
            <th scope="col">{{ __('cookies.table.purpose') }}</th>
            <th scope="col">{{ __('cookies.table.duration') }}</th>
            <th scope="col">{{ __('cookies.table.party') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            <tr>
                <td><code>{{ $item['name'] }}</code></td>
                <td>{{ __('cookies.types.'.$item['type']) }}</td>
                <td>{{ __('cookies.categories.'.$item['category'].'.title') }}</td>
                <td>{{ __('cookies.purposes.'.$item['purpose']) }}@if (! empty($item['note'])) <br><small class="muted">{{ __('cookies.notes.'.$item['note']) }}</small>@endif</td>
                <td>{{ \App\Support\Consent::durationLabel($item['duration']) }}</td>
                <td>{{ __('cookies.parties.'.$item['party']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
