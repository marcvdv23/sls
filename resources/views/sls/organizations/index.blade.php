@extends('sls.layouts.app')

@section('title', 'Organization Directory - 1G-SLS')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Organization Directory')

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
        .org-filter-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 8px; align-items: end; }
        .org-filter-grid label { display: grid; gap: 4px; }
        .org-filter-grid input,
        .org-filter-grid select { min-height: 38px; }
        .org-filter-grid .name-filter { grid-column: 1 / span 3; grid-row: 1; }
        .org-filter-grid .type-filter { grid-column: 4 / span 3; grid-row: 1; }
        .org-filter-grid .industry-filter { grid-column: 7 / span 3; grid-row: 1; }
        .org-filter-grid .subcategory-filter { grid-column: 10 / span 3; grid-row: 1; }
        .org-filter-grid .crawler-filter { grid-column: 1 / span 4; grid-row: 2; }
        .org-filter-grid .lead-filter { grid-column: 5 / span 2; grid-row: 2; }
        .org-filter-grid .country-status-filter { grid-column: 7 / span 2; grid-row: 2; }
        .org-filter-grid .country-filter { grid-column: 9 / span 2; grid-row: 2; }
        .org-filter-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; grid-column: 11 / span 2; grid-row: 2; align-self: end; }
        .org-filter-actions .button { min-height: 38px; padding: 8px 14px; }
        .org-filter-grid .student-from-filter,
        .org-filter-grid .student-to-filter,
        .org-filter-grid .school-state-filter { display: none; }
        .org-filter-grid.show-student-filters .student-from-filter { display: grid; grid-column: 1 / span 2; grid-row: 3; }
        .org-filter-grid.show-student-filters .student-to-filter { display: grid; grid-column: 3 / span 2; grid-row: 3; }
        .org-filter-grid.show-student-filters .school-state-filter { display: grid; grid-column: 5 / span 2; grid-row: 3; }
        .org-directory-table { min-width: 1320px; }
        .org-directory-table th,
        .org-directory-table td { padding: 8px 10px; vertical-align: middle; }
        .org-directory-table .name-col { min-width: 230px; }
        .org-directory-table .type-col { width: 145px; }
        .org-directory-table .iso-col { width: 78px; text-align: center; }
        .org-directory-table .status-col { width: 120px; }
        .org-directory-table .count-col,
        .org-directory-table .flag-col { width: 78px; text-align: center; }
        .org-directory-table .date-col { width: 132px; }
        .org-directory-table .crawler-col { width: 150px; }
        .org-import-form { display: grid; gap: 8px; margin-top: .8rem; }
        .import-grid { display: grid; grid-template-columns: repeat(5, minmax(140px, 1fr)); gap: 8px; align-items: end; }
        .import-file-row { display: grid; grid-template-columns: minmax(260px, 1fr) 180px; gap: 8px; align-items: end; }
        .import-file-row .button { width: 100%; min-height: 38px; }
        .crawler-table { min-width: 760px; margin-top: 10px; }
        .crawler-table th,
        .crawler-table td { padding: 8px 10px; }
        .stat-link { color: inherit; text-decoration: none; }
        .stat-link:hover strong { color: var(--accent-primary); }
        .legacy-page table { background: var(--bg-secondary); }
        .legacy-page .empty { border: 1px dashed var(--border-subtle); border-radius: var(--radius-card); padding: 14px; }
        @media (max-width: 980px) {
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
            .org-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .org-filter-grid .country-filter,
            .org-filter-grid .crawler-filter,
            .org-filter-grid .lead-filter,
            .org-filter-grid .country-status-filter,
            .org-filter-grid.show-student-filters .student-from-filter,
            .org-filter-grid.show-student-filters .student-to-filter,
            .org-filter-grid.show-student-filters .school-state-filter,
            .org-filter-actions { grid-column: auto; grid-row: auto; }
            .import-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .import-file-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .org-filter-grid { grid-template-columns: 1fr; }
            .import-grid { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page">
<header>
            <div>
                <p class="eyebrow">Market Intelligence</p>
                <h1>Organization Directory</h1>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('sls.intelligence.sources') }}">Source coverage</a>
                <a class="button secondary" href="{{ route('sls.emailAccounts.index') }}">Email boxes</a>
                <a class="button secondary" href="{{ route('sls.survey.universities') }}">University survey contacts</a>
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
            </div>
        </header>

        <main class="stack">
            @if (session('status'))
                <section class="panel"><p class="muted">{{ session('status') }}</p></section>
            @endif

            <section class="summary">
                <div class="panel stat"><strong>{{ $organizations->count() }}</strong><span class="muted">organizations shown</span></div>
                <div class="panel stat"><strong>{{ $organizations->sum('contacts_count') }}</strong><span class="muted">linked contacts</span></div>
                <a class="panel stat stat-link" href="#crawler-registry"><strong>{{ $crawlers->count() }}</strong><span class="muted">named crawlers</span></a>
                <div class="panel stat"><strong>{{ $organizations->whereNotNull('procurement_page_url')->count() }}</strong><span class="muted">with procurement page URL</span></div>
            </section>

            <section class="panel">
                <p class="eyebrow">Search</p>
                <h2>Filter organizations by type, industry, country, crawler, or name</h2>
                <form class="org-filter-grid {{ ($filters['subcategory'] ?? 'all') === 'school_district' ? 'show-student-filters' : '' }}" method="get" action="{{ route('sls.organizations.index') }}">
                    <label class="name-filter">Name / website
                        <input name="q" value="{{ $filters['query'] }}" placeholder="Search organization name or domain">
                    </label>
                    <label class="type-filter">Type
                        <select name="type">
                            <option value="all">All</option>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" {{ $filters['type'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="industry-filter">Industry
                        <select name="industry">
                            <option value="all">All</option>
                            @foreach ($industries as $value => $label)
                                <option value="{{ $value }}" {{ $filters['industry'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="subcategory-filter">Subcategory
                        <select name="subcategory" id="organization-subcategory-filter">
                            <option value="all">All</option>
                            @foreach ($subcategories as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['subcategory'] ?? 'all') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="org-filter-actions">
                        <button class="button" type="submit">Apply</button>
                        <a class="button secondary" href="{{ route('sls.organizations.index') }}">Clear</a>
                    </div>
                    <label class="crawler-filter">Crawler
                        <select name="crawler">
                            <option value="all">All</option>
                            @foreach ($crawlers as $crawler)
                                <option value="{{ $crawler->id }}" {{ (string) $filters['crawlerId'] === (string) $crawler->id ? 'selected' : '' }}>{{ $crawler->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="lead-filter">Lead status
                        <select name="lead_status">
                            <option value="all">All</option>
                            @foreach ($leadStatuses as $value => $label)
                                <option value="{{ $value }}" {{ $filters['leadStatus'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="country-status-filter">Country status
                        <select name="country_status">
                            <option value="all">All</option>
                            <option value="resolved" {{ $filters['countryStatus'] === 'resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="missing" {{ $filters['countryStatus'] === 'missing' ? 'selected' : '' }}>Missing</option>
                            <option value="unresolved" {{ $filters['countryStatus'] === 'unresolved' ? 'selected' : '' }}>Unresolved</option>
                        </select>
                    </label>
                    <label class="country-filter">Country
                        <input name="country" value="{{ $filters['country'] }}" placeholder="Country / ISO">
                    </label>
                    <label class="student-from-filter">Students from
                        <input name="students_from" value="{{ $filters['studentsFrom'] ?? '' }}" inputmode="numeric" placeholder="Minimum">
                    </label>
                    <label class="student-to-filter">Students to
                        <input name="students_to" value="{{ $filters['studentsTo'] ?? '' }}" inputmode="numeric" placeholder="Maximum">
                    </label>
                    <label class="school-state-filter">State
                        <input name="school_state" value="{{ $filters['schoolDistrictState'] ?? '' }}" placeholder="TX or Texas">
                    </label>
                </form>
            </section>

            <section class="panel">
                <p class="eyebrow">Import</p>
                <h2>Add organizations</h2>
                <p class="muted" style="margin-top:.35rem;">Use one line per organization or upload a CSV/TXT file. Supported formats include: Organization name, Country, https://website.example; ISO, Organization name, https://website.example; airline rows; one-column lists with a default country; and large school district CSV files with headers such as district/name, state, website, phone, address, or district ID. Missing websites are allowed and can be enriched by a crawler later.</p>
                <form method="post" action="{{ route('sls.organizations.import') }}" class="org-import-form" enctype="multipart/form-data">
                    @csrf
                    <div class="import-grid">
                        <label>Type
                            <select name="organization_type" required>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Industry
                            <select name="industry" required>
                                @foreach ($industries as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Subcategory
                            <select name="organization_subcategory">
                                <option value="">None</option>
                                @foreach ($subcategories as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Crawler
                            <select name="market_crawler_id">
                                <option value="">Unassigned</option>
                                @foreach ($crawlers as $crawler)
                                    <option value="{{ $crawler->id }}">{{ $crawler->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Default country
                            <input name="default_country" placeholder="Optional, e.g. South Africa">
                        </label>
                    </div>
                    <div class="import-file-row">
                        <label>CSV or TXT file
                            <input name="organization_file" type="file" accept=".csv,.txt,text/csv,text/plain">
                        </label>
                        <button class="button" type="submit">Import organizations</button>
                    </div>
                </form>
            </section>

            <section class="panel" id="crawler-registry">
                <p class="eyebrow">Crawler Registry</p>
                <h2>Named crawlers</h2>
                <form method="post" action="{{ route('sls.organizations.crawlers.store') }}" class="import-grid">
                    @csrf
                    <label>Name
                        <input name="name" required placeholder="e.g. Asia university procurement crawler">
                    </label>
                    <label>Crawler type
                        <select name="crawler_type" required>
                            @foreach ($crawlerTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Description
                        <input name="description" placeholder="What this crawler checks">
                    </label>
                    <button class="button" type="submit">Add crawler</button>
                </form>
                <div class="table-wrap">
                    <table class="crawler-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Last Crawl</th>
                                <th>Assigned</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($crawlers as $crawler)
                                @php
                                    $lastCrawlCandidates = collect([$crawler->last_run_at, $crawler->organizations_max_last_crawled_at])->filter();
                                    $lastCrawl = $lastCrawlCandidates->sortDesc()->first();
                                @endphp
                                <tr>
                                    <td>{{ $crawler->id }}</td>
                                    <td><strong><a href="{{ route('sls.organizations.crawlers.show', $crawler) }}">{{ $crawler->name }}</a></strong></td>
                                    <td>{{ $crawlerTypes[$crawler->crawler_type] ?? $crawler->crawler_type }}</td>
                                    <td class="nowrap">{{ $lastCrawl ? $lastCrawl->copy()->timezone('America/Chicago')->format('Y-m-d H:i') : 'Never' }}</td>
                                    <td>{{ $crawler->organizations_count ?? 0 }}</td>
                                    <td>{{ $crawler->description ?: 'No description captured' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="muted">No named crawlers have been created yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <p class="eyebrow">Directory</p>
                <h2>Organizations and contact coverage</h2>
                <div class="table-wrap">
                    <table class="org-directory-table">
                        <thead>
                            <tr>
                                <th class="name-col">Organization</th>
                                <th class="type-col">Type</th>
                                <th class="iso-col">ISO</th>
                                <th class="status-col">Lead status</th>
                                <th class="flag-col">Website</th>
                                <th class="flag-col">Phone</th>
                                <th class="count-col">Contacts</th>
                                <th class="count-col">Activities</th>
                                <th class="count-col">Tasks</th>
                                <th class="count-col">Comms</th>
                                <th class="flag-col">Proc.</th>
                                <th class="flag-col">Leader</th>
                                <th class="flag-col">HR</th>
                                <th class="flag-col">IT</th>
                                <th class="date-col">Last crawled</th>
                                <th class="crawler-col">Crawler</th>
                                <th class="action-col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($organizations as $organization)
                                <tr>
                                    <td>
                                        <strong><a href="{{ route('sls.organizations.show', $organization) }}">{{ $organization->name }}</a></strong>
                                    </td>
                                    <td>{{ $types[$organization->organization_type] ?? $organization->organization_type }}</td>
                                    <td class="iso-col">{{ $organization->country_iso ?: '??' }}</td>
                                    <td><span class="pill">{{ $leadStatuses[$organization->lead_status] ?? $organization->lead_status }}</span></td>
                                    <td>{{ $organization->website_url ? 'Yes' : 'No' }}</td>
                                    <td>{{ $organization->organization_phone ? 'Yes' : 'No' }}</td>
                                    <td><span class="pill">{{ $organization->contacts_count }}</span></td>
                                    <td><span class="pill">{{ $organization->activities_count }}</span></td>
                                    <td><span class="pill">{{ $organization->tasks_count }}</span></td>
                                    <td><span class="pill">{{ $organization->communications_count }}</span></td>
                                    <td>{{ $organization->procurement_page_url ? 'Yes' : 'No' }}</td>
                                    <td>{{ $organization->leadership_page_url ? 'Yes' : 'No' }}</td>
                                    <td>{{ $organization->hr_page_url ? 'Yes' : 'No' }}</td>
                                    <td>{{ $organization->it_page_url ? 'Yes' : 'No' }}</td>
                                    <td class="nowrap">{{ $organization->last_crawled_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'Not crawled yet' }}</td>
                                    <td>{{ $organization->crawler?->name ?? $organization->last_crawler_name ?? 'Unassigned' }}</td>
                                    <td class="nowrap"><a class="button small secondary" href="{{ route('sls.organizations.edit', $organization) }}">Edit</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="17" class="muted">No organizations match this filter yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('.org-filter-grid');
            const subcategory = document.getElementById('organization-subcategory-filter');
            if (!form || !subcategory) {
                return;
            }

            const toggleStudentFilters = () => {
                form.classList.toggle('show-student-filters', subcategory.value === 'school_district');
            };

            subcategory.addEventListener('change', toggleStudentFilters);
            toggleStudentFilters();
        })();
    </script>
@endpush
