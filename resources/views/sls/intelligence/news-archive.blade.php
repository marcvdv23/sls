@extends('sls.layouts.app')

@section('title', 'News Archive')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'News Archive')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.review') }}">Review desk</a>
    <a class="button secondary" href="{{ route('sls.intelligence.intakeLog') }}">Intake log</a>
    <a class="button secondary" href="{{ route('sls.intelligence.dropped') }}">Dropped items</a>
    <a class="button secondary" href="{{ route('sls.intelligence.world') }}">World map</a>
@endsection

@push('head')
    <style>
        .archive-filters {
            align-items: end;
            display: grid;
            gap: 10px;
            grid-template-columns: minmax(180px, 1fr) 130px 130px 110px auto;
        }

        .archive-filters label {
            color: #536173;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .02em;
        }

        .archive-filters input,
        .archive-filters select {
            margin-top: 5px;
            width: 100%;
        }

        .archive-title {
            color: #071225;
            font-weight: 800;
            text-decoration: none;
        }

        .archive-title:hover,
        .archive-action:hover {
            color: #1d5fff;
            text-decoration: underline;
        }

        .archive-summary {
            color: #536173;
            font-size: 13px;
            line-height: 1.35;
            margin-top: 6px;
        }

        .archive-actions {
            display: grid;
            gap: 6px;
            justify-items: start;
        }

        .archive-action {
            background: transparent;
            border: 0;
            color: #1d5fff;
            cursor: pointer;
            font: inherit;
            font-weight: 800;
            padding: 0;
            text-decoration: none;
        }

        .org-chip {
            border: 1px solid #dbe3ee;
            border-radius: 999px;
            display: inline-flex;
            font-size: 12px;
            font-weight: 800;
            margin: 2px 4px 2px 0;
            max-width: 100%;
            padding: 4px 8px;
            text-decoration: none;
        }

        @media (max-width: 980px) {
            .archive-filters {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="stack">
        <section class="panel stack">
            <div class="toolbar">
                <div>
                    <p class="eyebrow">Recently Pulled Stories</p>
                    <h2>Unread archive queue</h2>
                    <p class="muted">Sorted by retrieval time, so older publication dates still appear when SLS has just found them. Marking a story read only clears it from this queue; it stays filed under its country and any tagged organizations.</p>
                </div>
                <p class="muted"><strong>{{ $updates->count() }}</strong> story item(s)</p>
            </div>

            <form class="archive-filters" method="get" action="{{ route('sls.intelligence.newsArchive') }}">
                <label>
                    Country / ISO
                    <input name="country" value="{{ $countryFilter }}" placeholder="Any country">
                </label>
                <label>
                    Status
                    <select name="status">
                        <option value="unread" @selected($status === 'unread')>Unread</option>
                        <option value="all" @selected($status === 'all')>All</option>
                        <option value="read" @selected($status === 'read')>Read</option>
                    </select>
                </label>
                <label>
                    Retrieved
                    <select name="days">
                        @foreach ([7, 14, 30, 90, 180, 365] as $option)
                            <option value="{{ $option }}" @selected($days === $option)>Last {{ $option }} days</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Limit
                    <select name="limit">
                        @foreach ([100, 250, 500, 1000] as $option)
                            <option value="{{ $option }}" @selected($limit === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button" type="submit">Filter</button>
            </form>
        </section>

        <section class="panel">
            <div class="table-wrap">
                <table class="data-table" style="min-width:1180px;table-layout:fixed;">
                    <thead>
                        <tr>
                            <th style="width:9rem;">Retrieved</th>
                            <th style="width:10rem;">Country</th>
                            <th style="width:34rem;">Story</th>
                            <th style="width:12rem;">Source</th>
                            <th style="width:9rem;">Published</th>
                            <th style="width:18rem;">Filed with</th>
                            <th style="width:8rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($updates as $update)
                            @php
                                $title = $update->title_english ?: $update->title ?: $update->title_original ?: 'Untitled story';
                                $summary = $update->summary_english ?: $update->summary;
                                $summary = $summary ? \Illuminate\Support\Str::limit($summary, 220) : null;
                                $focusLabel = $update->inferred_focus && isset($focuses[$update->inferred_focus]) ? $focuses[$update->inferred_focus]['label'] : 'News';
                                $sourceHost = $update->source_url ? parse_url($update->source_url, PHP_URL_HOST) : null;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $update->retrieved_at?->copy()->timezone($austinTz)->format('Y-m-d') }}</strong>
                                    <p class="dim">{{ $update->retrieved_at?->copy()->timezone($austinTz)->format('H:i') }} Austin</p>
                                    @if ($update->archive_read_at)
                                        <span class="pill good">Read</span>
                                    @else
                                        <span class="pill warn">Unread</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $update->country?->name ?: 'Unknown' }}</strong>
                                    @if ($update->country?->iso_code)
                                        <p class="dim">{{ $update->country->iso_code }}</p>
                                    @endif
                                </td>
                                <td style="overflow-wrap:anywhere;">
                                    <a class="archive-title" href="{{ route('sls.intelligence.updates.sourcePage', ['countryUpdate' => $update]) }}">{{ $title }}</a>
                                    <p class="dim">{{ $focusLabel }}</p>
                                    @if ($summary)
                                        <p class="archive-summary">{{ $summary }}</p>
                                    @endif
                                </td>
                                <td style="overflow-wrap:anywhere;">
                                    <strong>{{ $update->source_name ?: 'Source' }}</strong>
                                    @if ($sourceHost)
                                        <p class="dim">{{ $sourceHost }}</p>
                                    @endif
                                </td>
                                <td>{{ $update->publication_date?->format('Y-m-d') ?: 'Not captured' }}</td>
                                <td style="overflow-wrap:anywhere;">
                                    @forelse ($update->organizations as $organization)
                                        <a class="org-chip" href="{{ route('sls.organizations.show', $organization) }}">{{ $organization->name }}</a>
                                    @empty
                                        <span class="dim">No organization tagged yet</span>
                                    @endforelse
                                </td>
                                <td>
                                    <div class="archive-actions">
                                        <a class="archive-action" href="{{ route('sls.intelligence.updates.sourcePage', ['countryUpdate' => $update]) }}">Open</a>
                                        @if ($update->archive_read_at)
                                            <form method="post" action="{{ route('sls.intelligence.updates.archiveUnread', ['countryUpdate' => $update]) }}">
                                                @csrf
                                                <button class="archive-action" type="submit">Unread</button>
                                            </form>
                                        @else
                                            <form method="post" action="{{ route('sls.intelligence.updates.archiveRead', ['countryUpdate' => $update]) }}">
                                                @csrf
                                                <button class="archive-action" type="submit">Read</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">No stories match this archive filter yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
