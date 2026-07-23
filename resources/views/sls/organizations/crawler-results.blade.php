@extends('sls.layouts.app')

@section('title', 'Crawler Results - 1G-SLS')
@section('eyebrow', 'Crawler Registry')
@section('page_title', 'Crawler Results')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.organizations.index') }}#crawler-registry">Crawler registry</a>
    <a class="button secondary" href="{{ route('sls.organizations.crawlers.show', 1) }}">Crawlers & SerpAPI</a>
    <a class="button" href="{{ route('sls.organizations.crawlerResults', array_merge(request()->except('export'), ['export' => 'csv'])) }}">Export CSV</a>
@endsection

@push('head')
    <style>
        .crawler-results-filters {
            display:grid;
            grid-template-columns:minmax(220px, 1.3fr) minmax(180px, 1fr) repeat(6, minmax(115px, .5fr)) auto auto;
            gap:8px;
            align-items:end;
        }
        .crawler-results-kpis {
            align-items:center;
            border:1px solid var(--border-subtle);
            border-radius:8px;
            display:flex;
            flex-wrap:wrap;
            gap:0;
            overflow:hidden;
        }
        .crawler-results-kpis span {
            align-items:baseline;
            border-right:1px solid var(--border-subtle);
            display:inline-flex;
            gap:6px;
            padding:8px 12px;
            white-space:nowrap;
        }
        .crawler-results-kpis span:last-child { border-right:0; }
        .crawler-results-kpis strong {
            font-family:"JetBrains Mono", ui-monospace, monospace;
            font-size:15px;
        }
        .crawler-results-kpis em {
            color:var(--text-secondary);
            font-style:normal;
            font-size:12px;
        }
        .crawler-results-filters label { margin:0; }
        .crawler-results-table { min-width:1800px; table-layout:fixed; }
        .crawler-results-table th,
        .crawler-results-table td { padding:8px 10px; vertical-align:top; }
        .org-col { width:260px; }
        .country-col { width:80px; }
        .crawler-col { width:190px; }
        .url-col { width:130px; }
        .contact-col { width:230px; }
        .email-col { width:220px; }
        .source-col { width:260px; }
        .date-col { width:130px; }
        .context-col { width:320px; }
        .compact-links { display:flex; flex-wrap:wrap; gap:5px; }
        .compact-links a,
        .yes-no {
            border:1px solid var(--border-subtle);
            border-radius:6px;
            color:var(--accent-primary);
            display:inline-flex;
            font-size:12px;
            font-weight:800;
            line-height:1;
            padding:5px 7px;
            text-decoration:none;
        }
        .yes-no.missing { color:var(--text-muted); }
        .cell-muted { color:var(--text-secondary); font-size:12px; margin-top:3px; }
        .truncate { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        @media (max-width:1200px) {
            .crawler-results-filters { grid-template-columns:repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width:760px) {
            .crawler-results-filters { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="stack">
        <section class="crawler-results-kpis" aria-label="Crawler result totals">
            <span><strong>{{ $summary['rows'] }}</strong><em>rows</em></span>
            <span><strong>{{ $summary['emails'] }}</strong><em>email rows</em></span>
            <span><strong>{{ $summary['contacts'] }}</strong><em>contact rows</em></span>
            <span><strong>{{ $summary['needs_name_research'] }}</strong><em>need name research</em></span>
            <span><strong>{{ $summary['procurement_urls'] }}</strong><em>procurement URLs</em></span>
            <span><strong>{{ $summary['leadership_urls'] }}</strong><em>leadership URLs</em></span>
            <span><strong>{{ $summary['news_urls'] }}</strong><em>news URLs</em></span>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Filters</p>
                <h2>Search captured crawler contacts and URLs</h2>
            </div>
            <form class="crawler-results-filters" method="get" action="{{ route('sls.organizations.crawlerResults') }}">
                <label>Search
                    <input name="q" value="{{ $filters['query'] }}" placeholder="Organization, email, title, URL">
                </label>
                <label>Crawler
                    <select name="crawler">
                        <option value="all">All</option>
                        @foreach ($crawlers as $crawler)
                            <option value="{{ $crawler->id }}" @selected((string) $crawler->id === (string) $filters['crawlerId'])>{{ $crawler->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Country
                    <input name="country" value="{{ $filters['country'] }}" placeholder="ISO / country">
                </label>
                <label>Email
                    <select name="has_email">
                        <option value="all" @selected($filters['hasEmail'] === 'all')>All</option>
                        <option value="yes" @selected($filters['hasEmail'] === 'yes')>Yes</option>
                        <option value="no" @selected($filters['hasEmail'] === 'no')>No</option>
                    </select>
                </label>
                <label>Contact quality
                    <select name="contact_quality">
                        <option value="all" @selected($filters['contactQuality'] === 'all')>All</option>
                        <option value="needs_name_research" @selected($filters['contactQuality'] === 'needs_name_research')>Needs name</option>
                        <option value="published" @selected($filters['contactQuality'] === 'published')>Clean</option>
                        <option value="no_contact" @selected($filters['contactQuality'] === 'no_contact')>No contact</option>
                    </select>
                </label>
                <label>Procurement
                    <select name="has_procurement">
                        <option value="all" @selected($filters['hasProcurement'] === 'all')>All</option>
                        <option value="yes" @selected($filters['hasProcurement'] === 'yes')>Yes</option>
                        <option value="no" @selected($filters['hasProcurement'] === 'no')>No</option>
                    </select>
                </label>
                <label>Leadership
                    <select name="has_leadership">
                        <option value="all" @selected($filters['hasLeadership'] === 'all')>All</option>
                        <option value="yes" @selected($filters['hasLeadership'] === 'yes')>Yes</option>
                        <option value="no" @selected($filters['hasLeadership'] === 'no')>No</option>
                    </select>
                </label>
                <label>News
                    <select name="has_news">
                        <option value="all" @selected($filters['hasNews'] === 'all')>All</option>
                        <option value="yes" @selected($filters['hasNews'] === 'yes')>Yes</option>
                        <option value="no" @selected($filters['hasNews'] === 'no')>No</option>
                    </select>
                </label>
                <button class="button" type="submit">Apply</button>
                <a class="button secondary" href="{{ route('sls.organizations.crawlerResults') }}">Clear</a>
            </form>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Results</p>
                <h2>Captured emails and URLs</h2>
                <p class="muted">Showing up to 750 rows on screen. Export CSV includes up to 5,000 rows for the same filters.</p>
            </div>
            <div class="table-wrap">
                <table class="data-table crawler-results-table">
                    <thead>
                        <tr>
                            <th class="org-col">Organization</th>
                            <th class="country-col">Country</th>
                            <th class="crawler-col">Crawler</th>
                            <th class="url-col">Captured URLs</th>
                            <th class="contact-col">Contact</th>
                            <th class="email-col">Email / phone</th>
                            <th class="source-col">Source page</th>
                            <th class="date-col">Last crawl</th>
                            <th class="date-col">Extracted</th>
                            <th class="context-col">Context</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>
                                    <strong><a href="{{ route('sls.organizations.show', $row->organization_id) }}">{{ $row->organization_name }}</a></strong>
                                    <div class="cell-muted">{{ $row->organization_type }}{{ $row->organization_subcategory ? ' / ' . $row->organization_subcategory : '' }}</div>
                                </td>
                                <td>{{ $row->country_iso ?: '-' }}</td>
                                <td>{{ $row->crawler_name ?: 'Unassigned' }}</td>
                                <td>
                                    <div class="compact-links">
                                        @if ($row->website_url)<a href="{{ $row->website_url }}" rel="noreferrer">Website</a>@else<span class="yes-no missing">Website: No</span>@endif
                                        @if ($row->procurement_page_url)<a href="{{ $row->procurement_page_url }}" rel="noreferrer">Procurement</a>@else<span class="yes-no missing">Proc: No</span>@endif
                                        @if ($row->leadership_page_url)<a href="{{ $row->leadership_page_url }}" rel="noreferrer">Leadership</a>@else<span class="yes-no missing">Lead: No</span>@endif
                                        @if ($row->news_page_url)<a href="{{ $row->news_page_url }}" rel="noreferrer">News</a>@else<span class="yes-no missing">News: No</span>@endif
                                    </div>
                                </td>
                                <td>
                                    <strong>{{ $row->person_name ?: ($row->job_title ?: ($row->verification_status === 'needs_name_research' ? 'Name needed' : 'No contact captured')) }}</strong>
                                    <div class="cell-muted">
                                        @if ($row->person_name && $row->job_title)
                                            {{ $row->job_title }}
                                        @elseif (! $row->person_name && $row->job_title)
                                            Role email - name needed
                                        @elseif ($row->verification_status === 'needs_name_research' && $row->email)
                                            Email found - name not found yet
                                        @else
                                            {{ $row->contact_type ?: '-' }}
                                        @endif
                                    </div>
                                    @if ($row->verification_status === 'needs_name_research')
                                        <div class="cell-muted">Needs name research</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($row->email)
                                        <span class="truncate">{{ $row->email }}</span>
                                    @else
                                        <span class="muted">No email</span>
                                    @endif
                                    <div class="cell-muted">{{ $row->phone ?: 'No phone' }}</div>
                                </td>
                                <td>
                                    @if ($row->contact_source_url)
                                        <a href="{{ $row->contact_source_url }}" rel="noreferrer">{{ parse_url($row->contact_source_url, PHP_URL_HOST) ?: 'Open source' }}</a>
                                    @else
                                        <span class="muted">No contact source</span>
                                    @endif
                                </td>
                                <td>{{ $row->last_crawled_at ?: 'Not crawled' }}</td>
                                <td>{{ $row->extracted_at ?: '-' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit((string) ($row->context_excerpt ?: $row->last_error), 260) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="muted">No crawler results match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
