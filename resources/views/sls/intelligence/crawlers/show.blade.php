@extends('sls.layouts.app')

@section('title', $focus->label . ' Crawler')
@section('eyebrow', 'Intelligence Crawler')
@section('page_title', $focus->label)

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.crawlers.index') }}">All intelligence crawlers</a>
    <a class="button secondary" href="{{ route('sls.intelligence.review', ['focus' => $focus->focus_key, 'region' => 'all']) }}">Review results</a>
    <a class="button secondary" href="{{ route('sls.intelligence.sources', ['focus' => $focus->focus_key]) }}">Sources</a>
@endsection

@push('head')
    <style>
        .crawler-detail { display:grid; gap:14px; }
        .crawler-detail-grid { display:grid; grid-template-columns:minmax(360px, .8fr) minmax(620px, 1.2fr); gap:14px; align-items:start; }
        .crawler-edit-form { display:grid; gap:10px; }
        .crawler-edit-form label { color:var(--text-secondary); display:grid; font-size:12px; font-weight:800; gap:5px; }
        .crawler-edit-form textarea { min-height:100px; }
        .inline-checks { display:flex; flex-wrap:wrap; gap:12px; align-items:center; }
        .inline-checks label { align-items:center; display:inline-flex; flex-direction:row; gap:7px; }
        .crawler-kpis { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:10px; }
        .crawler-kpis strong { display:block; font-size:22px; line-height:1.1; }
        .source-table { min-width:1120px; }
        .runs-table { min-width:920px; }
        .items-table { min-width:1250px; }
        .focus-key { color:var(--text-muted); font-family:ui-monospace, SFMono-Regular, Consolas, monospace; font-size:12px; font-weight:800; overflow-wrap:anywhere; }
        .state-pill { border-radius:999px; display:inline-flex; padding:4px 8px; font-size:12px; font-weight:900; background:#e5edff; color:#1d4ed8; white-space:nowrap; }
        .state-pill.off { background:#fee2e2; color:#b91c1c; }
        @media (max-width:1180px) {
            .crawler-detail-grid, .crawler-kpis { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="crawler-detail">
        <section class="panel stack">
            <div>
                <p class="eyebrow">Crawler definition</p>
                <h2>{{ $focus->label }}</h2>
                <p class="focus-key">{{ $focus->focus_key }}</p>
                <p class="muted">{{ $focus->description ?: 'This crawler does not have a description yet.' }}</p>
            </div>
            <div class="crawler-kpis">
                <div class="panel stat"><strong>{{ number_format($runs->count()) }}</strong><span class="muted">recent runs</span></div>
                <div class="panel stat"><strong>{{ number_format((int) $runs->sum('items_found')) }}</strong><span class="muted">items found by recent runs</span></div>
                <div class="panel stat"><strong>{{ number_format($sources->count()) }}</strong><span class="muted">matching sources</span></div>
                <div class="panel stat"><strong>{{ number_format($recentItems->count()) }}</strong><span class="muted">captured items in 60 days</span></div>
            </div>
        </section>

        <div class="crawler-detail-grid">
            <section class="panel stack">
                <div>
                    <p class="eyebrow">Edit crawler</p>
                    <h2>Terms and behavior</h2>
                    <p class="muted">These terms drive search queries and relevance matching for this workspace. The crawler key stays stable so existing records keep mapping correctly.</p>
                </div>
                <form class="crawler-edit-form" method="post" action="{{ route('sls.intelligence.crawlers.update', $focus) }}">
                    @csrf
                    <label>Label
                        <input name="label" value="{{ old('label', $focus->label) }}" required>
                    </label>
                    <label>Description
                        <textarea name="description">{{ old('description', $focus->description) }}</textarea>
                    </label>
                    <label>Search / match terms, one per line
                        <textarea name="terms">{{ old('terms', $lineValue($focus->terms ?? [])) }}</textarea>
                    </label>
                    <label>Strong signals, one per line
                        <textarea name="strong_signals">{{ old('strong_signals', $lineValue($focus->strong_signals ?? [])) }}</textarea>
                    </label>
                    <label>Sort order
                        <input name="sort_order" type="number" min="0" max="10000" value="{{ old('sort_order', $focus->sort_order ?? 100) }}">
                    </label>
                    <div class="inline-checks">
                        <label><input name="is_enabled" type="checkbox" value="1" @checked(old('is_enabled', $focus->is_enabled))> Enabled</label>
                        <label><input name="is_default" type="checkbox" value="1" @checked(old('is_default', $focus->is_default))> Default crawler</label>
                    </div>
                    <button type="submit">Save crawler</button>
                </form>
            </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Recent monitor runs</p>
                    <h2>What this crawler has done</h2>
                    <p class="muted">These are country monitor runs for this focus key. They are the closest operational trace for “which crawler found this.”</p>
                </div>
                <div class="table-wrap">
                    <table class="runs-table">
                        <thead>
                            <tr>
                                <th>Started</th>
                                <th>Country</th>
                                <th>Status</th>
                                <th>Items</th>
                                <th>Sources checked</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($runs as $run)
                                <tr>
                                    <td>{{ $run->started_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'Not started' }}</td>
                                    <td>{{ $run->country?->name ?? 'Global' }}{{ $run->country?->iso_code ? ' (' . $run->country->iso_code . ')' : '' }}</td>
                                    <td><span class="state-pill {{ $run->status === 'completed' ? '' : 'off' }}">{{ $run->status }}</span></td>
                                    <td>{{ number_format((int) $run->items_found) }}</td>
                                    <td>{{ collect($run->sources_checked ?? [])->take(4)->implode(', ') ?: 'None recorded' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="muted">No runs recorded for this crawler yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Configured sources</p>
                <h2>Where this crawler can look</h2>
                <p class="muted">Sources match this crawler when their focus is this key, both, news for non-tender crawlers, or tenders for tender crawlers.</p>
            </div>
            <div class="table-wrap">
                <table class="source-table">
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Focus</th>
                            <th>Domain</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sources as $source)
                            <tr>
                                <td>{{ $source->country_iso ?: 'Global' }}</td>
                                <td><a href="{{ route('sls.intelligence.sources', ['q' => $source->name]) }}">{{ $source->name }}</a></td>
                                <td>{{ str_replace('_', ' ', (string) $source->source_class) }}</td>
                                <td>{{ $source->focus ?: 'both' }}</td>
                                <td>{{ $source->domain ?: parse_url((string) $source->url, PHP_URL_HOST) }}</td>
                                <td><span class="state-pill {{ $source->is_enabled ? '' : 'off' }}">{{ $source->is_enabled ? 'Enabled' : 'Disabled' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">No sources currently match this crawler.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Recent captured results</p>
                <h2>What this crawler has produced</h2>
                <p class="muted">This list is inferred from captured item text and the current terms, so it helps spot noisy terms quickly.</p>
            </div>
            <div class="table-wrap">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Retrieved</th>
                            <th>Country</th>
                            <th>Title</th>
                            <th>Source</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentItems as $item)
                            <tr>
                                <td>{{ $item->retrieved_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') }}</td>
                                <td>{{ $item->country?->name ?? '-' }}{{ $item->country?->iso_code ? ' (' . $item->country->iso_code . ')' : '' }}</td>
                                <td style="overflow-wrap:anywhere;">
                                    @if ($item->source_url)
                                        <a href="{{ route('sls.intelligence.updates.sourcePage', $item) }}">{{ $item->title_english ?: $item->title }}</a>
                                    @else
                                        {{ $item->title_english ?: $item->title }}
                                    @endif
                                </td>
                                <td>{{ $item->source_name ?: parse_url((string) $item->source_url, PHP_URL_HOST) }}</td>
                                <td><span class="state-pill {{ $item->review_status === 'rejected' ? 'off' : '' }}">{{ $item->review_status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No recent captured items match this crawler.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
