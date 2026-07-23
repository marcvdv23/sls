@extends('sls.layouts.app')

@section('title', 'Country Intelligence Search')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'Country Search')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.review') }}">Review desk</a>
    <a class="button secondary" href="{{ route('sls.intelligence.favorites') }}">Favorites</a>
    <a class="button secondary" href="{{ route('sls.intelligence.sources') }}">Sources</a>
@endsection

@push('head')
    <style>
        .search-grid { display:grid; grid-template-columns:minmax(260px, 1fr) 12rem 12rem auto; gap:10px; align-items:end; }
        .summary-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:10px; }
        .country-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px; }
        .country-card { border:1px solid var(--border-subtle); border-radius:var(--radius-card); padding:12px; background:var(--bg-primary); }
        .stat strong { display:block; font-family:"Sora", sans-serif; font-size:1.8rem; line-height:1; margin-bottom:4px; }
        .search-table { min-width:1480px; table-layout:fixed; }
        .run-table { min-width:900px; }
        .title-col { width:32rem; }
        .country-col { width:10rem; }
        .type-col { width:8rem; }
        .date-col { width:8.5rem; }
        .source-col { width:13rem; }
        .status-col { width:8rem; }
        .link-col { width:8rem; }
        .favorite-note-col { width:22rem; }
        .favorite-reminder-col { width:17rem; }
        .favorite-action-col { width:10rem; }
        .title-cell { overflow-wrap:anywhere; }
        .favorite-form { display:grid; gap:7px; }
        textarea.favorite-note { min-height:58px; resize:vertical; }
        .favorite-actions { display:grid; gap:6px; }
        label.field { display:grid; gap:4px; color:var(--text-secondary); font-weight:700; }
        @media (max-width:980px) {
            .search-grid, .summary-grid { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="stack">
        <section class="panel">
            <form class="search-grid" method="get" action="{{ route('sls.intelligence.search') }}">
                <label class="field">
                    Country name or ISO code
                    <input name="q" value="{{ $query }}" placeholder="Example: Trinidad, Ghana, Brazil, TT">
                </label>
                <label class="field">
                    Type
                    <select name="type">
                        <option value="all" @selected($type === 'all')>All items</option>
                        <option value="tenders" @selected($type === 'tenders')>Tenders only</option>
                        <option value="news" @selected($type === 'news')>News only</option>
                    </select>
                </label>
                <label class="field">
                    Status
                    <select name="status">
                        <option value="all" @selected($status === 'all')>All statuses</option>
                        <option value="unreviewed" @selected($status === 'unreviewed')>Unreviewed</option>
                        <option value="approved" @selected($status === 'approved')>Approved</option>
                        <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                    </select>
                </label>
                <button type="submit">Search</button>
            </form>
        </section>

        @if ($query !== '')
            <section class="summary-grid">
                <div class="panel stat"><strong>{{ $summary['total'] }}</strong><span class="muted">stored items shown</span></div>
                <div class="panel stat"><strong>{{ $summary['tenders'] }}</strong><span class="muted">tenders / procurement</span></div>
                <div class="panel stat"><strong>{{ $summary['news'] }}</strong><span class="muted">news / intelligence</span></div>
                <div class="panel stat"><strong>{{ $summary['rejected'] }}</strong><span class="muted">rejected but still stored</span></div>
            </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Matched Countries</p>
                    <h2>Countries matching "{{ $query }}"</h2>
                </div>
                @if ($countries->isNotEmpty())
                    <div class="country-grid">
                        @foreach ($countries as $country)
                            <div class="country-card">
                                <h3>{{ $country->name }} <span class="muted">({{ $country->iso_code }})</span></h3>
                                <p class="muted">{{ $country->region }}{{ $country->social_security_administration_name ? ' | ' . $country->social_security_administration_name : '' }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="muted">No country matched "{{ $query }}". Try the ISO code or a shorter country name.</p>
                @endif
            </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Stored Intelligence</p>
                    <h2>News, tenders, and reports found for this country search</h2>
                </div>
                <div class="table-wrap">
                    <table class="data-table search-table">
                        <thead>
                            <tr>
                                <th class="country-col">Country</th>
                                <th class="type-col">Type</th>
                                <th class="title-col">Title</th>
                                <th class="source-col">Source</th>
                                <th class="date-col">Published</th>
                                <th class="date-col">Retrieved</th>
                                <th class="status-col">Status</th>
                                <th class="link-col">Original</th>
                                <th class="favorite-note-col">Favorite note</th>
                                <th class="favorite-reminder-col">Reminder date/time</th>
                                <th class="favorite-action-col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($updates as $update)
                                <tr>
                                    <td>{{ $update->country?->name }}</td>
                                    <td>
                                        <span class="pill {{ $update->item_type === 'tender' ? 'warn' : 'good' }}">{{ $update->item_type }}</span>
                                        <div class="muted">{{ $update->inferred_focus && isset($focuses[$update->inferred_focus]) ? $focuses[$update->inferred_focus]['label'] : 'Unclassified' }}</div>
                                    </td>
                                    <td class="title-cell">
                                        <strong>{{ $update->title_english ?: $update->title }}</strong>
                                        @if ($update->title_original && $update->title_original !== ($update->title_english ?: $update->title))
                                            <p class="muted">{{ $update->title_original }}</p>
                                        @endif
                                    </td>
                                    <td>{{ $update->source_name ?: 'Unknown source' }}</td>
                                    <td>{{ $update->publication_date?->toDateString() ?: 'Not captured' }}</td>
                                    <td>{{ $update->retrieved_at?->copy()->timezone($austinTz)->format('Y-m-d H:i') ?: 'Not captured' }}</td>
                                    <td><span class="pill {{ $update->review_status === 'rejected' ? 'bad' : 'warn' }}">{{ $update->review_status }}</span></td>
                                    <td>
                                        @if ($update->source_url)
                                            <a class="button secondary tiny" href="{{ route('sls.intelligence.updates.sourcePage', $update) }}">Open</a>
                                        @else
                                            <span class="muted">No link</span>
                                        @endif
                                    </td>
                                    @php($favoriteFormId = 'favorite-form-' . $update->id)
                                    <td>
                                        @if ($update->is_favorite)
                                            <span class="pill good">Favorite</span>
                                            @if ($update->favorite_note)
                                                <p class="muted">{{ $update->favorite_note }}</p>
                                            @endif
                                        @endif
                                        <form id="{{ $favoriteFormId }}" class="favorite-form" method="post" action="{{ route('sls.intelligence.updates.favorite', $update) }}">
                                            @csrf
                                            <input type="hidden" name="return_to" value="{{ url()->full() }}">
                                            <textarea class="favorite-note" name="favorite_note" placeholder="Reminder note, e.g. follow up with ministry contact">{{ old('favorite_note', $update->favorite_note) }}</textarea>
                                        </form>
                                    </td>
                                    <td>
                                        @if ($update->reminder_due_at)
                                            <p class="muted">Current: {{ $update->reminder_due_at->copy()->timezone($austinTz)->format('Y-m-d H:i') }}</p>
                                        @endif
                                        <input form="{{ $favoriteFormId }}" type="datetime-local" name="reminder_due_at" value="{{ old('reminder_due_at', $update->reminder_due_at?->copy()->timezone($austinTz)->format('Y-m-d\TH:i')) }}">
                                    </td>
                                    <td>
                                        <div class="favorite-actions">
                                            <button form="{{ $favoriteFormId }}" class="tiny" type="submit">{{ $update->is_favorite ? 'Update' : 'Like' }}</button>
                                            @if ($update->is_favorite)
                                                @if (! $update->reminder_completed_at)
                                                    <form method="post" action="{{ route('sls.intelligence.updates.favorite.complete', $update) }}">
                                                        @csrf
                                                        <button class="tiny" type="submit">Done</button>
                                                    </form>
                                                @endif
                                                <form method="post" action="{{ route('sls.intelligence.updates.favorite.remove', $update) }}">
                                                    @csrf
                                                    <button class="secondary tiny" type="submit">Remove</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="muted">No stored items match this country and filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Monitor Runs</p>
                    <h2>Recent checks for matched countries</h2>
                </div>
                <div class="table-wrap">
                    <table class="data-table run-table">
                        <thead>
                            <tr>
                                <th>Country</th>
                                <th>Focus</th>
                                <th>Status</th>
                                <th>Items found</th>
                                <th>Finished (Austin time)</th>
                                <th>Sources checked</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($runs as $run)
                                <tr>
                                    <td>{{ $run->country?->name }}</td>
                                    <td>{{ $focuses[$run->focus]['label'] ?? $run->focus }}</td>
                                    <td><span class="pill {{ $run->status === 'completed' ? 'good' : 'warn' }}">{{ $run->status }}</span></td>
                                    <td>{{ $run->items_found }}</td>
                                    <td>{{ $run->finished_at?->copy()->timezone($austinTz)->format('Y-m-d H:i') ?: 'Not finished' }}</td>
                                    <td class="muted">{{ collect($run->sources_checked)->take(8)->implode(', ') ?: 'Not recorded' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="muted">No monitor runs recorded yet for this country search.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
