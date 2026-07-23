@extends('sls.layouts.app')

@section('title', 'Caribbean Country Intelligence')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Caribbean Country Intelligence')

@push('head')
    <style>
        .legacy-page { display: grid; gap: 14px; }
        .legacy-page > header,
        .legacy-page > main { padding: 0; }
        .legacy-page > header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card);
            box-shadow: var(--card-shadow);
            padding: 14px;
        }
        .legacy-page > main { display: grid; gap: 14px; }
        .legacy-page .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
        .legacy-page .filters,
        .legacy-page .actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .legacy-page label { color: var(--text-secondary); font-size: 12px; font-weight: 800; }
        .legacy-page table { background: var(--bg-secondary); }
        .legacy-page .empty { border: 1px dashed var(--border-subtle); border-radius: var(--radius-card); padding: 14px; }
        @media (max-width: 980px) {
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page">
<header>
            <div>
                <p class="eyebrow">Country Intelligence</p>
                <h1>Caribbean Social Security and Tender Monitor</h1>
            </div>
            <a class="button" href="{{ url('/sls') }}">Back to dashboard</a>
        </header>

        <main>
            <div class="layout">
                <section class="panel map-wrap">
                    <div class="sea-line" aria-hidden="true"></div>

                    @foreach ($countries as $country)
                        <button class="country-pin {{ $country['status'] }}" style="left: {{ $country['x'] }}%; top: {{ $country['y'] }}%;" type="button">
                            {{ $country['iso'] }}
                            <span class="country-card">
                                <h3>{{ $country['name'] }}</h3>
                                @if ($country['latest_title'])
                                    <p><strong>{{ $country['latest_title'] }}</strong></p>
                                    <p>{{ Str::limit($country['latest_summary'], 260) }}</p>
                                    <p class="muted">{{ $country['latest_date'] ?: 'Date not captured' }}{{ $country['latest_source'] ? ' | ' . $country['latest_source'] : '' }}</p>
                                    @if ($country['latest_url'])
                                        <p><a href="{{ $country['latest_url'] }}" target="_blank" rel="noreferrer">Open source</a></p>
                                    @endif
                                @else
                                    <p>No social security, pension, provident fund, ministry of labor, or tender update has been captured yet.</p>
                                @endif
                            </span>
                        </button>
                    @endforeach
                </section>

                <aside class="side">
                    <section class="panel">
                        <p class="eyebrow">Monitor Scope</p>
                        <h2>Caribbean Markets</h2>
                        <div class="stat-grid">
                            <div class="stat">
                                <strong>{{ $monitoredCount }}</strong>
                                <span class="muted">in country table</span>
                            </div>
                            <div class="stat">
                                <strong>{{ $updateCount }}</strong>
                                <span class="muted">news and tender updates</span>
                            </div>
                        </div>
                        <div class="legend">
                            <span><i class="swatch" style="background: var(--update);"></i> Has relevant news/tender</span>
                            <span><i class="swatch" style="background: var(--monitored);"></i> Monitored/no update</span>
                            <span><i class="swatch" style="background: var(--none);"></i> Not initialized</span>
                        </div>
                    </section>

                    <section class="panel">
                        <p class="eyebrow">Tracked Topics</p>
                        <h2>News and Tender Filter</h2>
                        <ul class="topic-list">
                            <li>social security administrations and schemes</li>
                            <li>pension funds and pension reform</li>
                            <li>provident funds</li>
                            <li>ministry of labor or labour announcements</li>
                            <li>tenders, procurement notices, bids, RFPs, and expressions of interest</li>
                            <li>contributions, benefits, registration, compliance, and enforcement</li>
                        </ul>
                    </section>

                    <section class="panel">
                        <p class="eyebrow">Latest Captured Items</p>
                        <h2>Review Queue</h2>
                        <p class="muted">Every summarized item must keep a link to the original announcement, article, gazette, or tender notice.</p>
                        <div class="update-list">
                            @forelse ($countries->where('latest_title') as $country)
                                <article class="update-item">
                                    <h3>{{ $country['name'] }}</h3>
                                    <p>{{ $country['latest_title'] }}</p>
                                    <p class="muted">{{ $country['latest_date'] ?: 'Date not captured' }}</p>
                                </article>
                            @empty
                                <p class="muted">No Caribbean intelligence items have been collected yet. The scheduled monitor will populate this section once enabled.</p>
                            @endforelse
                        </div>
                    </section>
                </aside>
            </div>
        </main>
    </div>
@endsection