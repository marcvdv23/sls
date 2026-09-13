@extends('sls.layouts.app')

@section('title', 'Agent Coverage')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'Agent Coverage')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.review', ['focus' => $focus, 'region' => $region]) }}">Review desk</a>
    <a class="button secondary" href="{{ route('sls.intelligence.sources', ['region' => $region]) }}">Source coverage</a>
@endsection

@push('head')
    <style>
        .coverage-summary { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; }
        .filters { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
        .coverage-table { min-width:1180px; table-layout:fixed; }
        .summary-cell { max-width:380px; color:var(--text-secondary); }
        @media (max-width:980px) {
            .coverage-summary { grid-template-columns:1fr; }
            .coverage-table { min-width:900px; }
        }
    </style>
@endpush

@section('content')
    @php($austinTz = $austinTz ?? 'America/Chicago')

    <div class="stack">
        <section class="coverage-summary">
            <div class="panel stat">
                <strong>{{ $researchedCountries }}</strong>
                <span class="muted">countries with a recorded agent run</span>
            </div>
            <div class="panel stat">
                <strong>{{ $totalCountries }}</strong>
                <span class="muted">countries in selected scope</span>
            </div>
        </section>

        <section class="panel">
            <p class="eyebrow">Filters</p>
            <h2>Coverage by focus and region</h2>
            <div class="filters">
                @foreach ($focuses as $focusKey => $focusConfig)
                    <a class="button {{ $focus === $focusKey ? '' : 'secondary' }}" href="{{ route('sls.intelligence.coverage', ['focus' => $focusKey, 'region' => $region]) }}">{{ $focusConfig['label'] }}</a>
                @endforeach
                <a class="button {{ $region === 'all' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.coverage', ['focus' => $focus, 'region' => 'all']) }}">All regions</a>
                <a class="button {{ $region === 'africa' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.coverage', ['focus' => $focus, 'region' => 'africa']) }}">Africa</a>
                <a class="button {{ $region === 'asia' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.coverage', ['focus' => $focus, 'region' => 'asia']) }}">Asia</a>
                <a class="button {{ $region === 'caribbean' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.coverage', ['focus' => $focus, 'region' => 'caribbean']) }}">Caribbean</a>
                <a class="button {{ $region === 'latin_america' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.coverage', ['focus' => $focus, 'region' => 'latin_america']) }}">Latin America</a>
                <a class="button {{ $region === 'north_america' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.coverage', ['focus' => $focus, 'region' => 'north_america']) }}">North America</a>
                <a class="button {{ $region === 'europe' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.coverage', ['focus' => $focus, 'region' => 'europe']) }}">Europe</a>
            </div>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Agent Coverage</p>
                <h2>When each country was last researched</h2>
            </div>
            <div class="table-wrap">
                <table class="data-table coverage-table">
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>Region</th>
                            <th>Last researched (Austin time)</th>
                            <th>Next scheduled run (Austin time)</th>
                            <th>Last captured item (Austin time)</th>
                            <th>Items in last run</th>
                            <th>Status</th>
                            <th>Sources checked</th>
                            <th>Queue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($countryStatus as $country)
                            <tr>
                                <td>{{ $country['name'] }} <span class="muted">({{ $country['iso'] }})</span></td>
                                <td>{{ $country['region'] }}</td>
                                <td>{{ $country['last_researched']?->copy()->timezone($austinTz)->format('Y-m-d H:i') ?? 'Not recorded yet' }}</td>
                                <td>{{ $country['next_scheduled_run'] }}</td>
                                <td>{{ $country['last_update']?->copy()->timezone($austinTz)->format('Y-m-d H:i') ?? 'No item captured' }}</td>
                                <td>{{ $country['items_found'] ?? '-' }}</td>
                                <td>
                                    <span class="pill {{ $country['status'] === 'completed' ? 'good' : 'warn' }}">{{ $country['status'] }}</span>
                                </td>
                                <td class="summary-cell">{{ collect($country['sources_checked'])->take(6)->implode(', ') ?: 'No run log yet' }}</td>
                                <td>
                                    @if ($country['priority_requested_at'])
                                        <span class="pill warn">Priority queued</span>
                                    @else
                                        <form method="post" action="{{ route('sls.intelligence.priorities.store') }}">
                                            @csrf
                                            <input type="hidden" name="country_iso" value="{{ $country['iso'] }}">
                                            <input type="hidden" name="country_name" value="{{ $country['name'] }}">
                                            <input type="hidden" name="focus" value="{{ $country['priority_focus'] }}">
                                            <button class="secondary tiny" type="submit">Run next</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
