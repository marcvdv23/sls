@extends('sls.layouts.app')

@section('title', 'Intelligence Intake Log')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'Intake Log')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.review') }}">Review desk</a>
    <a class="button secondary" href="{{ route('sls.intelligence.newsArchive') }}">News archive</a>
    <a class="button secondary" href="{{ route('sls.intelligence.dropped') }}">Dropped items</a>
@endsection

@push('head')
    <style>
        .intake-kpis { display:flex; flex-wrap:wrap; gap:0; border:1px solid var(--border-subtle); border-radius:8px; overflow:hidden; }
        .intake-kpis span { display:inline-flex; gap:7px; align-items:baseline; padding:8px 12px; border-right:1px solid var(--border-subtle); }
        .intake-kpis span:last-child { border-right:0; }
        .intake-kpis strong { font-family:"JetBrains Mono", ui-monospace, monospace; }
        .intake-kpis em { color:var(--text-secondary); font-size:12px; font-style:normal; }
        .intake-filters { display:grid; grid-template-columns:minmax(220px, 1.2fr) repeat(5, minmax(130px, .65fr)) auto; gap:8px; align-items:end; }
        .intake-filters label { color:var(--text-secondary); font-size:12px; font-weight:800; }
        .intake-filters input, .intake-filters select { margin-top:5px; width:100%; }
        .intake-table { min-width:1520px; table-layout:fixed; }
        .intake-table th, .intake-table td { vertical-align:top; }
        .title-col { width:34rem; }
        .crawler-col { width:15rem; }
        .source-col { width:13rem; }
        .country-col { width:10rem; }
        .status-col { width:8rem; }
        .date-col { width:9rem; }
        .summary-col { width:25rem; }
        .title-link { color:var(--text-primary); font-weight:900; text-decoration:none; }
        .title-link:hover { color:var(--accent-primary); text-decoration:underline; }
        .summary-text { color:var(--text-secondary); font-size:13px; line-height:1.35; }
        .crawler-counts { display:flex; flex-wrap:wrap; gap:8px; }
    </style>
@endpush

@section('content')
    <div class="stack">
        <section class="intake-kpis" aria-label="Intelligence intake totals">
            <span><strong>{{ $summary['rows'] }}</strong><em>items</em></span>
            <span><strong>{{ $summary['news'] }}</strong><em>news</em></span>
            <span><strong>{{ $summary['tenders'] }}</strong><em>tenders</em></span>
            <span><strong>{{ $summary['dropped'] }}</strong><em>dropped</em></span>
            <span><strong>{{ $summary['unreviewed'] }}</strong><em>unreviewed</em></span>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Latest captured items</p>
                <h2>Stories and tenders pulled in the last {{ $filters['days'] }} day(s)</h2>
                <p class="muted">This is the audit trail for what SLS brought in. “Monitor / crawler” is inferred from the capture source and focus for older records; going forward we can add an exact stored crawler key for stricter auditing.</p>
            </div>
            <form class="intake-filters" method="get" action="{{ route('sls.intelligence.intakeLog') }}">
                <label>Search
                    <input name="q" value="{{ $filters['query'] }}" placeholder="Title, source, summary">
                </label>
                <label>Country
                    <input name="country" value="{{ $filters['country'] }}" placeholder="ISO / country">
                </label>
                <label>Type
                    <select name="type">
                        <option value="all" @selected($filters['type'] === 'all')>All</option>
                        <option value="news" @selected($filters['type'] === 'news')>News</option>
                        <option value="tender" @selected($filters['type'] === 'tender')>Tender</option>
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active only</option>
                        <option value="rejected" @selected($filters['status'] === 'rejected')>Dropped only</option>
                        <option value="unreviewed" @selected($filters['status'] === 'unreviewed')>Unreviewed</option>
                    </select>
                </label>
                <label>Days
                    <select name="days">
                        @foreach ([1, 3, 7, 14, 30, 90] as $option)
                            <option value="{{ $option }}" @selected((int) $filters['days'] === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Limit
                    <select name="limit">
                        @foreach ([100, 250, 500, 1000] as $option)
                            <option value="{{ $option }}" @selected((int) $filters['limit'] === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button" type="submit">Filter</button>
            </form>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Monitor / crawler totals</p>
                <div class="crawler-counts">
                    @forelse ($crawlerCounts as $label => $count)
                        <span class="pill">{{ $label }}: {{ $count }}</span>
                    @empty
                        <span class="muted">No intake in this period.</span>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="panel stack">
            <div class="table-wrap">
                <table class="data-table intake-table">
                    <thead>
                        <tr>
                            <th class="date-col">Retrieved</th>
                            <th class="country-col">Country</th>
                            <th class="crawler-col">Monitor / crawler</th>
                            <th class="title-col">Title</th>
                            <th class="source-col">Source</th>
                            <th class="date-col">Published</th>
                            <th class="status-col">Status</th>
                            <th class="summary-col">Summary / reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($updates as $update)
                            @php
                                $title = $update->display_title ?: 'Untitled item';
                                $sourceHost = $update->source_url ? parse_url($update->source_url, PHP_URL_HOST) : null;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $update->retrieved_at?->copy()->timezone($austinTz)->format('Y-m-d') ?: 'Unknown' }}</strong>
                                    <p class="dim">{{ $update->retrieved_at?->copy()->timezone($austinTz)->format('H:i') }} Austin</p>
                                </td>
                                <td>
                                    <strong>{{ $update->country?->name ?: 'Unknown' }}</strong>
                                    @if ($update->country?->iso_code)<p class="dim">{{ $update->country->iso_code }}</p>@endif
                                </td>
                                <td>
                                    <strong>{{ $update->crawler_label }}</strong>
                                    <p class="dim">{{ $update->item_type === 'tender' ? 'Tender' : 'News' }} / {{ $update->focus_label }}</p>
                                </td>
                                <td style="overflow-wrap:anywhere;">
                                    @if ($update->source_url && $update->review_status !== 'rejected')
                                        <a class="title-link" href="{{ route('sls.intelligence.updates.sourcePage', $update) }}">{{ $title }}</a>
                                    @else
                                        <strong>{{ $title }}</strong>
                                    @endif
                                </td>
                                <td style="overflow-wrap:anywhere;">
                                    <strong>{{ $update->source_name ?: 'Source' }}</strong>
                                    @if ($sourceHost)<p class="dim">{{ $sourceHost }}</p>@endif
                                </td>
                                <td>{{ $update->publication_date?->format('Y-m-d') ?: 'Not captured' }}</td>
                                <td>
                                    <span class="pill {{ $update->review_status === 'rejected' ? 'bad' : ($update->review_status === 'unreviewed' ? 'warn' : 'good') }}">{{ $update->review_status }}</span>
                                    @if ($update->review_status === 'rejected')
                                        <p class="dim">{{ $update->rejected_at?->copy()->timezone($austinTz)->format('Y-m-d H:i') }}</p>
                                    @endif
                                </td>
                                <td class="summary-text">
                                    @if ($update->review_status === 'rejected')
                                        <strong>{{ $reasonOptions[$update->rejection_reason_code] ?? 'Dropped' }}</strong><br>
                                        {{ $update->rejection_reason ?: 'No drop note captured.' }}
                                    @else
                                        {{ $update->story_summary ?: 'No summary captured yet.' }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="muted">No intake records match this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection