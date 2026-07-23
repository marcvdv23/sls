@extends('sls.layouts.app')

@section('title', $crawler->name . ' - Crawler Logs')
@section('eyebrow', 'Crawler Registry')
@section('page_title', $crawler->name)

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.organizations.index') }}#crawler-registry">All crawlers</a>
    <a class="button secondary" href="{{ route('sls.organizations.index', ['crawler' => $crawler->id]) }}">Assigned organizations</a>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.js-crawler-country-select').forEach((select) => {
            const input = document.querySelector(`[name="${select.dataset.targetInput}"]`);
            const hint = document.getElementById(select.dataset.targetHint);
            const maxCount = Number.parseInt(select.dataset.maxCount || '1000', 10);
            const mode = select.dataset.countMode || 'total';

            const updateCount = () => {
                if (!input) {
                    return;
                }

                const selected = Array.from(select.selectedOptions);
                const total = selected.reduce((sum, option) => sum + Number.parseInt(option.dataset.total || '0', 10), 0);
                const missing = selected.reduce((sum, option) => sum + Number.parseInt(option.dataset.missingDomain || '0', 10), 0);
                const notCrawled = selected.reduce((sum, option) => sum + Number.parseInt(option.dataset.notCrawled || '0', 10), 0);
                const selectedCount = mode === 'missing' ? missing : total;
                const stagedCount = Math.min(maxCount, selectedCount);

                if (selected.length > 0) {
                    input.value = Math.max(1, stagedCount);
                }

                if (hint) {
                    if (selected.length === 0) {
                        hint.textContent = 'Select countries to calculate the staging count.';
                    } else if (mode === 'missing') {
                        hint.textContent = `${selected.length} countries selected: ${missing} missing domain record(s), ${total} total. Cap ${maxCount}.`;
                    } else {
                        hint.textContent = `${selected.length} countries selected: ${total} total record(s), ${notCrawled} not crawled. Cap ${maxCount}.`;
                    }
                }
            };

            select.addEventListener('change', updateCount);
            updateCount();
        });
    </script>
@endpush

@push('head')
    <style>
        .crawler-summary { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; }
        .crawler-status-panel { grid-column:1 / -1; display:grid; grid-template-columns:260px 1fr; gap:18px; align-items:start; }
        .crawler-status-panel strong { display:block; }
        .crawler-status-panel .issue-text { max-height:96px; overflow:auto; line-height:1.35; }
        .crawler-actions-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; }
        .crawler-actions-grid form { display:grid; grid-template-columns:1fr 120px 120px 150px; gap:8px; align-items:end; }
        .crawler-preview-form { display:grid; grid-template-columns:minmax(420px, 1fr) 84px 84px 230px; gap:8px; align-items:end; }
        .crawler-preview-form input[type="number"] { text-align:center; }
        .compact-multiselect { min-height:96px; }
        .crawler-log-table { min-width:1100px; }
        .crawler-run-table { min-width:1250px; }
        .staged-table { min-width:900px; }
        .crawler-log-table th,
        .crawler-log-table td,
        .crawler-run-table th,
        .crawler-run-table td,
        .staged-table th,
        .staged-table td { padding:8px 10px; vertical-align:middle; }
        .crawler-log-table .name-col { min-width:260px; }
        .crawler-log-table .url-col { width:90px; text-align:center; }
        .crawler-picker { display:grid; grid-template-columns:minmax(280px, 520px) auto; gap:8px; align-items:end; }
        .crawler-count-hint { margin-top:4px; font-size:12px; }
        @media (max-width:980px) {
            .crawler-summary { grid-template-columns:1fr 1fr; }
            .crawler-status-panel { grid-template-columns:1fr; }
            .crawler-actions-grid { grid-template-columns:1fr; }
            .crawler-actions-grid form { grid-template-columns:1fr 1fr; }
            .crawler-preview-form { grid-template-columns:1fr 1fr; }
        }
        @media (max-width:640px) {
            .crawler-summary { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    @php
        $lastCrawl = collect([$crawler->last_run_at, $recentCrawls->max('last_crawled_at')])->filter()->sortDesc()->first();
    @endphp

    <div class="stack">
        <section class="crawler-summary">
            <div class="panel stat">
                <strong>{{ $assignedOrganizations }}</strong>
                <span class="muted">assigned organizations</span>
            </div>
            <div class="panel stat">
                <strong>{{ $recentCrawls->count() }}</strong>
                <span class="muted">crawl entries in last 5 days</span>
            </div>
            <div class="panel crawler-status-panel">
                <div>
                    <strong>{{ $lastCrawl ? $lastCrawl->copy()->timezone('America/Chicago')->format('Y-m-d H:i') : 'Never' }}</strong>
                    <span class="muted">last crawl Austin time</span>
                </div>
                <div>
                    <strong>{{ $crawler->last_error ? 'Issue' : 'OK' }}</strong>
                    <span class="muted issue-text">{{ $crawler->last_error ?: 'no crawler-level error recorded' }}</span>
                </div>
            </div>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Crawler Definition</p>
                <h2>Selected crawler: {{ $crawlerTypes[$crawler->crawler_type] ?? $crawler->crawler_type }}</h2>
                <p class="muted">{{ $crawler->description ?: 'No description captured.' }}</p>
            </div>
            <div class="crawler-picker">
                <label>Switch crawler
                    <select onchange="if (this.value) window.location.href = this.value;">
                        @foreach (($allCrawlers ?? collect()) as $availableCrawler)
                            <option value="{{ route('sls.organizations.crawlers.show', $availableCrawler) }}" @selected($availableCrawler->id === $crawler->id)>
                                {{ $availableCrawler->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <a class="button secondary" href="{{ route('sls.organizations.index') }}#crawler-registry">Crawler registry</a>
            </div>
        </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Controlled Pilot</p>
                    <h2>{{ ucfirst($crawlerProfile['singular']) }} URL discovery and crawl</h2>
                    <p class="muted">{{ $crawlerProfile['description'] }}</p>
                </div>
                <form class="crawler-preview-form" method="get" action="{{ route('sls.organizations.crawlers.show', $crawler) }}">
                    <input type="hidden" name="preview_serpapi" value="1">
                    <label>Countries
                        <select class="compact-multiselect js-crawler-country-select" name="preview_countries[]" multiple data-count-mode="missing" data-target-input="preview_organization_limit" data-target-hint="serpapi-stage-count-hint" data-max-count="100">
                            @foreach ($countryOptions as $countryOption)
                                <option value="{{ $countryOption['iso'] }}" data-total="{{ $countryOption['total'] }}" data-missing-domain="{{ $countryOption['missing_domain'] }}" data-not-crawled="{{ $countryOption['not_crawled'] }}" @selected(in_array($countryOption['iso'], $discoveryPreviewFilters['countries'] ?? [], true))>
                                    {{ $countryOption['region'] }} - {{ $countryOption['label'] }} ({{ $countryOption['iso'] }}, {{ $countryOption['language'] }}) - {{ $countryOption['missing_domain'] }} without domain / {{ $countryOption['total'] }} total
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>{{ ucfirst($crawlerProfile['plural']) }} to stage
                        <input name="preview_organization_limit" type="number" min="1" max="100" value="{{ $discoveryPreviewFilters['organization_limit'] ?? 25 }}">
                        <span id="serpapi-stage-count-hint" class="muted crawler-count-hint"></span>
                    </label>
                    <label>Results/query
                        <input name="preview_results_per_query" type="number" min="1" max="10" value="{{ $discoveryPreviewFilters['results_per_query'] ?? 5 }}">
                    </label>
                    <button class="button secondary" type="submit">Preview staged SerpAPI list</button>
                </form>
                @if (($discoveryPreviewFilters['countries'] ?? []) !== [])
                    <p class="muted">Current SerpAPI preview scope: {{ count($discoveryPreviewFilters['countries']) }} selected countr{{ count($discoveryPreviewFilters['countries']) === 1 ? 'y' : 'ies' }} ({{ implode(', ', $discoveryPreviewFilters['countries']) }}).</p>
                @endif

                @if (($discoveryPreview ?? collect())->isNotEmpty())
                    <div class="table-wrap">
                        <table class="staged-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Organization</th>
                                    <th>Country</th>
                                    <th>ISO</th>
                                    <th>Current website</th>
                                    <th>Query to send</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($discoveryPreview as $organization)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td><strong>{{ $organization->name }}</strong></td>
                                        <td>{{ $organization->country ?: '-' }}</td>
                                        <td>{{ $organization->country_iso ?: '-' }}</td>
                                        <td>{{ $organization->website_url ?: 'Missing' }}</td>
                                        <td class="summary-cell">{{ $organization->crawler_discovery_query ?: '"' . $organization->name . '" "' . ($organization->country ?: $organization->country_iso) . '" official website' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <form method="post" action="{{ route('sls.organizations.crawlers.discovery', $crawler) }}">
                        @csrf
                        @foreach (($discoveryPreviewFilters['countries'] ?? []) as $country)
                            <input type="hidden" name="countries[]" value="{{ $country }}">
                        @endforeach
                        <input type="hidden" name="organization_limit" value="{{ $discoveryPreview->count() }}">
                        <input type="hidden" name="max_calls" value="{{ $discoveryPreview->count() }}">
                        <input type="hidden" name="results_per_query" value="{{ $discoveryPreviewFilters['results_per_query'] ?? 5 }}">
                        @foreach ($discoveryPreview as $organization)
                            <input type="hidden" name="selected_organization_ids[]" value="{{ $organization->id }}">
                        @endforeach
                        <button class="button" type="submit">Run SerpAPI for these {{ $discoveryPreview->count() }} staged {{ $crawlerProfile['plural'] }}</button>
                    </form>
                @elseif (request()->boolean('preview_serpapi'))
                    <p class="muted">No {{ $crawlerProfile['plural'] }} matched this staging request.</p>
                    @if (($discoveryPreviewCoverage ?? collect())->isNotEmpty())
                        <div class="table-wrap">
                            <table class="staged-table">
                                <thead>
                                    <tr>
                                        <th>Country</th>
                                        <th>Total {{ $crawlerProfile['plural'] }}</th>
                                        <th>Missing main website</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($discoveryPreviewCoverage as $coverage)
                                        @php
                                            $countryName = collect($countryOptions)->firstWhere('iso', $coverage->country_iso)['label'] ?? $coverage->country_iso;
                                        @endphp
                                        <tr>
                                            <td>{{ $countryName }} ({{ $coverage->country_iso }})</td>
                                            <td>{{ $coverage->total }}</td>
                                            <td>{{ $coverage->missing_website }}</td>
                                            <td>{{ (int) $coverage->missing_website === 0 ? 'All matched records already have a main website. Use direct crawl staging instead.' : 'Increase the staging count or check filters.' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif

                <div>
                    <p class="eyebrow">Direct Crawl Staging</p>
                    <h2>Preview {{ $crawlerProfile['plural'] }} to crawl</h2>
                    <p class="muted">This stage does not use SerpAPI. It lists records with saved website or page URLs so you can confirm the exact targets before the crawler visits them.</p>
                </div>
                <form class="crawler-preview-form" method="get" action="{{ route('sls.organizations.crawlers.show', $crawler) }}">
                    <input type="hidden" name="preview_crawl" value="1">
                    <label>Countries
                        <select class="compact-multiselect js-crawler-country-select" name="crawl_preview_countries[]" multiple data-count-mode="total" data-target-input="crawl_preview_organization_limit" data-target-hint="crawl-stage-count-hint" data-max-count="1000">
                            @foreach ($countryOptions as $countryOption)
                                <option value="{{ $countryOption['iso'] }}" data-total="{{ $countryOption['total'] }}" data-missing-domain="{{ $countryOption['missing_domain'] }}" data-not-crawled="{{ $countryOption['not_crawled'] }}" @selected(in_array($countryOption['iso'], $crawlPreviewFilters['countries'] ?? [], true))>
                                    {{ $countryOption['region'] }} - {{ $countryOption['label'] }} ({{ $countryOption['iso'] }}, {{ $countryOption['language'] }}) - {{ $countryOption['not_crawled'] }} not crawled / {{ $countryOption['total'] }} total
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>{{ ucfirst($crawlerProfile['plural']) }} to stage
                        <input name="crawl_preview_organization_limit" type="number" min="1" max="1000" value="{{ $crawlPreviewFilters['organization_limit'] ?? 25 }}">
                        <span id="crawl-stage-count-hint" class="muted crawler-count-hint"></span>
                    </label>
                    <label>Pages/org
                        <input name="crawl_preview_page_limit" type="number" min="1" max="25" value="{{ $crawlPreviewFilters['page_limit'] ?? 6 }}">
                    </label>
                    <button class="button secondary" type="submit">Preview direct crawl list</button>
                </form>
                @if (($crawlPreviewFilters['countries'] ?? []) !== [])
                    <p class="muted">Current direct crawl preview scope: {{ count($crawlPreviewFilters['countries']) }} selected countr{{ count($crawlPreviewFilters['countries']) === 1 ? 'y' : 'ies' }} ({{ implode(', ', $crawlPreviewFilters['countries']) }}).</p>
                @endif
                <p class="muted">Large crawl throttle: direct crawls pause for 2 minutes after every 50 organizations processed.</p>

                @if (($crawlPreview ?? collect())->isNotEmpty())
                    <div class="table-wrap">
                        <table class="staged-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Organization</th>
                                    <th>Country</th>
                                    <th>Website</th>
                                    <th>Known pages</th>
                                    <th>Last crawled</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($crawlPreview as $organization)
                                    @php
                                        $knownPages = collect([
                                            'procurement' => $organization->procurement_page_url,
                                            'leadership' => $organization->leadership_page_url,
                                            'media' => $organization->news_page_url,
                                            'HR' => $organization->hr_page_url,
                                            'IT' => $organization->it_page_url,
                                        ])->filter()->keys()->implode(', ');
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td><strong>{{ $organization->name }}</strong></td>
                                        <td>{{ $organization->country ?: $organization->country_iso ?: '-' }}</td>
                                        <td class="summary-cell">{{ $organization->website_url ?: 'Missing' }}</td>
                                        <td>{{ $knownPages ?: 'main website only' }}</td>
                                        <td>{{ $organization->last_crawled_at ? $organization->last_crawled_at->copy()->timezone('America/Chicago')->format('Y-m-d H:i') : 'Never' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <form method="post" action="{{ route('sls.organizations.crawlers.crawl', $crawler) }}">
                        @csrf
                        @foreach (($crawlPreviewFilters['countries'] ?? []) as $country)
                            <input type="hidden" name="countries[]" value="{{ $country }}">
                        @endforeach
                        <input type="hidden" name="organization_limit" value="{{ $crawlPreview->count() }}">
                        <input type="hidden" name="page_limit" value="{{ $crawlPreviewFilters['page_limit'] ?? 6 }}">
                        @foreach ($crawlPreview as $organization)
                            <input type="hidden" name="selected_organization_ids[]" value="{{ $organization->id }}">
                        @endforeach
                        <button class="button" type="submit">Run direct crawl for these {{ $crawlPreview->count() }} staged {{ $crawlerProfile['plural'] }}</button>
                    </form>
                @elseif (request()->boolean('preview_crawl'))
                    <p class="muted">No {{ $crawlerProfile['plural'] }} matched this direct-crawl staging request. Select countries that have website URLs already captured.</p>
                @endif
            </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Crawler Follow-Up Queue</p>
                <h2>Unreachable sites to retry or review manually</h2>
                <p class="muted">These are open organization tasks created when the crawler could not reach saved website/page URLs.</p>
            </div>
            <div class="table-wrap">
                <table class="staged-table">
                    <thead>
                        <tr>
                            <th>Organization</th>
                            <th>Country</th>
                            <th>Due</th>
                            <th>Issue</th>
                            <th>Profile</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($crawlerFollowUps ?? collect()) as $task)
                            <tr>
                                <td><strong>{{ $task->organization?->name ?: 'Unknown organization' }}</strong></td>
                                <td>{{ $task->organization?->country ?: $task->organization?->country_iso ?: '-' }}</td>
                                <td class="nowrap">{{ $task->due_at ? $task->due_at->copy()->timezone('America/Chicago')->format('Y-m-d H:i') : '-' }}</td>
                                <td class="summary-cell">{{ $task->notes }}</td>
                                <td>
                                    @if ($task->organization)
                                        <a class="button secondary compact" href="{{ route('sls.organizations.show', $task->organization) }}">Open</a>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="muted">No unreachable crawler targets are waiting for follow-up.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Run Log</p>
                <h2>SerpAPI and crawl activity in last 5 days</h2>
                <p class="muted">Refresh this page manually to see new rows. No automatic refresh is used.</p>
            </div>
            <div class="table-wrap">
                <table class="crawler-run-table">
                    <thead>
                        <tr>
                            <th>Started</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Organization</th>
                            <th>Query / URL</th>
                            <th>HTTP</th>
                            <th>Found</th>
                            <th>URLs</th>
                            <th>Contacts</th>
                            <th>Candidate results / updates</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($runs as $run)
                            @php
                                $payload = is_array($run->result_payload) ? $run->result_payload : [];
                                $updatedFields = collect($payload['updated_fields'] ?? [])->map(fn ($field) => str_replace('_page_url', '', str_replace('_url', '', $field)))->implode(', ');
                                $candidateSummary = collect($payload['candidates'] ?? [])
                                    ->take(3)
                                    ->map(fn ($candidate) => ($candidate['kind'] ?? 'candidate') . ': ' . ($candidate['domain'] ?? $candidate['url'] ?? ''))
                                    ->implode(' | ');
                                $checkedSummary = collect($payload['checked_urls'] ?? [])->take(3)->implode(' | ');
                                $batchSummary = $payload['summary'] ?? null;
                            @endphp
                            <tr>
                                <td class="nowrap">{{ $run->started_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') }}</td>
                                <td>{{ str_replace('_', ' ', $run->run_type) }}</td>
                                <td><span class="pill {{ $run->status === 'completed' ? 'good' : (in_array($run->status, ['error', 'failed'], true) ? 'bad' : 'warn') }}">{{ $run->status }}</span></td>
                                <td>{{ $run->organization?->name ?: 'Crawler level' }}</td>
                                <td class="summary-cell">{{ $run->query_text ?: $run->url_checked ?: $run->request_url }}</td>
                                <td>{{ $run->http_status ?: '-' }}</td>
                                <td>{{ $run->items_found }}</td>
                                <td>{{ $run->urls_updated }}</td>
                                <td>{{ $run->contacts_found }}</td>
                                <td class="summary-cell">
                                    @if ($updatedFields)
                                        <strong>Updated:</strong> {{ $updatedFields }}<br>
                                    @endif
                                    {{ $batchSummary ?: $candidateSummary ?: $checkedSummary ?: '-' }}
                                </td>
                                <td class="summary-cell">{{ $run->error_message ?: 'None' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="muted">No crawler run records were logged in the last 5 days.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Last 5 Days</p>
                <h2>Crawl log entries</h2>
            </div>
            <div class="table-wrap">
                <table class="crawler-log-table">
                    <thead>
                        <tr>
                            <th>Last crawl</th>
                            <th class="name-col">Organization</th>
                            <th>Country</th>
                            <th class="url-col">Website</th>
                            <th class="url-col">Proc.</th>
                            <th class="url-col">Leader</th>
                            <th class="url-col">HR</th>
                            <th class="url-col">IT</th>
                            <th class="url-col">Press</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentCrawls as $organization)
                            <tr>
                                <td class="nowrap">{{ $organization->last_crawled_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') }}</td>
                                <td><strong><a href="{{ route('sls.organizations.show', $organization) }}">{{ $organization->name }}</a></strong></td>
                                <td>{{ $organization->country_iso ?: $organization->country ?: 'Unknown' }}</td>
                                <td>{{ $organization->website_url ? 'Yes' : 'No' }}</td>
                                <td>{{ $organization->procurement_page_url ? 'Yes' : 'No' }}</td>
                                <td>{{ $organization->leadership_page_url ? 'Yes' : 'No' }}</td>
                                <td>{{ $organization->hr_page_url ? 'Yes' : 'No' }}</td>
                                <td>{{ $organization->it_page_url ? 'Yes' : 'No' }}</td>
                                <td>{{ $organization->news_page_url ? 'Yes' : 'No' }}</td>
                                <td>{{ $organization->last_error ?: 'None' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="muted">No crawl log entries were recorded for this crawler in the last 5 days.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
