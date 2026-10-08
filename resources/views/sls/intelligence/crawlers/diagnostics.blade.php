@extends('sls.layouts.app')

@section('title', 'Crawler Diagnostics')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'Crawler Diagnostics')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.crawlers.index') }}">Intelligence crawlers</a>
    <a class="button secondary" href="{{ route('sls.intelligence.sources') }}">Sources</a>
    <a class="button secondary" href="{{ route('sls.intelligence.keywords') }}">Keywords</a>
@endsection

@push('head')
    <style>
        .diagnostics-page { display:grid; gap:14px; }
        .diagnostic-filter { display:grid; grid-template-columns:160px 220px minmax(180px, 1fr) 150px auto; gap:10px; align-items:end; }
        .diagnostic-filter label { color:var(--text-secondary); display:grid; font-size:12px; font-weight:800; gap:5px; }
        .diagnostic-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:10px; }
        .diagnostic-grid strong { display:block; font-size:24px; line-height:1.1; }
        .diagnostic-table { min-width:1180px; }
        .diagnostic-table th, .diagnostic-table td { vertical-align:top; }
        .wide-text { overflow-wrap:anywhere; min-width:300px; }
        .source-list { color:var(--text-secondary); font-size:12px; line-height:1.4; max-width:520px; overflow-wrap:anywhere; }
        .state-pill { border-radius:999px; display:inline-flex; padding:4px 8px; font-size:12px; font-weight:900; background:#e5edff; color:#1d4ed8; white-space:nowrap; }
        .state-pill.good { background:#d1fae5; color:#047857; }
        .state-pill.warn { background:#fef3c7; color:#b45309; }
        .state-pill.bad { background:#fee2e2; color:#b91c1c; }
        .section-split { display:grid; grid-template-columns:1fr; gap:14px; }
        @media (max-width:1100px) {
            .diagnostic-filter, .diagnostic-grid { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    @php
        $totalRuns = $summaryByFocus->sum('runs');
        $totalItems = $summaryByFocus->sum('items_found');
        $failedRuns = $summaryByFocus->filter(fn ($row) => $row->status !== 'completed')->sum('runs');
        $activeFilterLabel = $focusFilter === 'all' ? 'All crawlers' : str_replace('_', ' ', $focusFilter);
    @endphp

    <div class="diagnostics-page">
        <section class="panel stack">
            <div>
                <p class="eyebrow">Crawler health</p>
                <h2>Find missing coverage and weak crawler output</h2>
                <p class="muted">This page compares monitor-run telemetry with stored Review Desk items and configured country-specific sources. Use it to spot crawlers that are running but not producing useful stories or tenders.</p>
            </div>

            <form class="diagnostic-filter" method="get" action="{{ route('sls.intelligence.crawlers.diagnostics') }}">
                <label>Days
                    <input name="days" type="number" min="1" max="365" value="{{ $days }}">
                </label>
                <label>Focus
                    <select name="focus">
                        <option value="all" @selected($focusFilter === 'all')>All crawlers</option>
                        @foreach ($focuses as $focus)
                            <option value="{{ $focus }}" @selected($focusFilter === $focus)>{{ str_replace('_', ' ', $focus) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Country
                    <input name="country" value="{{ $countryQuery }}" placeholder="Country name or ISO">
                </label>
                <label>Min runs
                    <input name="min_runs" type="number" min="1" max="10000" value="{{ $minimumRuns }}">
                </label>
                <button class="button" type="submit">Apply</button>
            </form>

            <div class="diagnostic-grid">
                <div class="panel stat"><strong>{{ number_format($totalRuns) }}</strong><span class="muted">runs in {{ $days }} day(s)</span></div>
                <div class="panel stat"><strong>{{ number_format($totalItems) }}</strong><span class="muted">items found by runs</span></div>
                <div class="panel stat"><strong>{{ number_format($zeroResultCountries->count()) }}</strong><span class="muted">high-run zero-result rows</span></div>
                <div class="panel stat"><strong>{{ number_format($failedRuns) }}</strong><span class="muted">non-completed runs</span></div>
            </div>
            <p class="muted">Current scope: {{ $activeFilterLabel }}{{ $countryQuery !== '' ? ' / ' . $countryQuery : '' }}.</p>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Priority fixes</p>
                <h2>High-run countries with zero captured items</h2>
                <p class="muted">These are the places where the crawler has been busy but produced nothing. Start here when adding official sources, better keywords, or language-specific search terms.</p>
            </div>
            <div class="table-wrap">
                <table class="diagnostic-table">
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>Focus</th>
                            <th>Runs</th>
                            <th>Stored</th>
                            <th>Configured sources</th>
                            <th>Latest sources checked</th>
                            <th>Warning</th>
                            <th>Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($zeroResultCountries as $row)
                            <tr>
                                <td><strong>{{ $row['country']->iso_code }}</strong><br>{{ $row['country']->name }}</td>
                                <td>{{ str_replace('_', ' ', $row['focus']) }}</td>
                                <td>{{ number_format($row['runs']) }}</td>
                                <td>
                                    {{ number_format($row['stored_total']) }} total<br>
                                    <span class="muted">{{ number_format($row['active_total']) }} active / {{ number_format($row['rejected_total']) }} rejected</span>
                                </td>
                                <td>
                                    <span class="state-pill {{ $row['configured_sources'] > 0 ? 'good' : 'bad' }}">{{ number_format($row['configured_sources']) }}</span>
                                </td>
                                <td class="source-list">
                                    {{ $row['latest_sources_checked']->take(10)->implode(', ') ?: 'No sources recorded' }}
                                    @if ($row['latest_sources_checked']->count() > 10)
                                        <br><span class="muted">+{{ $row['latest_sources_checked']->count() - 10 }} more</span>
                                    @endif
                                </td>
                                <td><span class="state-pill bad">{{ $row['warning'] ?: 'Zero output' }}</span></td>
                                <td>
                                    <a href="{{ route('sls.intelligence.review', ['focus' => $row['focus'], 'country_q' => $row['country']->name, 'retrieved' => 'all', 'region' => 'all']) }}">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="muted">No high-run zero-result countries match this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Crawler totals</p>
                <h2>Runs and captures by focus</h2>
                <p class="muted">This separates crawler volume from crawler yield. A high run count with very low item count usually means source coverage, query wording, language coverage, or relevance filtering needs work.</p>
            </div>
            <div class="table-wrap">
                <table class="diagnostic-table">
                    <thead>
                        <tr>
                            <th>Focus</th>
                            <th>Status</th>
                            <th>Runs</th>
                            <th>Items found</th>
                            <th>Last finished</th>
                            <th>Yield</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summaryByFocus as $row)
                            @php
                                $yield = (int) $row->runs > 0 ? ((int) $row->items_found / (int) $row->runs) : 0;
                            @endphp
                            <tr>
                                <td>{{ str_replace('_', ' ', (string) $row->focus) }}</td>
                                <td><span class="state-pill {{ $row->status === 'completed' ? 'good' : 'warn' }}">{{ $row->status }}</span></td>
                                <td>{{ number_format((int) $row->runs) }}</td>
                                <td>{{ number_format((int) $row->items_found) }}</td>
                                <td>{{ $row->last_finished ? \Carbon\Carbon::parse($row->last_finished)->timezone('America/Chicago')->format('Y-m-d H:i') : '-' }}</td>
                                <td>{{ number_format($yield, 2) }} item/run</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">No runs match this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="section-split">
            <section class="panel stack">
                <div>
                    <p class="eyebrow">Source gaps</p>
                    <h2>Countries running without country-specific sources</h2>
                    <p class="muted">These runs rely mainly on global donor/news/search sources. They need direct official, procurement, press, and sector sources before their zero output can be trusted.</p>
                </div>
                <div class="table-wrap">
                    <table class="diagnostic-table">
                        <thead>
                            <tr>
                                <th>Country</th>
                                <th>Focus</th>
                                <th>Runs</th>
                                <th>Items</th>
                                <th>Latest checked</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($needsCoverage as $row)
                                <tr>
                                    <td><strong>{{ $row['country']->iso_code }}</strong><br>{{ $row['country']->name }}</td>
                                    <td>{{ str_replace('_', ' ', $row['focus']) }}</td>
                                    <td>{{ number_format($row['runs']) }}</td>
                                    <td>{{ number_format($row['items_found']) }}</td>
                                    <td class="source-list">{{ $row['latest_sources_checked']->take(12)->implode(', ') ?: 'No sources recorded' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="muted">No no-source countries match this filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">What is working</p>
                    <h2>Most productive country/crawler combinations</h2>
                    <p class="muted">Use the productive rows as patterns for source quality, language coverage, and keyword shape.</p>
                </div>
                <div class="table-wrap">
                    <table class="diagnostic-table">
                        <thead>
                            <tr>
                                <th>Country</th>
                                <th>Focus</th>
                                <th>Runs</th>
                                <th>Items found</th>
                                <th>Stored / active / rejected</th>
                                <th>Latest sources checked</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($productiveCountries as $row)
                                <tr>
                                    <td><strong>{{ $row['country']->iso_code }}</strong><br>{{ $row['country']->name }}</td>
                                    <td>{{ str_replace('_', ' ', $row['focus']) }}</td>
                                    <td>{{ number_format($row['runs']) }}</td>
                                    <td><span class="state-pill good">{{ number_format($row['items_found']) }}</span></td>
                                    <td>{{ number_format($row['stored_total']) }} / {{ number_format($row['active_total']) }} / {{ number_format($row['rejected_total']) }}</td>
                                    <td class="source-list">{{ $row['latest_sources_checked']->take(12)->implode(', ') ?: 'No sources recorded' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="muted">No productive country/crawler rows match this filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Recent run log</p>
                <h2>Latest monitor activity</h2>
            </div>
            <div class="table-wrap">
                <table class="diagnostic-table">
                    <thead>
                        <tr>
                            <th>Started</th>
                            <th>Country</th>
                            <th>Focus</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Sources checked</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentRuns as $run)
                            <tr>
                                <td>{{ $run->started_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') }}</td>
                                <td>{{ $run->country?->name ?? 'Global' }}{{ $run->country?->iso_code ? ' (' . $run->country->iso_code . ')' : '' }}</td>
                                <td>{{ str_replace('_', ' ', (string) $run->focus) }}</td>
                                <td><span class="state-pill {{ $run->status === 'completed' ? 'good' : 'warn' }}">{{ $run->status }}</span></td>
                                <td>{{ number_format((int) $run->items_found) }}</td>
                                <td class="source-list">{{ collect($run->sources_checked ?? [])->take(12)->implode(', ') ?: 'No sources recorded' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">No recent monitor runs match this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
