@extends('sls.layouts.app')

@section('title', '1G-SLS Dashboard')
@section('refresh', '300')
@section('eyebrow', 'Standalone PHP/MySQL Project')
@section('page_title', '1G-SLS')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        .dashboard-grid { display:grid; gap:14px; }
        .monitor-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
        .tracker-grid { display:grid; grid-template-columns:repeat(5, minmax(0, 1fr)); gap:10px; }
        .tracker-card { min-height:108px; }
        .tracker-card strong { display:block; font-family:"Sora", sans-serif; font-size:2rem; line-height:1; margin:.25rem 0 .35rem; }
        .stat-link { color:var(--text-primary); text-decoration:none; }
        .stat-link:hover strong { color:var(--accent-primary); text-decoration:underline; }
        .monitor-card.good { border-color:color-mix(in srgb, var(--accent-success) 35%, var(--border-subtle)); }
        .monitor-card.bad { border-color:color-mix(in srgb, var(--accent-danger) 35%, var(--border-subtle)); }
        .monitor-card.good .health-status { color:var(--accent-success); }
        .monitor-card.bad .health-status { color:var(--accent-danger); }
        .command-panel { display:grid; grid-template-columns:minmax(260px, .8fr) minmax(0, 1.8fr); gap:12px; align-items:center; }
        .command-row { justify-content:flex-end; }
        .backup-status { display:grid; grid-template-columns:minmax(220px, .65fr) minmax(0, 1.35fr); gap:12px; align-items:center; }
        .backup-path { color:var(--text-secondary); font-family:"JetBrains Mono", monospace; font-size:.78rem; overflow-wrap:anywhere; }
        .map-panel { padding:0; overflow:hidden; }
        .map-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 14px; border-bottom:1px solid var(--border-subtle); }
        #dashboard-world-map { width:100%; min-height:430px; }
        .leaflet-container { font:inherit; background:#eef5f8; }
        .map-popup { display:grid; gap:5px; max-width:320px; }
        .map-popup h3 { font-size:.95rem; margin:0; }
        .map-popup p { font-size:.8rem; line-height:1.3; margin:0; }
        .map-popup-actions { display:grid; gap:6px; margin-top:5px; }
        .map-popup-actions select { width:100%; min-height:34px; border:1px solid var(--border-subtle); border-radius:7px; background:var(--bg-secondary); color:var(--text-primary); font:inherit; font-size:.82rem; padding:4px 8px; }
        .map-popup-actions button { width:100%; }
        .map-popup-status { color:var(--text-secondary); font-size:.76rem; min-height:1em; }
        .region-count { color:var(--accent-primary); font-family:"JetBrains Mono", monospace; font-weight:800; text-decoration:none; }
        .region-count:hover { text-decoration:underline; }
        .product-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
        .product-card { min-height:150px; }
        .intel-line { display:grid; gap:3px; padding-top:10px; border-top:1px solid var(--border-subtle); }
        .intel-line:first-of-type { border-top:0; padding-top:0; }
        .intel-line-title { color:var(--text-muted); font-size:.7rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
        .intel-link { color:var(--text-primary); font-weight:800; line-height:1.25; text-decoration:none; }
        .intel-link:hover { color:var(--accent-primary); text-decoration:underline; }
        .intel-meta { color:var(--text-secondary); font-size:.78rem; }
        .intel-capture-row { display:flex; flex-wrap:wrap; gap:8px; align-items:center; color:var(--text-secondary); font-size:.82rem; }
        .intel-capture-row a { color:var(--accent-primary); font-weight:800; text-decoration:none; }
        .intel-capture-row a:hover { text-decoration:underline; }
        .compact-table { min-width:1180px; table-layout:fixed; }
        .compact-table td, .compact-table th { padding:6px 8px; }
        .tracked-country-col { width:135px; }
        .tracked-iso-col { width:58px; }
        .tracked-region-col { width:125px; }
        .tracked-lang-col { width:58px; }
        .tracked-source-col { width:44%; }
        .tracked-profile-col { width:112px; }
        .tracked-topics-col { width:190px; }
        .tracked-country-col, .tracked-source-col, .tracked-topics-col { overflow-wrap:anywhere; }
        .missing-source { color:var(--accent-danger); font-weight:700; }
        .source-name-list { display:grid; gap:0; line-height:1.15; }
        .source-name-row { display:grid; grid-template-columns:minmax(0, 1fr) auto; align-items:start; gap:4px; }
        .source-name-actions { display:flex; align-items:center; gap:2px; }
        .source-name-list span, .source-name-list a { display:block; }
        .source-name-list a { color:var(--accent-primary); font-weight:700; text-decoration:none; }
        .source-name-list a:hover { text-decoration:underline; }
        .source-url-edit,
        .source-url-delete { width:22px; height:22px; min-height:22px; padding:0; border-radius:6px; font-size:.72rem; line-height:1; color:var(--text-secondary); background:transparent; border:1px solid transparent; }
        .source-url-edit:hover { color:var(--accent-primary); border-color:var(--border-subtle); background:var(--bg-secondary); }
        .source-url-delete { color:var(--accent-danger); font-size:.9rem; font-weight:900; }
        .source-url-delete:hover { color:#b91c1c; border-color:#fecaca; background:#fef2f2; }
        .source-url-modal[hidden] { display:none; }
        .source-url-modal { position:fixed; inset:0; z-index:1000; display:grid; place-items:center; padding:24px; background:rgba(15, 23, 42, .46); }
        .source-url-dialog { width:min(560px, 100%); border-radius:8px; border:1px solid var(--border-subtle); background:var(--bg-primary); box-shadow:0 24px 70px rgba(15, 23, 42, .28); }
        .source-url-dialog header { display:flex; justify-content:space-between; gap:12px; padding:14px 16px; border-bottom:1px solid var(--border-subtle); }
        .source-url-dialog h3 { margin:0; font-size:1rem; }
        .source-url-dialog form { display:grid; gap:12px; padding:16px; }
        .source-url-dialog label { display:grid; gap:5px; color:var(--text-secondary); font-size:.78rem; font-weight:800; text-transform:uppercase; }
        .source-url-dialog input { width:100%; box-sizing:border-box; }
        .source-url-dialog .form-status { min-height:1.2em; color:var(--accent-danger); font-size:.82rem; }
        .tracked-country-filters { display:flex; flex-wrap:wrap; gap:8px; align-items:end; }
        .tracked-country-filters label { display:grid; gap:4px; color:var(--text-secondary); font-size:.7rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
        .tracked-country-filters input,
        .tracked-country-filters select { min-height:34px; border:1px solid var(--border-subtle); border-radius:7px; background:var(--bg-secondary); color:var(--text-primary); font:inherit; font-size:.84rem; padding:4px 8px; }
        .tracked-country-filters input { width:190px; }
        .tracked-country-filters select { width:150px; }
        @media (max-width:1180px) { .tracker-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } .command-panel, .monitor-grid, .product-grid { grid-template-columns:1fr; } .command-row { justify-content:flex-start; } }
    </style>
@endpush

@section('topbar_actions')
    <a class="button" href="{{ route('sls.crm.search') }}">Global CRM search</a>
    <a class="button secondary" href="{{ route('sls.intelligence.review') }}">Review desk</a>
    <form method="post" action="{{ route('sls.system.scheduler.restart') }}" style="margin:0;">
        @csrf
        <button class="secondary" type="submit">Restart scheduler</button>
    </form>
    <form method="post" action="{{ route('sls.system.backup') }}" style="margin:0;">
        @csrf
        <button class="secondary" type="submit">Run backup</button>
    </form>
@endsection

@section('content')
    @php
        $austinTz = 'America/Chicago';
    @endphp

    <div class="dashboard-grid">
        <section class="monitor-grid">
            @foreach ($intelligenceMonitorHealths as $monitorHealth)
                <article class="panel monitor-card {{ $monitorHealth['healthy'] ? 'good' : 'bad' }}">
                    <p class="eyebrow">Intelligence Monitor</p>
                    <h2 class="health-status">{{ $monitorHealth['label'] }}: {{ $monitorHealth['healthy'] ? 'Live' : 'Needs attention' }}</h2>
                    <p class="muted" style="margin-top:6px;">
                        @if (is_null($monitorHealth['minutes_since_last_run']))
                            No completed run has been logged yet.
                        @else
                            Last completed {{ $monitorHealth['minutes_since_last_run'] }} minute(s) ago
                            @if ($monitorHealth['last_country'])
                                for {{ $monitorHealth['last_country'] }}
                            @endif
                            .
                        @endif
                    </p>
                    <div class="toolbar" style="margin-top:10px;">
                        <span class="pill {{ $monitorHealth['healthy'] ? 'good' : 'bad' }}">{{ $monitorHealth['healthy'] ? 'Healthy' : 'No run in 30+ minutes' }}</span>
                        @if ($monitorHealth['last_run_at'])
                            <span class="pill">Last run {{ $monitorHealth['last_run_at']->copy()->timezone($austinTz)->format('Y-m-d H:i') }} Austin</span>
                        @endif
                        @if (! is_null($monitorHealth['items_found']))
                            <span class="pill">{{ $monitorHealth['items_found'] }} item(s)</span>
                        @endif
                        <a class="button tiny secondary" href="{{ route('sls.intelligence.review', ['focus' => $monitorHealth['focus'], 'region' => $monitorHealth['region']]) }}">Review</a>
                        <form method="post" action="{{ route('sls.intelligence.monitors.runNow', ['monitor' => $monitorHealth['run_key']]) }}" style="margin:0;">
                            @csrf
                            <button class="tiny secondary" type="submit">Run now</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="tracker-grid" aria-label="Published intelligence counters">
            <article class="panel tracker-card">
                <p class="eyebrow">SSAS Tenders</p>
                <a class="stat-link" href="{{ $recentIntelligenceStats['links']['social_security_tenders'] }}">
                    <strong>{{ $recentIntelligenceStats['social_security_tenders'] }}</strong>
                    <p class="muted">Published in last {{ $recentIntelligenceStats['published_window_days'] }} days.</p>
                </a>
            </article>
            <article class="panel tracker-card">
                <p class="eyebrow">HRMS Tenders</p>
                <a class="stat-link" href="{{ $recentIntelligenceStats['links']['hrms_tenders'] }}">
                    <strong>{{ $recentIntelligenceStats['hrms_tenders'] }}</strong>
                    <p class="muted">HRMS, payroll, HCM, benefits, talent, workforce.</p>
                </a>
            </article>
            <article class="panel tracker-card">
                <p class="eyebrow">ERMS Tenders</p>
                <a class="stat-link" href="{{ $recentIntelligenceStats['links']['erms_tenders'] }}">
                    <strong>{{ $recentIntelligenceStats['erms_tenders'] }}</strong>
                    <p class="muted">Risk, GRC, compliance, audit, controls.</p>
                </a>
            </article>
            <article class="panel tracker-card">
                <p class="eyebrow">EBPC Tenders</p>
                <a class="stat-link" href="{{ $recentIntelligenceStats['links']['ebpc_tenders'] }}">
                    <strong>{{ $recentIntelligenceStats['ebpc_tenders'] }}</strong>
                    <p class="muted">Budget planning, control, forecasting.</p>
                </a>
            </article>
            <article class="panel tracker-card">
                <p class="eyebrow">Social Security News</p>
                <a class="stat-link" href="{{ $recentIntelligenceStats['links']['social_security_news'] }}">
                    <strong>{{ $recentIntelligenceStats['social_security_news'] }}</strong>
                    <p class="muted">Pensions, social security, social protection.</p>
                </a>
            </article>
        </section>

        <section class="panel command-panel">
            <div>
                <p class="eyebrow">Command Center</p>
                <h2>Search, monitor, and load sales intelligence</h2>
                <p class="muted">{{ $sourceDocumentCount }} source document(s), {{ $knowledgeChunkCount }} searchable chunk(s).</p>
            </div>
            <div class="command-row toolbar">
                <a class="button secondary tiny" href="{{ route('sls.chat') }}">Chatbox</a>
                <a class="button secondary tiny" href="{{ route('sls.intelligence.sources') }}">Sources</a>
                <a class="button secondary tiny" href="{{ route('sls.intelligence.favorites') }}">Favorites</a>
                <a class="button secondary tiny" href="{{ route('sls.organizations.index') }}">Organizations</a>
                <a class="button secondary tiny" href="{{ route('sls.knowledge.index') }}">Knowledge</a>
                <a class="button secondary tiny" href="{{ route('sls.demo-media.index') }}">Demo media</a>
                <a class="button secondary tiny" href="{{ route('sls.directoryImages.index') }}">Image import</a>
                <a class="button secondary tiny" href="{{ route('sls.security.index') }}">Security</a>
            </div>
        </section>

        <section class="panel backup-status">
            <div>
                <p class="eyebrow">Backup</p>
                <h2>Latest local backup</h2>
            </div>
            <div>
                @if ($latestBackup)
                    <p class="muted">
                        Completed {{ $latestBackup['updated_at']->copy()->timezone($austinTz)->format('Y-m-d H:i:s') }} Austin.
                    </p>
                    <p class="backup-path">{{ $latestBackup['path'] }}</p>
                @else
                    <p class="muted">No local backup folder found yet.</p>
                    <p class="backup-path">{{ $backupRoot }}</p>
                @endif
            </div>
        </section>

        <section class="panel map-panel">
            <div class="map-head">
                <div>
                    <p class="eyebrow">World Intelligence Map</p>
                    <h2>SSAS news and tender activity by country</h2>
                </div>
                <div class="toolbar">
                    <span class="pill">{{ $dashboardMapMonitoredCount }} monitored</span>
                    <span class="pill">{{ $dashboardMapUpdateCount }} updates</span>
                    <a class="button secondary tiny" href="{{ route('sls.intelligence.world') }}">Open full map</a>
                </div>
            </div>
            <div id="dashboard-world-map"></div>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Regional Intelligence Totals</p>
                <h2>Published items in the last {{ $recentIntelligenceStats['published_window_days'] }} days</h2>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Region</th>
                            <th>SSAS Tenders</th>
                            <th>HRMS Tenders</th>
                            <th>ERMS Tenders</th>
                            <th>EBPC Tenders</th>
                            <th>Social Security News</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentIntelligenceStats['regions'] as $regionTotal)
                            <tr>
                                <td><strong>{{ $regionTotal['name'] }}</strong></td>
                                <td><a class="region-count" href="{{ $regionTotal['links']['social_security_tenders'] }}">{{ $regionTotal['social_security_tenders'] }}</a></td>
                                <td><a class="region-count" href="{{ $regionTotal['links']['hrms_tenders'] }}">{{ $regionTotal['hrms_tenders'] }}</a></td>
                                <td><a class="region-count" href="{{ $regionTotal['links']['erms_tenders'] }}">{{ $regionTotal['erms_tenders'] }}</a></td>
                                <td><a class="region-count" href="{{ $regionTotal['links']['ebpc_tenders'] }}">{{ $regionTotal['ebpc_tenders'] }}</a></td>
                                <td><a class="region-count" href="{{ $regionTotal['links']['social_security_news'] }}">{{ $regionTotal['social_security_news'] }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Product Intelligence</p>
                <h2>Latest opportunities and activity</h2>
            </div>
            <div class="product-grid">
                @foreach ($productIntelligenceCards as $card)
                    <article class="card product-card dense-stack">
                        <h3>{{ $card['name'] }}</h3>
                        <div class="intel-line">
                            <span class="intel-line-title">Latest tender opportunity</span>
                            @if ($card['latest_tender'])
                                <a class="intel-link" href="{{ route('sls.intelligence.updates.sourcePage', $card['latest_tender']) }}">
                                    {{ Str::limit($card['latest_tender']->title_english ?: $card['latest_tender']->title ?: $card['latest_tender']->title_original, 130) }}
                                </a>
                                <span class="intel-meta">
                                    {{ $card['latest_tender']->country?->name ?? 'Global / unassigned' }}
                                    @if ($card['latest_tender']->publication_date)
                                        | published {{ $card['latest_tender']->publication_date->format('Y-m-d') }}
                                    @endif
                                </span>
                            @else
                                <span class="muted">No matching tender found yet.</span>
                            @endif
                        </div>
                        <div class="intel-line">
                            <span class="intel-line-title">Captured last {{ $card['captured']['window_days'] }} days</span>
                            <div class="intel-capture-row">
                                <a href="{{ $card['captured']['tender_url'] }}">{{ $card['captured']['tender_count'] }} tender{{ $card['captured']['tender_count'] === 1 ? '' : 's' }}</a>
                                @if (! is_null($card['captured']['news_count']) && $card['captured']['news_url'])
                                    <span>/</span>
                                    <a href="{{ $card['captured']['news_url'] }}">{{ $card['captured']['news_count'] }} news</a>
                                @endif
                            </div>
                            @if ($card['captured']['latest_tender'])
                                <span class="intel-meta">
                                    Latest captured tender: {{ $card['captured']['latest_tender']->country?->name ?? 'Global / unassigned' }}
                                    @if ($card['captured']['latest_tender']->retrieved_at)
                                        | retrieved {{ $card['captured']['latest_tender']->retrieved_at->copy()->timezone($austinTz)->format('Y-m-d H:i') }} Austin
                                    @endif
                                </span>
                            @elseif ($card['captured']['latest_news'])
                                <span class="intel-meta">
                                    Latest captured news: {{ $card['captured']['latest_news']->country?->name ?? 'Global / unassigned' }}
                                    @if ($card['captured']['latest_news']->retrieved_at)
                                        | retrieved {{ $card['captured']['latest_news']->retrieved_at->copy()->timezone($austinTz)->format('Y-m-d H:i') }} Austin
                                    @endif
                                </span>
                            @endif
                        </div>
                        <div class="intel-line">
                            <span class="intel-line-title">{{ $card['secondary_label'] }}</span>
                            @if ($card['secondary_item'] && $card['secondary_type'] === 'news')
                                <a class="intel-link" href="{{ route('sls.intelligence.updates.sourcePage', $card['secondary_item']) }}">
                                    {{ Str::limit($card['secondary_item']->title_english ?: $card['secondary_item']->title ?: $card['secondary_item']->title_original, 130) }}
                                </a>
                                <span class="intel-meta">
                                    {{ $card['secondary_item']->country?->name ?? 'Global / unassigned' }}
                                    @if ($card['secondary_item']->publication_date)
                                        | published {{ $card['secondary_item']->publication_date->format('Y-m-d') }}
                                    @endif
                                </span>
                            @elseif ($card['secondary_item'] && $card['secondary_type'] === 'activity')
                                @if ($card['secondary_item']['url'])
                                    <a class="intel-link" href="{{ $card['secondary_item']['url'] }}">{{ Str::limit($card['secondary_item']['title'], 130) }}</a>
                                @else
                                    <span class="intel-link">{{ Str::limit($card['secondary_item']['title'], 130) }}</span>
                                @endif
                                <span class="intel-meta">
                                    {{ $card['secondary_item']['kind'] }}
                                    @if ($card['secondary_item']['organization'])
                                        | {{ $card['secondary_item']['organization'] }}
                                    @endif
                                    @if ($card['secondary_item']['date'])
                                        | {{ $card['secondary_item']['date']->copy()->timezone($austinTz)->format('Y-m-d H:i') }} Austin
                                    @endif
                                </span>
                            @else
                                @if (($card['secondary_type'] ?? null) === 'activity')
                                    <span class="muted">No CRM account activity tagged to this product yet.</span>
                                @else
                                <span class="muted">No tagged account activity yet.</span>
                                @endif
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="panel stack">
            @php
                $trackedRegions = $countries->pluck('region')->filter()->unique()->sort()->values();
                $trackedLanguages = $countries->pluck('default_language_code')->filter()->map(fn ($code) => strtoupper($code))->unique()->sort()->values();
            @endphp
            <div class="toolbar" style="justify-content:space-between;">
                <div>
                    <p class="eyebrow">Country Intelligence</p>
                    <h2>Tracked countries</h2>
                </div>
                <a class="button secondary tiny" href="{{ route('sls.trackedCountries.export') }}">Export CSV</a>
            </div>
            <div class="tracked-country-filters" style="margin-top:10px;">
                <label>
                    Country
                    <input type="search" id="tracked-country-filter" placeholder="Type country">
                </label>
                <label>
                    Region
                    <select id="tracked-region-filter">
                        <option value="">All regions</option>
                        @foreach ($trackedRegions as $region)
                            <option value="{{ $region }}">{{ $region }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Language
                    <select id="tracked-language-filter">
                        <option value="">All languages</option>
                        @foreach ($trackedLanguages as $language)
                            <option value="{{ $language }}">{{ $language }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="table-wrap">
                <table class="data-table compact-table">
                    <thead>
                        <tr>
                            <th class="tracked-country-col">Country</th>
                            <th class="tracked-iso-col">ISO</th>
                            <th class="tracked-region-col">Region</th>
                            <th class="tracked-lang-col">Lang</th>
                            <th class="tracked-source-col">Social security administration / source</th>
                            <th class="tracked-profile-col">ILO profile</th>
                            <th class="tracked-topics-col">Topics</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($countries as $country)
                            <tr
                                data-tracked-country-row
                                data-country-name="{{ strtolower($country->name) }}"
                                data-region="{{ $country->region }}"
                                data-language="{{ strtoupper($country->default_language_code ?? 'n/a') }}"
                            >
                                <td class="tracked-country-col"><strong>{{ $country->name }}</strong></td>
                                <td class="mono tracked-iso-col">{{ strtoupper($country->iso_code ?? 'n/a') }}</td>
                                <td class="tracked-region-col">{{ $country->region }}</td>
                                <td class="mono tracked-lang-col">{{ strtoupper($country->default_language_code ?? 'n/a') }}</td>
                                <td class="tracked-source-col">
                                    @if (($country->social_security_administration_names ?? collect())->isNotEmpty())
                                        <div class="source-name-list">
                                            @foreach (($country->social_security_administration_links ?? collect()) as $admin)
                                                <div class="source-name-row">
                                                    @if (filled($admin['url'] ?? null))
                                                        <a class="source-name-value" href="{{ $admin['url'] }}" target="_blank" rel="noreferrer">{{ $admin['name'] }}</a>
                                                    @else
                                                        <span class="source-name-value muted">{{ $admin['name'] }}</span>
                                                    @endif
                                                    <span class="source-name-actions">
                                                        <button
                                                            class="source-url-edit"
                                                            type="button"
                                                            title="Edit URLs"
                                                            aria-label="Edit URLs for {{ $admin['name'] }}"
                                                            data-country-iso="{{ strtoupper($country->iso_code ?? '') }}"
                                                            data-country-name="{{ $country->name }}"
                                                            data-region="{{ $country->region }}"
                                                            data-organization-name="{{ $admin['name'] }}"
                                                            data-product-id="{{ $admin['product_id'] ?? optional($products->firstWhere('name', 'Interact SSAS'))->id }}"
                                                            data-general-url="{{ $admin['general_url'] ?? '' }}"
                                                            data-press-url="{{ $admin['press_url'] ?? '' }}"
                                                            data-tenders-url="{{ $admin['tenders_url'] ?? '' }}"
                                                        >&#9998;</button>
                                                        <button
                                                            class="source-url-delete"
                                                            type="button"
                                                            title="Remove duplicate entity"
                                                            aria-label="Remove {{ $admin['name'] }} as a duplicate"
                                                            data-country-iso="{{ strtoupper($country->iso_code ?? '') }}"
                                                            data-organization-name="{{ $admin['name'] }}"
                                                            data-product-id="{{ $admin['product_id'] ?? optional($products->firstWhere('name', 'Interact SSAS'))->id }}"
                                                        >&times;</button>
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="missing-source">Missing official source</span>
                                    @endif
                                </td>
                                <td class="tracked-profile-col">
                                    @if (filled($country->social_protection_profile_url))
                                        <a href="{{ $country->social_protection_profile_url }}" target="_blank" rel="noreferrer">Open profile</a>
                                        @if ($country->social_protection_profile_checked_at)
                                            <div class="muted tiny">Checked {{ $country->social_protection_profile_checked_at->format('Y-m-d') }}</div>
                                        @endif
                                        @if (filled($country->social_protection_profile_last_error))
                                            <div class="missing-source tiny">Last check failed</div>
                                        @endif
                                    @else
                                        <span class="muted">Not set</span>
                                    @endif
                                </td>
                                <td class="muted tracked-topics-col">{{ $country->topics->take(5)->pluck('topic')->implode(', ') ?: 'No topic run yet' }}</td>
                            </tr>
                        @endforeach
                        <tr data-tracked-country-empty hidden>
                            <td colspan="7" class="muted">No tracked countries match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="source-url-modal" id="source-url-modal" hidden>
        <div class="source-url-dialog" role="dialog" aria-modal="true" aria-labelledby="source-url-title">
            <header>
                <div>
                    <p class="eyebrow" id="source-url-country"></p>
                    <h3 id="source-url-title">Edit organization URLs</h3>
                </div>
                <button class="secondary tiny" type="button" data-source-url-close>Close</button>
            </header>
            <form id="source-url-form">
                <input type="hidden" name="country_iso">
                <input type="hidden" name="country_name">
                <input type="hidden" name="region">
                <input type="hidden" name="organization_name">
                <label>
                    Product
                    <select name="product_id" required>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    General
                    <input type="url" name="general_url" placeholder="https://example.gov">
                </label>
                <label>
                    Press
                    <input type="url" name="press_url" placeholder="https://example.gov/news">
                </label>
                <label>
                    Tenders
                    <input type="url" name="tenders_url" placeholder="https://example.gov/procurement">
                </label>
                <div class="toolbar" style="justify-content:space-between;">
                    <span class="form-status" id="source-url-status"></span>
                    <button class="button tiny" type="submit">Save URLs</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (() => {
            const countryInput = document.getElementById('tracked-country-filter');
            const regionSelect = document.getElementById('tracked-region-filter');
            const languageSelect = document.getElementById('tracked-language-filter');
            const rows = Array.from(document.querySelectorAll('[data-tracked-country-row]'));
            const emptyRow = document.querySelector('[data-tracked-country-empty]');

            if (!countryInput || !regionSelect || !languageSelect || rows.length === 0) {
                return;
            }

            const applyFilters = () => {
                const country = countryInput.value.trim().toLowerCase();
                const region = regionSelect.value;
                const language = languageSelect.value;
                let visible = 0;

                rows.forEach((row) => {
                    const matchesCountry = country === '' || (row.dataset.countryName || '').includes(country);
                    const matchesRegion = region === '' || row.dataset.region === region;
                    const matchesLanguage = language === '' || row.dataset.language === language;
                    const show = matchesCountry && matchesRegion && matchesLanguage;

                    row.hidden = !show;
                    if (show) {
                        visible++;
                    }
                });

                if (emptyRow) {
                    emptyRow.hidden = visible !== 0;
                }
            };

            countryInput.addEventListener('input', applyFilters);
            regionSelect.addEventListener('change', applyFilters);
            languageSelect.addEventListener('change', applyFilters);
        })();
    </script>
    <script>
        (() => {
            const countries = @json($dashboardMapCountries->values());
            const mapElement = document.getElementById('dashboard-world-map');

            if (!mapElement || typeof L === 'undefined') {
                if (mapElement) {
                    mapElement.innerHTML = '<div style="padding:1rem;">Map library could not load. Open the full map from the button above.</div>';
                }
                return;
            }

            const map = L.map(mapElement, {
                scrollWheelZoom: false,
                zoomControl: true,
            }).setView([10, 5], 2);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 7,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const bounds = [];
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const markersByUpdateId = new Map();

            countries.forEach((country) => {
                if (country.lat === undefined || country.lon === undefined) {
                    return;
                }

                const hasUpdate = country.status === 'has-update' && Boolean(country.latest_title);
                const marker = L.circleMarker([country.lat, country.lon], {
                    radius: hasUpdate ? 6 : 4,
                    weight: 1,
                    color: '#ffffff',
                    fillColor: markerColor(country),
                    fillOpacity: hasUpdate ? 0.92 : 0.62,
                }).addTo(map);

                if (hasUpdate) {
                    marker.bindPopup(popupFor(country), { maxWidth: 340 });
                    marker.on('popupopen', () => markOpened(country, marker));

                    if (country.latest_id) {
                        markersByUpdateId.set(String(country.latest_id), { marker, country });
                    }
                }

                bounds.push([country.lat, country.lon]);
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [18, 18] });
            }

            function popupFor(country) {
                const title = escapeHtml(country.latest_title || 'No item captured yet');
                const summary = escapeHtml(country.latest_summary || '').slice(0, 230);
                const dateAndSource = [country.latest_date || 'Date not captured', country.latest_source].filter(Boolean).join(' | ');
                const itemType = country.latest_type === 'tender' ? 'Tender' : 'News story';
                const stateText = country.latest_processed
                    ? 'Processed'
                    : (country.latest_opened ? 'Seen' : 'Unopened');
                const link = country.latest_url
                    ? `<p><a href="${escapeHtml(country.latest_url)}" target="_blank" rel="noreferrer">Open original source</a></p>`
                    : '';
                const action = country.latest_action_url
                    ? `<div class="map-popup-actions" data-map-action="${escapeHtml(country.latest_action_url)}" data-update-id="${escapeHtml(country.latest_id)}" data-follow-up-url="${escapeHtml(country.latest_follow_up_url || '')}">
                            <select aria-label="Map item action">
                                <option value="no_action_required">Verified, no further action</option>
                                <option value="action_taken">Action already taken</option>
                                <option value="follow_up">Follow up</option>
                            </select>
                            <button class="button tiny" type="button" data-map-action-save>Save action</button>
                            <span class="map-popup-status">${escapeHtml(stateText)}</span>
                        </div>`
                    : '';

                return `<div class="map-popup"><h3>${escapeHtml(country.name)}</h3><p><strong>${title}</strong></p><p><span class="pill">${escapeHtml(itemType)}</span> <span class="pill">${escapeHtml(stateText)}</span></p><p>${summary}</p><p>${escapeHtml(dateAndSource)}</p>${link}${action}</div>`;
            }

            function markerColor(country) {
                if (country.status !== 'has-update' || !country.latest_title) {
                    return '#7bb7cf';
                }

                if (country.latest_opened || country.latest_processed) {
                    return '#9ca3af';
                }

                return country.latest_type === 'tender' ? '#2563eb' : '#d62828';
            }

            function paintMarker(marker, country) {
                marker.setStyle({
                    fillColor: markerColor(country),
                    fillOpacity: country.status === 'has-update' ? 0.92 : 0.62,
                });
            }

            function markOpened(country, marker) {
                if (!country.latest_opened && country.latest_opened_url) {
                    country.latest_opened = true;
                    paintMarker(marker, country);

                    fetch(country.latest_opened_url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                    }).catch(() => {
                        country.latest_opened = false;
                        paintMarker(marker, country);
                    });
                    return;
                }

                paintMarker(marker, country);
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            map.on('popupopen', (event) => {
                const root = event.popup.getElement();
                const action = root?.querySelector('[data-map-action]');
                const save = root?.querySelector('[data-map-action-save]');
                const status = root?.querySelector('.map-popup-status');

                if (!action || !save) {
                    return;
                }

                save.addEventListener('click', async () => {
                    const updateId = action.dataset.updateId || '';
                    const record = markersByUpdateId.get(updateId);
                    const selected = action.querySelector('select')?.value || 'no_action_required';
                    if (selected === 'follow_up') {
                        const followUpUrl = action.dataset.followUpUrl || '';
                        if (followUpUrl) {
                            window.location.href = followUpUrl;
                            return;
                        }
                    }

                    save.disabled = true;
                    if (status) {
                        status.textContent = 'Saving...';
                    }

                    try {
                        const response = await fetch(action.dataset.mapAction, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ action_status: selected }),
                        });

                        if (!response.ok) {
                            throw new Error('Save failed');
                        }

                        if (record) {
                            record.country.latest_opened = true;
                            record.country.latest_processed = selected !== 'follow_up';
                            record.country.latest_action_status = selected;
                            paintMarker(record.marker, record.country);
                        }

                        if (status) {
                            status.textContent = selected === 'follow_up' ? 'Follow-up filed' : 'Processed';
                        }

                        if (selected !== 'follow_up') {
                            window.setTimeout(() => map.closePopup(event.popup), 120);
                        }
                    } catch (error) {
                        if (status) {
                            status.textContent = 'Could not save action';
                        }
                    } finally {
                        save.disabled = false;
                    }
                }, { once: true });
            });
        })();
    </script>
    <script>
        (() => {
            const modal = document.getElementById('source-url-modal');
            const form = document.getElementById('source-url-form');
            const title = document.getElementById('source-url-title');
            const country = document.getElementById('source-url-country');
            const status = document.getElementById('source-url-status');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            let activeButton = null;

            if (!modal || !form) {
                return;
            }

            document.querySelectorAll('.source-url-edit').forEach((button) => {
                button.addEventListener('click', () => {
                    activeButton = button;
                    status.textContent = '';
                    title.textContent = button.dataset.organizationName || 'Edit organization URLs';
                    country.textContent = [button.dataset.countryName, button.dataset.countryIso].filter(Boolean).join(' | ');
                    form.elements.country_iso.value = button.dataset.countryIso || '';
                    form.elements.country_name.value = button.dataset.countryName || '';
                    form.elements.region.value = button.dataset.region || '';
                    form.elements.organization_name.value = button.dataset.organizationName || '';
                    form.elements.product_id.value = button.dataset.productId || form.elements.product_id.options[0]?.value || '';
                    form.elements.general_url.value = button.dataset.generalUrl || '';
                    form.elements.press_url.value = button.dataset.pressUrl || '';
                    form.elements.tenders_url.value = button.dataset.tendersUrl || '';
                    modal.hidden = false;
                    form.elements.general_url.focus();
                });
            });

            document.querySelectorAll('.source-url-delete').forEach((button) => {
                button.addEventListener('click', async () => {
                    const organizationName = button.dataset.organizationName || 'this organization';
                    const countryIso = button.dataset.countryIso || '';

                    if (!confirm(`Remove ${organizationName} from ${countryIso || 'this country'} as a duplicate?`)) {
                        return;
                    }

                    button.disabled = true;

                    try {
                        const response = await fetch('{{ route('sls.trackedCountries.adminUrls.delete') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({
                                country_iso: countryIso,
                                organization_name: organizationName,
                                product_id: button.dataset.productId || '',
                                reason: 'duplicate',
                            }),
                        });
                        const body = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            throw new Error(body.message || 'Could not remove this organization.');
                        }

                        window.location.reload();
                    } catch (error) {
                        alert(error.message || 'Could not remove this organization.');
                        button.disabled = false;
                    }
                });
            });

            modal.querySelectorAll('[data-source-url-close]').forEach((button) => {
                button.addEventListener('click', closeModal);
            });

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.hidden) {
                    closeModal();
                }
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                status.textContent = 'Saving...';

                const payload = Object.fromEntries(new FormData(form).entries());

                try {
                    const response = await fetch('{{ route('sls.trackedCountries.adminUrls.update') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(payload),
                    });
                    const body = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(body.message || 'Could not save URLs.');
                    }

                    if (activeButton) {
                        activeButton.dataset.generalUrl = body.urls?.general_url || '';
                        activeButton.dataset.pressUrl = body.urls?.press_url || '';
                        activeButton.dataset.tendersUrl = body.urls?.tenders_url || '';
                        activeButton.dataset.productId = body.product_id || payload.product_id || '';
                    }

                    window.location.reload();
                } catch (error) {
                    status.textContent = error.message || 'Could not save URLs.';
                }
            });

            function closeModal() {
                modal.hidden = true;
                status.textContent = '';
                activeButton = null;
            }
        })();
    </script>
@endpush




