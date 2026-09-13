@extends('sls.layouts.app')

@section('title', 'Country Intelligence Review')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'Tenders and Research Status')

@section('topbar_actions')
    <a class="button" href="{{ route('sls.intelligence.stories.create') }}">Add story</a>
    <a class="button secondary" href="{{ route('sls.intelligence.world', ['focus' => $focus === 'all' ? 'social_security' : $focus, 'region' => $region === 'all' ? null : $region]) }}">Map</a>
    <a class="button secondary" href="#country-search">Country search</a>
    <a class="button secondary" href="{{ route('sls.intelligence.contacts') }}">Contact directory</a>
    <a class="button secondary" href="{{ route('sls.intelligence.sources', ['region' => $region]) }}">Source coverage</a>
    <a class="button secondary" href="{{ route('sls.intelligence.coverage', ['focus' => $focus, 'region' => $region]) }}">Agent coverage</a>
    <a class="button secondary" href="{{ route('sls.intelligence.dropped', ['focus' => $focus === 'all' ? null : $focus]) }}">Dropped items</a>
@endsection

@push('head')
    <style>
        .review-summary { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px; }
        .country-search-grid { display:grid; grid-template-columns:minmax(260px, 1fr) 12rem 12rem auto; gap:10px; align-items:end; }
        .country-search-summary { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:10px; }
        .country-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px; }
        .country-card { border:1px solid var(--border-subtle); border-radius:var(--radius-card); padding:12px; background:var(--bg-primary); }
        .favorite-form { display:grid; gap:5px; }
        textarea.favorite-note { min-height:42px; resize:vertical; }
        .favorite-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }        label.field { display:grid; gap:4px; color:var(--text-secondary); font-weight:700; }
        .stat strong { display:block; font-family:"Sora", sans-serif; font-size:1.8rem; line-height:1; margin-bottom:4px; }
        .filters { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
        .review-table { min-width:1440px; table-layout:fixed; }
        .serial-col { width:5rem; }
        .country-col { width:13.5rem; }
        .english-title-col { width:38rem; }
        .review-actions-col { width:15rem; }
        .opportunity-col { width:7.5rem; }
        .source-col { width:15rem; }
        .evidence-col { width:8.5rem; }
        .date-col { width:7rem; }
        .retrieved-col { width:9rem; }
        .score-col { width:5rem; }
        .status-col { width:6rem; }
        .award-col { width:10rem; }
        .title-cell { overflow-wrap:anywhere; }
        .country-action-stack { display:grid; gap:7px; align-items:start; }
        .country-action-stack .pill { justify-self:start; white-space:normal; line-height:1.15; border-radius:8px; }
        .summary-cell { max-width:520px; color:var(--text-secondary); }
        .translation-pending { color:var(--accent-warning); font-weight:800; }
        .external-source { display:grid; gap:3px; min-width:110px; }
        .external-source small { display:block; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text-secondary); }
        .copy-row { display:grid; grid-template-columns:minmax(0, 1fr) 5.5rem; gap:8px; align-items:start; }
        .serial-copy-row { display:grid; gap:5px; align-items:start; }
        .copy-value { min-width:0; overflow-wrap:anywhere; }
        .title-meta { display:flex; flex-wrap:wrap; gap:4px 8px; margin-bottom:4px; color:var(--text-secondary); font-size:11px; font-weight:800; line-height:1.25; }
        .title-meta span { white-space:nowrap; }
        .serial-copy-row .copy-value { white-space:nowrap; overflow-wrap:normal; font-family:"JetBrains Mono", ui-monospace, monospace; }
        .serial-copy-row .copy-button { justify-self:start; }
        .row-action-stack { display:grid; gap:4px; justify-items:start; align-content:start; min-width:5.5rem; }
        .action-link, button.action-link { appearance:none; border:0; background:transparent; padding:0; margin:0; color:var(--accent-primary); font:inherit; font-size:12px; font-weight:800; line-height:1.2; cursor:pointer; text-decoration:none; }
        .action-link:hover, button.action-link:hover { text-decoration:underline; }
        .copy-button { flex:0 0 auto; }
        .copy-button.copied { border-color:var(--accent-success); color:var(--accent-success); }
        .title-link { color:var(--text-primary); text-decoration:none; }
        .title-link:hover { color:var(--accent-primary); text-decoration:underline; }
        .pagination-wrap { display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; margin-top:12px; }
        .pager-links { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
        .pager-links a,
        .pager-links span { display:inline-flex; align-items:center; justify-content:center; min-width:2rem; height:2rem; border:1px solid var(--border-subtle); border-radius:6px; padding:0 8px; font-size:13px; font-weight:800; text-decoration:none; }
        .pager-links a { color:var(--accent-primary); background:var(--bg-primary); }
        .pager-links .active { color:#fff; background:var(--accent-primary); border-color:var(--accent-primary); }
        .pager-links .disabled { color:var(--text-secondary); background:var(--bg-muted); }
        .inline-update-form { display:flex; align-items:center; gap:6px; min-width:0; }
        .inline-update-form select { min-width:0; padding:5px 7px; font-size:12px; }
        .inline-update-form button { flex:0 0 auto; padding:5px 8px; font-size:12px; }
        .country-fix-form select { flex:1 1 auto; min-width:8.5rem; width:100%; }
        .opportunity-map-form { display:grid; gap:6px; align-items:start; }
        .opportunity-map-form select { width:100%; }
        .opportunity-map-form button { justify-self:start; width:100%; }
        .drop-form { display:grid; grid-template-columns:9rem auto; gap:6px; align-items:center; min-width:0; justify-content:start; }
        .drop-form select,
        .drop-form input { min-width:0; width:9rem; padding:5px 7px; font-size:12px; }
        .drop-form select { grid-column:1; }
        .drop-button { min-width:0; border-color:#dc2626; color:#dc2626; }
        .favorite-form { display:grid; grid-template-columns:9rem auto; gap:6px; align-items:center; min-width:0; justify-content:start; margin-top:5px; }
        .favorite-form input { min-width:0; width:9rem; padding:5px 7px; font-size:12px; }
        .journalist-actions { display: contents; }
        .mini-link { display: inline-flex; border-radius: 999px; padding: .2rem .45rem; background: #eef4ff; color: var(--accent); font-size: .74rem; font-weight: 800; text-decoration: none; }
        @media (max-width:980px) {
            .review-summary, .country-search-grid, .country-search-summary { grid-template-columns:1fr; }
            .review-table { min-width:900px; }
        }
    </style>
@endpush

@section('content')
    @php($austinTz = 'America/Chicago')

    <div class="stack">
        <section class="review-summary">
            <div class="panel stat">
                <strong>{{ $totalMatchingUpdates }}</strong>
                <span class="muted">matching captured items</span>
            </div>
            <div class="panel stat">
                <strong>{{ $researchedCountries }}</strong>
                <span class="muted">countries with a recorded agent run</span>
            </div>
            <div class="panel stat">
                <strong>{{ $totalCountries }}</strong>
                <span class="muted">countries in selected scope</span>
            </div>
        </section>

        <section class="panel">
            <p class="eyebrow">Filters</p>
            <h2>Review by product focus and region</h2>
            <div class="filters">
                <a class="button {{ $focus === 'all' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.review', ['focus' => 'all', 'region' => $region]) }}">All items</a>
                <?php foreach ($focuses as $focusKey => $focusConfig): ?>
                    <a class="button {{ $focus === $focusKey ? '' : 'secondary' }}" href="{{ route('sls.intelligence.review', ['focus' => $focusKey, 'region' => $region]) }}">{{ $focusConfig['label'] }}</a>
                <?php endforeach; ?>
                <a class="button {{ $region === 'all' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.review', ['focus' => $focus, 'region' => 'all']) }}">All regions</a>
                <a class="button {{ $region === 'africa' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.review', ['focus' => $focus, 'region' => 'africa']) }}">Africa</a>
                <a class="button {{ $region === 'asia' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.review', ['focus' => $focus, 'region' => 'asia']) }}">Asia</a>
                <a class="button {{ $region === 'caribbean' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.review', ['focus' => $focus, 'region' => 'caribbean']) }}">Caribbean</a>
                <a class="button {{ $region === 'latin_america' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.review', ['focus' => $focus, 'region' => 'latin_america']) }}">Latin America</a>
                <a class="button {{ $region === 'north_america' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.review', ['focus' => $focus, 'region' => 'north_america']) }}">North America</a>
                <a class="button {{ $region === 'europe' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.review', ['focus' => $focus, 'region' => 'europe']) }}">Europe</a>
            </div>
        </section>

        <section id="country-search" class="panel stack">
            <div>
                <p class="eyebrow">Country Search</p>
                <h2>Search stored intelligence by country</h2>
            </div>
            <form class="country-search-grid" method="get" action="{{ route('sls.intelligence.review') }}#country-search">
                <input type="hidden" name="focus" value="{{ $focus }}">
                <input type="hidden" name="region" value="{{ $region }}">
                <input type="hidden" name="published" value="{{ $publishedFilter }}">
                <input type="hidden" name="retrieved" value="{{ $retrievedFilter }}">
                <input type="hidden" name="type" value="{{ $typeFilter }}">
                <input type="hidden" name="per_page" value="{{ $displayLimit }}">
                <label class="field">
                    Country name or ISO code
                    <input name="country_q" value="{{ $countrySearchQuery }}" placeholder="Example: Trinidad, Ghana, Brazil, TT">
                </label>
                <label class="field">
                    Type
                    <select name="country_type">
                        <option value="all" @selected($countrySearchType === 'all')>All items</option>
                        <option value="tenders" @selected($countrySearchType === 'tenders')>Tenders only</option>
                        <option value="news" @selected($countrySearchType === 'news')>News only</option>
                    </select>
                </label>
                <label class="field">
                    Status
                    <select name="country_status">
                        <option value="all" @selected($countrySearchStatus === 'all')>Active items</option>
                        <option value="unreviewed" @selected($countrySearchStatus === 'unreviewed')>Unreviewed</option>
                        <option value="approved" @selected($countrySearchStatus === 'approved')>Approved</option>
                        <option value="rejected" @selected($countrySearchStatus === 'rejected')>Dropped</option>
                    </select>
                </label>
                <button type="submit">Search</button>
            </form>

            @if ($countrySearchQuery !== '')
                <section class="country-search-summary">
                    <div class="panel stat"><strong>{{ $countrySearchSummary['total'] }}</strong><span class="muted">stored items shown</span></div>
                    <div class="panel stat"><strong>{{ $countrySearchSummary['tenders'] }}</strong><span class="muted">tenders / procurement</span></div>
                    <div class="panel stat"><strong>{{ $countrySearchSummary['news'] }}</strong><span class="muted">news / intelligence</span></div>
                    <div class="panel stat"><strong>{{ $countrySearchSummary['rejected'] }}</strong><span class="muted">dropped but still stored</span></div>
                </section>

                <div>
                    <p class="eyebrow">Matched Countries</p>
                    <h2>Countries matching "{{ $countrySearchQuery }}"</h2>
                </div>
                @if ($countrySearchCountries->isNotEmpty())
                    <div class="country-grid">
                        @foreach ($countrySearchCountries as $country)
                            <div class="country-card">
                                <h3>{{ $country->name }} <span class="muted">({{ $country->iso_code }})</span></h3>
                                <p class="muted">{{ $country->region }}{{ $country->social_security_administration_name ? ' | ' . $country->social_security_administration_name : '' }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="muted">No country matched "{{ $countrySearchQuery }}". Try the ISO code or a shorter country name.</p>
                @endif
                <p class="muted">This country search is filtering the Review Desk table below, so the same review, source, journalist, favorite, and mapping actions remain available on each row.</p>

                <div>
                    <p class="eyebrow">Monitor Runs</p>
                    <h2>Recent checks for matched countries</h2>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
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
                            @forelse ($countrySearchRuns as $run)
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
            @endif
        </section>
        <section class="panel stack">
            <div>
                <p class="eyebrow">Captured Items</p>
                <h2>{{ $countrySearchQuery !== '' ? 'Review Desk items for country search' : 'All tenders, RFPs, and intelligence items found' }}</h2>
                <p class="muted">
                    Showing {{ $updates->firstItem() ?? 0 }}-{{ $updates->lastItem() ?? 0 }} of {{ $totalMatchingUpdates }} matching items, ordered by retrieval date first.
                    Use filters or Country Search to narrow the list further.
                </p>
                <?php if (in_array($publishedFilter, ['last30', 'last60', 'last120'], true) || in_array($typeFilter, ['tenders', 'news'], true)): ?>
                    <p class="muted">
                        Showing
                        <?php if ($typeFilter === 'tenders'): ?>
                            tenders
                        <?php elseif ($typeFilter === 'news'): ?>
                            news stories
                        <?php else: ?>
                            intelligence items
                        <?php endif; ?>
                        <?php if (in_array($publishedFilter, ['last30', 'last60', 'last120'], true)): ?>
                            published in the last {{ $publishedFilter === 'last120' ? 120 : ($publishedFilter === 'last60' ? 60 : 30) }} days
                        <?php endif; ?>
                        .
                    </p>
                <?php endif; ?>
            </div>
            <div class="table-wrap">
                <table class="data-table review-table">
                    <thead>
                        <tr>
                            <th class="serial-col">Serial</th>
                            <th class="country-col">Country</th>
                            <th class="english-title-col">English title</th>
                            <th class="review-actions-col">Review</th>
                            <th class="opportunity-col">Opportunity</th>
                            <th class="source-col">Source</th>
                            <th class="evidence-col">Evidence</th>
                            <th class="date-col">Published</th>
                            <th class="retrieved-col">Retrieved (Austin time)</th>
                            <th class="score-col">Score</th>
                            <th class="status-col">Status</th>
                            <th class="award-col">Award</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($updates->count() > 0): ?>
                        <?php foreach ($updates as $update): ?>
                            <?php
                                $serialNumber = str_pad((string) $update->id, 5, '0', STR_PAD_LEFT);
                                $englishTitle = trim((string) $update->title_english);
                                $originalTitle = trim((string) ($update->title_original ?: $update->title));
                                $englishTitleIsUsable = \App\Support\TitleLanguage::isUsableEnglishTitle($englishTitle, $originalTitle);
                                $displayEnglishTitle = $englishTitleIsUsable
                                    ? $englishTitle
                                    : ($originalTitle !== '' && ! \App\Support\TitleLanguage::looksNonEnglish($originalTitle) ? $originalTitle : 'Translation pending');
                                $needsTranslation = $displayEnglishTitle === 'Translation pending';
                                $summaryText = (string) $update->summary;
                                $evidenceLabel = str_contains($summaryText, '[Aggregator lead - verify at official source]')
                                    ? 'Aggregator lead'
                                    : (str_contains($summaryText, '[Official tender source]') ? 'Official source' : 'Needs verification');
                                $evidenceClass = $evidenceLabel === 'Official source' ? 'good' : ($evidenceLabel === 'Aggregator lead' ? 'warn' : 'bad');
                                $productNameForFocus = match ($update->inferred_focus) {
                                    'hrms_tenders' => 'Interact HRMS',
                                    'erms_tenders' => 'Interact ERMS',
                                    'ebpc_tenders' => 'Interact EBPC',
                                    default => 'Interact SSAS',
                                };
                                $defaultOpportunityProduct = ($products ?? collect())->first(fn ($product) => $product->name === $productNameForFocus)
                                    ?: ($products ?? collect())->first(fn ($product) => $product->name === 'Interact SSAS')
                                    ?: ($products ?? collect())->first();
                            ?>
                            <tr>
                                <td>
                                    <div class="serial-copy-row">
                                        <span class="copy-value">{{ $serialNumber }}</span>
                                        <button class="secondary tiny copy-button" type="button" title="Copy serial number" data-copy-text="{{ $serialNumber }}">Copy</button>
                                    </div>
                                </td>
                                <td>
                                    <div class="country-action-stack">
                                        <form class="inline-update-form country-fix-form" method="post" action="{{ route('sls.intelligence.updates.country', $update) }}">
                                            @csrf
                                            <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                            <select name="country_id" aria-label="Update country for {{ $serialNumber }}">
                                                @foreach ($reviewCountries as $reviewCountry)
                                                    <option value="{{ $reviewCountry->id }}" @selected((int) $reviewCountry->id === (int) $update->country_id)>
                                                        {{ $reviewCountry->name }}{{ $reviewCountry->iso_code ? ' (' . $reviewCountry->iso_code . ')' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button class="secondary tiny" type="submit">Update</button>
                                        </form>
                                        <span class="pill">{{ $update->inferred_focus && isset($focuses[$update->inferred_focus]) ? $focuses[$update->inferred_focus]['label'] : 'Unclassified' }}</span>
                                    </div>
                                </td>
                                <td class="title-cell">
                                    <div class="copy-row">
                                        <span class="copy-value">
                                            <span class="title-meta">
                                                <span>Published {{ $update->publication_date?->toDateString() ?: 'not captured' }}</span>
                                                <span>
                                                    Retrieved
                                                    @if ($update->retrieved_at)
                                                        {{ $update->retrieved_at->copy()->timezone($austinTz)->format('Y-m-d H:i') }} Austin
                                                    @else
                                                        not captured
                                                    @endif
                                                </span>
                                            </span>
                                            <?php if ($needsTranslation): ?>
                                                <span class="translation-pending">Translation pending</span>
                                            <?php elseif ($update->source_url): ?>
                                                <a class="title-link" href="{{ route('sls.intelligence.updates.sourcePage', $update) }}" target="_blank" rel="noreferrer">{{ $displayEnglishTitle }}</a>
                                            <?php else: ?>
                                                {{ $displayEnglishTitle }}
                                            <?php endif; ?>
                                        </span>
                                        <span class="row-action-stack">
                                            <button class="action-link copy-button" type="button" title="Copy English title" data-copy-text="{{ $displayEnglishTitle }}">Copy</button>
                                            @if ($update->journalistArticles->isNotEmpty())
                                                @foreach ($update->journalistArticles as $journalistArticle)
                                                    @if ($journalistArticle->journalist)
                                                        <a class="action-link" href="{{ route('sls.intelligence.journalists.show', $journalistArticle->journalist) }}">{{ $journalistArticle->journalist->name }}</a>
                                                    @endif
                                                @endforeach
                                            @else
                                                <form class="journalist-capture-form" method="post" action="{{ route('sls.intelligence.journalists.capture', $update) }}">
                                                    @csrf
                                                    <button class="action-link" type="submit">Add Journo</button>
                                                </form>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if ($update->review_status === 'rejected')
                                        <form class="drop-form" method="post" action="{{ route('sls.intelligence.updates.restore', $update) }}">
                                            @csrf
                                            <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                            <span class="pill bad">Dropped</span>
                                            <button class="action-link" type="submit">Restore</button>
                                        </form>
                                    @else
                                        <form class="drop-form" method="post" action="{{ route('sls.intelligence.updates.drop', $update) }}">
                                            @csrf
                                            <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                            <select name="reason_code" aria-label="Drop reason for {{ $serialNumber }}">
                                                <option value="not_relevant">Not relevant</option>
                                                <option value="wrong_product">Wrong product</option>
                                                <option value="wrong_country">Wrong country</option>
                                                <option value="duplicate">Duplicate</option>
                                                <option value="old_or_awarded">Old or awarded</option>
                                                <option value="spam_or_scrape">Scrape noise</option>
                                                <option value="other">Other</option>
                                            </select>
                                            <button class="secondary tiny drop-button" type="submit">Drop</button>
                                        </form>
                                    @endif
                                    @php($favoriteFormId = 'review-favorite-form-' . $update->id)
                                    <form id="{{ $favoriteFormId }}" class="favorite-form compact-favorite-form" method="post" action="{{ route('sls.intelligence.updates.favorite', $update) }}">
                                        @csrf
                                        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                        <input type="text" name="favorite_note" value="{{ old('favorite_note', $update->favorite_note) }}" maxlength="5000" placeholder="Favorite note" aria-label="Favorite note for {{ $serialNumber }}">
                                        <button class="action-link" type="submit">{{ $update->is_favorite ? 'Update fav' : 'Favorite' }}</button>
                                    </form>
                                </td>
                                <td>
                                    <form class="inline-update-form opportunity-map-form" method="post" action="{{ route('sls.intelligence.opportunities.store', $update) }}">
                                        @csrf
                                        <select name="product_id" aria-label="Map {{ $serialNumber }} to product">
                                            @foreach ($products as $product)
                                                <option value="{{ $product->id }}" @selected($defaultOpportunityProduct && (int) $product->id === (int) $defaultOpportunityProduct->id)>
                                                    {{ Str::after($product->name, 'Interact ') ?: $product->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button class="secondary tiny" type="submit">Map</button>
                                    </form>
                                </td>
                                <td>{{ $update->source_name ?: 'Unknown source' }}</td>
                                <td><span class="pill {{ $evidenceClass }}">{{ $evidenceLabel }}</span></td>
                                <td>{{ $update->publication_date?->toDateString() ?: 'Not captured' }}</td>
                                <td>{{ $update->retrieved_at?->copy()->timezone($austinTz)->format('Y-m-d H:i') ?: 'Not captured' }}</td>
                                <td>{{ $update->relevance_score }}</td>
                                <td><span class="pill warn">{{ $update->review_status }}</span></td>
                                <td>
                                    <?php if ($update->award_status === 'awarded'): ?>
                                        <span class="pill good">Awarded</span>
                                        <?php if ($update->award_url): ?>
                                            <p style="margin-top:4px;"><a href="{{ $update->award_url }}" target="_blank" rel="noreferrer">Award record</a></p>
                                        <?php endif; ?>
                                    <?php elseif ($update->award_status === 'not_found'): ?>
                                        <span class="pill">No award found</span>
                                    <?php elseif ($update->award_checked_at): ?>
                                        <span class="pill warn">Unknown</span>
                                    <?php else: ?>
                                        <span class="muted">Not checked</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="muted">No captured items match this view yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">
                <p class="muted">Page {{ $updates->currentPage() }} of {{ $updates->lastPage() }} | {{ $displayLimit }} items per page</p>
                <nav class="pager-links" aria-label="Review desk pagination">
                    @if ($updates->onFirstPage())
                        <span class="disabled">Previous</span>
                    @else
                        <a href="{{ $updates->previousPageUrl() }}">Previous</a>
                    @endif

                    @foreach ($updates->getUrlRange(1, $updates->lastPage()) as $pageNumber => $url)
                        @if ($pageNumber === $updates->currentPage())
                            <span class="active">{{ $pageNumber }}</span>
                        @else
                            <a href="{{ $url }}">{{ $pageNumber }}</a>
                        @endif
                    @endforeach

                    @if ($updates->hasMorePages())
                        <a href="{{ $updates->nextPageUrl() }}">Next</a>
                    @else
                        <span class="disabled">Next</span>
                    @endif
                </nav>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-copy-text]');
            if (!button) {
                return;
            }

            const text = button.getAttribute('data-copy-text') || '';
            const originalLabel = button.textContent;

            try {
                await navigator.clipboard.writeText(text);
                button.classList.add('copied');
                button.textContent = 'Copied';
                window.setTimeout(() => {
                    button.classList.remove('copied');
                    button.textContent = originalLabel;
                }, 1200);
            } catch (error) {
                const fallback = document.createElement('textarea');
                fallback.value = text;
                document.body.appendChild(fallback);
                fallback.select();
                document.execCommand('copy');
                fallback.remove();
            }
        });
    </script>
@endpush


