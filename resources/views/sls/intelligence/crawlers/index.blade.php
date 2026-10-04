@extends('sls.layouts.app')

@section('title', 'Intelligence Crawlers')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'Intelligence Crawlers')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.crawlerSettings') }}">General settings</a>
    <a class="button secondary" href="{{ route('sls.intelligence.sources') }}">Sources</a>
    <a class="button secondary" href="{{ route('sls.settings.reviewFocuses') }}">Review categories</a>
@endsection

@push('head')
    <style>
        .crawler-registry { display:grid; gap:14px; }
        .crawler-context { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px; }
        .crawler-context strong { display:block; font-size:22px; line-height:1.1; }
        .crawler-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
        .crawler-card { display:grid; gap:12px; }
        .crawler-card-header { display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
        .crawler-card h2 { margin:0; }
        .crawler-key { color:var(--text-muted); font-family:ui-monospace, SFMono-Regular, Consolas, monospace; font-size:12px; overflow-wrap:anywhere; }
        .crawler-metrics { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:8px; }
        .crawler-metrics div { border:1px solid var(--border-subtle); border-radius:8px; padding:8px; background:var(--bg-secondary); }
        .crawler-metrics strong { display:block; font-size:20px; line-height:1.1; }
        .crawler-note { border:1px solid var(--border-subtle); border-radius:8px; color:var(--text-secondary); font-size:13px; padding:10px; background:var(--bg-secondary); }
        .term-list { display:flex; flex-wrap:wrap; gap:6px; }
        .term-list span { background:#e5edff; border-radius:999px; color:#1d4ed8; font-size:12px; font-weight:800; padding:4px 8px; }
        .crawler-card-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .state-pill { border-radius:999px; display:inline-flex; padding:4px 8px; font-size:12px; font-weight:900; background:#e5edff; color:#1d4ed8; white-space:nowrap; }
        .state-pill.off { background:#fee2e2; color:#b91c1c; }
        @media (max-width:1100px) {
            .crawler-grid, .crawler-context { grid-template-columns:1fr; }
            .crawler-metrics { grid-template-columns:repeat(2, minmax(0, 1fr)); }
        }
    </style>
@endpush

@section('content')
    <div class="crawler-registry">
        <section class="panel stack">
            <div>
                <p class="eyebrow">Configured monitor crawlers</p>
                <h2>What SLS is crawling for in this workspace</h2>
                <p class="muted">Each card is one country-intelligence crawler/focus. It combines the Review Desk category, matching terms, configured sources, scheduled monitor runs, and captured results.</p>
            </div>
            <div class="crawler-note">
                <strong>How to read the numbers:</strong> run counts and new items are operational telemetry from crawler runs. Reviewable now is the Review Desk count after current intake, dropped/rejected, static-reference, relevance, and duplicate filters. Eligible sources include shared sources that can serve more than one crawler.
            </div>
            <div class="crawler-context">
                <div class="panel stat"><strong>{{ $focuses->count() }}</strong><span class="muted">configured crawlers</span></div>
                <div class="panel stat"><strong>{{ $scheduledCountries->count() ?: 'Region' }}</strong><span class="muted">{{ $scheduledCountries->count() ? 'scheduled country ISO codes' : 'using region scope' }}</span></div>
                <div class="panel stat"><strong>{{ $workspaceBatchSize ?: '-' }}</strong><span class="muted">countries per scheduled focus run</span></div>
            </div>
            @if ($workspaceSlots !== '')
                <p class="muted">Scheduled slots: <code>{{ $workspaceSlots }}</code></p>
            @endif
        </section>

        <section class="crawler-grid">
            @forelse ($focuses as $focus)
                @php
                    $runs = $runsByFocus->get($focus->focus_key, collect());
                    $latestRun = $runs->first();
                    $items = $itemsByFocus->get($focus->focus_key, collect());
                    $reviewableItems = $reviewableItemsByFocus->get($focus->focus_key, collect());
                    $terms = collect($focus->terms ?? [])->merge($focus->strong_signals ?? [])->filter()->take(8);
                @endphp
                <article class="panel crawler-card">
                    <div class="crawler-card-header">
                        <div>
                            <p class="eyebrow">Crawler</p>
                            <h2>{{ $focus->label }}</h2>
                            <div class="crawler-key">{{ $focus->focus_key }}</div>
                        </div>
                        <span class="state-pill {{ $focus->is_enabled ? '' : 'off' }}">{{ $focus->is_enabled ? 'Enabled' : 'Disabled' }}</span>
                    </div>

                    <p class="muted">{{ $focus->description ?: 'No crawler description configured yet.' }}</p>

                    <div class="crawler-metrics">
                        <div><strong>{{ number_format($runs->count()) }}</strong><span class="muted">runs in 7 days</span></div>
                        <div><strong>{{ number_format((int) $runs->sum('items_found')) }}</strong><span class="muted">new items in runs</span></div>
                        <div><strong>{{ number_format((int) ($sourceCountsByFocus[$focus->focus_key] ?? 0)) }}</strong><span class="muted">eligible sources</span></div>
                        <div><strong>{{ number_format($reviewableItems->count()) }}</strong><span class="muted">reviewable now</span></div>
                    </div>

                    <div>
                        <strong>Search terms</strong>
                        <div class="term-list" style="margin-top:6px;">
                            @forelse ($terms as $term)
                                <span>{{ $term }}</span>
                            @empty
                                <span>No terms configured</span>
                            @endforelse
                        </div>
                    </div>

                    <p class="muted">Last run: {{ $latestRun?->started_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'Never' }}{{ $latestRun?->country ? ' for ' . $latestRun->country->name : '' }}</p>

                    <div class="crawler-card-actions">
                        <a class="button" href="{{ route('sls.intelligence.crawlers.show', $focus->focus_key) }}">Open crawler</a>
                        <a class="button secondary" href="{{ route('sls.intelligence.review', ['focus' => $focus->focus_key, 'region' => 'all', 'retrieved' => 'current', 'status' => 'all']) }}">Review results</a>
                        @if ($items->count() !== $reviewableItems->count())
                            <span class="muted">{{ number_format($items->count()) }} captured in 30 days before Review Desk filters.</span>
                        @endif
                    </div>
                </article>
            @empty
                <section class="panel">
                    <p class="muted">No intelligence crawlers are configured yet. Add review categories to define crawler focus areas.</p>
                </section>
            @endforelse
        </section>
    </div>
@endsection
