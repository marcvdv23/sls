@extends('sls.layouts.app')

@section('title', 'Country Source Coverage')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'Source Coverage')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.review', ['region' => $region]) }}">Tenders and status</a>
    <a class="button secondary" href="{{ route('sls.intelligence.keywords') }}">Keyword directory</a>
    <a class="button secondary" href="{{ route('sls.intelligence.world', ['region' => $region === 'all' ? null : $region]) }}">Map</a>
@endsection

@push('head')
    <style>
        .sources-summary { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:10px; }
        .stat strong { display:block; font-family:"Sora", sans-serif; font-size:1.8rem; line-height:1; margin-bottom:4px; }
        .filters { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .form-grid { display:grid; grid-template-columns:1.2fr 1.2fr .75fr .9fr .9fr .9fr .9fr .8fr auto; gap:9px; align-items:end; margin-top:12px; }
        .filter-grid { display:grid; grid-template-columns:repeat(8, minmax(120px, 1fr)); gap:9px; align-items:end; margin-top:12px; }
        label.field { display:grid; gap:4px; color:var(--text-secondary); font-size:12px; font-weight:800; }
        .inline-form { display:contents; }
        .row-actions { display:grid; grid-template-columns:1.35fr 1fr 1fr; gap:4px; min-width:330px; align-items:stretch; }
        .row-actions .button,
        .row-actions button { width:100%; min-height:30px; padding:5px 8px; white-space:nowrap; }
        .row-actions .audit-action { grid-column:span 2; }
        .sources-table { min-width:1880px; }
        .source-list { display:grid; gap:5px; min-width:340px; }
        .source-row { display:grid; grid-template-columns:170px minmax(0, 1fr); gap:8px; }
        .source-row span:first-child { color:var(--text-secondary); font-size:12px; }
        @media (max-width:1180px) {
            .sources-summary, .form-grid, .filter-grid { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="stack">
        <section class="sources-summary">
            <div class="panel stat">
                <strong>{{ $rows->count() }}</strong>
                <span class="muted">countries in this view</span>
            </div>
            <div class="panel stat">
                <strong>{{ $completeCount }}</strong>
                <span class="muted">with all four source classes configured</span>
            </div>
            <div class="panel stat">
                <strong>{{ $rows->sum(fn ($row) => $row['sources']->count()) }}</strong>
                <span class="muted">configured local sources</span>
            </div>
            <div class="panel stat">
                <strong style="color: {{ $rows->where('has_media', false)->count() ? 'var(--accent-danger)' : 'var(--accent-success)' }}">{{ $rows->where('has_media', false)->count() }}</strong>
                <span class="muted">countries missing local newspaper / business media sources</span>
            </div>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Filters</p>
                <h2>Source registry by region</h2>
                <p class="muted">For social security and pension stories, the monitor prioritizes configured local media sources every run, then checks official social security, ministry, government, and tender sources.</p>
            </div>
            <div class="filters">
                <a class="button {{ $region === 'all' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.sources', ['region' => 'all']) }}">All regions</a>
                <a class="button {{ $region === 'africa' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.sources', ['region' => 'africa']) }}">Africa</a>
                <a class="button {{ $region === 'asia' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.sources', ['region' => 'asia']) }}">Asia</a>
                <a class="button {{ $region === 'latin_america' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.sources', ['region' => 'latin_america']) }}">Latin America</a>
                <a class="button {{ $region === 'caribbean' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.sources', ['region' => 'caribbean']) }}">Caribbean</a>
                <a class="button {{ $region === 'north_america' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.sources', ['region' => 'north_america']) }}">North America</a>
                <a class="button {{ $region === 'europe' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.sources', ['region' => 'europe']) }}">Europe</a>
            </div>
            <form class="filter-grid" method="get" action="{{ route('sls.intelligence.sources') }}">
                <input type="hidden" name="region" value="{{ $region }}">
                <label class="field">Status
                    <select name="status">
                        <option value="all" {{ $filters['status'] === 'all' ? 'selected' : '' }}>All</option>
                        <option value="enabled" {{ $filters['status'] === 'enabled' ? 'selected' : '' }}>Enabled</option>
                        <option value="disabled" {{ $filters['status'] === 'disabled' ? 'selected' : '' }}>Disabled</option>
                    </select>
                </label>
                <label class="field">Type
                    <select name="source_class">
                        <option value="all">All</option>
                        <?php foreach ($sourceClassOptions as $value => $label): ?>
                            <option value="{{ $value }}" {{ $filters['source_class'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">Focus
                    <select name="focus">
                        <option value="all">All</option>
                        <?php foreach (['both' => 'Both', 'news' => 'News', 'tenders' => 'Tenders', 'social_security' => 'Social security', 'hrms_tenders' => 'HRMS tenders', 'erms_tenders' => 'ERMS tenders', 'ebpc_tenders' => 'EBPC tenders', 'sector_tenders' => 'Sector tenders'] as $value => $label): ?>
                            <option value="{{ $value }}" {{ $filters['focus'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">Portal type
                    <select name="portal_type">
                        <option value="all">All</option>
                        <?php foreach ($procurementPortalOptions as $value => $label): ?>
                            <option value="{{ $value }}" {{ $filters['portal_type'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">Registration
                    <select name="registration">
                        <option value="all">All</option>
                        <?php foreach ($registrationOptions as $value => $label): ?>
                            <option value="{{ $value }}" {{ $filters['registration'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">Access setup
                    <select name="access">
                        <option value="all">All</option>
                        <?php foreach ($accessOptions as $label): ?>
                            <option value="{{ \Illuminate\Support\Str::slug($label) }}" {{ $filters['access'] === \Illuminate\Support\Str::slug($label) ? 'selected' : '' }}>{{ $label }}</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">Queue
                    <select name="queue">
                        <option value="all" {{ $filters['queue'] === 'all' ? 'selected' : '' }}>All</option>
                        <option value="forced" {{ $filters['queue'] === 'forced' ? 'selected' : '' }}>Forced next</option>
                        <option value="normal" {{ $filters['queue'] === 'normal' ? 'selected' : '' }}>Normal</option>
                    </select>
                </label>
                <div class="actions">
                    <button type="submit">Apply</button>
                    <a class="button secondary" href="{{ route('sls.intelligence.sources', ['region' => $region]) }}">Clear</a>
                </div>
            </form>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Manage Sources</p>
                <h2>Exact tender, official, and news sources checked by the agent</h2>
                <p class="muted">Disable a source here and the monitor will stop using it. Country sources run on the country cycle for their region and focus. Global donor and aggregator sources run in the global donor sweep. Use Check next to put a source at the front of the next applicable run.</p>
            </div>
            <form method="post" action="{{ route('sls.intelligence.sources.autoConfigure') }}">
                @csrf
                <button class="secondary" type="submit">Auto-configure missing access</button>
            </form>

            <form class="form-grid" method="post" action="{{ route('sls.intelligence.sources.store') }}">
                @csrf
                <label class="field">Name
                    <input name="name" required placeholder="e.g. Ghana Electronic Procurement System">
                </label>
                <label class="field">URL
                    <input name="url" type="url" placeholder="https://...">
                </label>
                <label class="field">Country
                    <select name="country_iso">
                        <option value="">Global donor / all countries</option>
                        <?php foreach ($countriesForSourceForm as $country): ?>
                            <option value="{{ $country['iso'] }}">{{ $country['name'] }} ({{ $country['iso'] }})</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">Region
                    <select name="region">
                        <option value="">Auto/global</option>
                        <option>Africa</option>
                        <option>Asia</option>
                        <option>Caribbean</option>
                        <option>Latin America</option>
                        <option>North America</option>
                        <option>Europe</option>
                    </select>
                </label>
                <label class="field">Type
                    <select name="source_class" required>
                        <?php foreach ($sourceClassOptions as $value => $label): ?>
                            <option value="{{ $value }}">{{ $label }}</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">Portal type
                    <select name="procurement_portal_type" required>
                        <?php foreach ($procurementPortalOptions as $value => $label): ?>
                            <option value="{{ $value }}" {{ $value === 'government' ? 'selected' : '' }}>{{ $label }}</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">Registration
                    <select name="registration_status" required>
                        <?php foreach ($registrationOptions as $value => $label): ?>
                            <option value="{{ $value }}" {{ $value === 'unknown' ? 'selected' : '' }}>{{ $label }}</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">Focus
                    <select name="focus" required>
                        <option value="both">Both</option>
                        <option value="news">News</option>
                        <option value="tenders">Tenders</option>
                        <option value="social_security">Social security</option>
                        <option value="hrms_tenders">HRMS tenders</option>
                        <option value="erms_tenders">ERMS tenders</option>
                        <option value="ebpc_tenders">EBPC tenders</option>
                        <option value="sector_tenders">Sector tenders</option>
                    </select>
                </label>
                <button type="submit">Add source</button>
                <label class="field" style="grid-column:1 / -1;">Registration notes
                    <input name="registration_notes" placeholder="e.g. supplier registration required before viewing documents; API key needed; free public browsing only">
                </label>
            </form>

            <div class="table-wrap">
                <table class="data-table sources-table">
                    <thead>
                        <tr>
                            <th>Source #</th>
                            <th>Status</th>
                            <th>Scope</th>
                            <th>Type</th>
                            <th>Portal type</th>
                            <th>Focus</th>
                            <th>Registration</th>
                            <th>Access setup</th>
                            <th>Connection status</th>
                            <th>Source</th>
                            <th>Tenders last 120 days</th>
                            <th>Total tenders captured</th>
                            <th>Last checked (Austin time)</th>
                            <th>Last success (Austin time)</th>
                            <th>Next scheduled check</th>
                            <th>Schedule logic</th>
                            <th>Queue</th>
                            <th>Last issue</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($managedSources as $source): ?>
                            <tr>
                                <td><strong>SRC-{{ str_pad($source->id, 4, '0', STR_PAD_LEFT) }}</strong></td>
                                <td><span class="pill {{ $source->is_enabled ? 'good' : 'bad' }}">{{ $source->is_enabled ? 'Enabled' : 'Disabled' }}</span></td>
                                <td>{{ $source->country_iso ?: 'Global' }}{{ $source->region ? ' / ' . $source->region : '' }}</td>
                                <td>{{ $sourceClassOptions[$source->source_class] ?? $source->source_class }}</td>
                                <td>{{ $procurementPortalOptions[$source->procurement_portal_type] ?? $source->procurement_portal_type ?? 'Not applicable' }}</td>
                                <td>{{ $source->focus }}</td>
                                <td>
                                    <span class="pill {{ in_array($source->registration_status, ['none', 'optional'], true) ? 'good' : (in_array($source->registration_status, ['unknown'], true) ? 'warn' : 'bad') }}">
                                        {{ $registrationOptions[$source->registration_status] ?? $source->registration_status ?? 'Unknown' }}
                                    </span>
                                    @if ($source->registration_notes)
                                        <p class="muted" style="margin-top:4px;">{{ $source->registration_notes }}</p>
                                    @endif
                                </td>
                                <td>{{ $source->access_setup_label ?? 'None configured' }}</td>
                                <td><span class="pill {{ $source->connection_status_class ?? 'warn' }}">{{ $source->connection_status_label ?? 'Unknown' }}</span></td>
                                <td>
                                    <strong>{{ $source->name }}</strong><br>
                                    <?php if ($source->url): ?>
                                        <a href="{{ $source->url }}" rel="noreferrer">{{ $source->domain ?: $source->url }}</a>
                                    <?php else: ?>
                                        <span class="muted">{{ $source->domain ?: 'No URL captured' }}</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong>{{ $source->captured_tenders_last_120_count ?? 0 }}</strong></td>
                                <td><strong>{{ $source->captured_tenders_total_count ?? 0 }}</strong></td>
                                <td>{{ $source->computed_last_checked_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'Not checked yet' }}</td>
                                <td>{{ $source->last_success_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'No successful hit yet' }}</td>
                                <td>{{ $source->computed_next_checked_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'Country cycle / on demand' }}</td>
                                <td>{{ $source->schedule_explanation ?? 'Configured schedule' }}</td>
                                <td>
                                    @if ($source->force_next_at)
                                        <span class="pill warn">Forced next</span>
                                    @else
                                        <span class="pill">Normal</span>
                                    @endif
                                </td>
                                <td>{{ $source->last_error ?: 'None' }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a class="button secondary tiny audit-action" href="{{ route('sls.intelligence.sources.audit', $source) }}" target="_blank" rel="noreferrer">Audit SRC-{{ str_pad($source->id, 4, '0', STR_PAD_LEFT) }} ({{ $source->audit_count ?? 0 }})</a>
                                        <a class="button secondary tiny" href="{{ route('sls.intelligence.sources.setupHelp', $source) }}" target="_blank" rel="noreferrer">Setup issue</a>
                                        <form class="inline-form" method="post" action="{{ route('sls.intelligence.sources.runNext', $source) }}">
                                            @csrf
                                            <button class="secondary tiny" type="submit">Check next</button>
                                        </form>
                                        <form class="inline-form" method="post" action="{{ route('sls.intelligence.sources.checkNow', $source) }}">
                                            @csrf
                                            <button class="secondary tiny" type="submit">Check now</button>
                                        </form>
                                        <form class="inline-form" method="post" action="{{ route('sls.intelligence.sources.toggle', $source) }}">
                                            @csrf
                                            <button class="secondary tiny" type="submit">{{ $source->is_enabled ? 'Disable' : 'Enable' }}</button>
                                        </form>
                                        <form class="inline-form" method="post" action="{{ route('sls.intelligence.sources.delete', $source) }}">
                                            @csrf
                                            <button class="secondary tiny" type="submit">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
