@extends('sls.layouts.app')

@section('title', 'Market Research Sources')
@section('eyebrow', 'Setup')
@section('page_title', 'Market Research Sources')

@push('head')
    <style>
        .source-maintenance-page { display:grid; gap:14px; }
        .source-maintenance-head { display:flex; justify-content:space-between; align-items:flex-end; gap:12px; }
        .tracked-country-filters { display:flex; flex-wrap:wrap; gap:8px; align-items:end; margin-top:10px; }
        .tracked-country-filters label { display:grid; gap:4px; color:var(--text-secondary); font-size:.7rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
        .tracked-country-filters input,
        .tracked-country-filters select { min-height:34px; border:1px solid var(--border-subtle); border-radius:7px; background:var(--bg-secondary); color:var(--text-primary); font:inherit; font-size:.84rem; padding:4px 8px; }
        .tracked-country-filters input { width:220px; }
        .tracked-country-filters select { width:170px; }
        .tracked-country-filters label.checkbox-field { display:flex; flex-direction:row; align-items:center; gap:7px; min-height:34px; padding:0 4px; text-transform:none; letter-spacing:0; font-size:.84rem; color:var(--text-primary); }
        .tracked-country-filters label.checkbox-field input { width:auto; min-height:0; }
        .source-metric-grid { display:grid; grid-template-columns:repeat(6, minmax(130px, 1fr)); gap:10px; }
        .source-metric-card { border:1px solid var(--border-subtle); border-radius:8px; padding:12px; background:var(--bg-primary); }
        .source-metric-card strong { display:block; font-size:1.45rem; line-height:1.1; }
        .source-metric-card span { color:var(--text-secondary); font-size:.82rem; }
        .source-trend { display:grid; gap:8px; margin-top:10px; }
        .source-trend-bars { display:flex; align-items:end; gap:5px; min-height:96px; padding:10px 8px 0; border:1px solid var(--border-subtle); border-radius:8px; background:var(--bg-secondary); overflow-x:auto; }
        .source-trend-bar { flex:0 0 13px; min-height:2px; border-radius:4px 4px 0 0; background:var(--accent-primary); opacity:.88; }
        .source-trend-caption { display:flex; justify-content:space-between; gap:12px; color:var(--text-secondary); font-size:.78rem; }
        .maintenance-table { min-width:1180px; table-layout:fixed; }
        .maintenance-table td,
        .maintenance-table th { padding:7px 9px; vertical-align:top; }
        .country-col { width:175px; }
        .country-meta { display:block; margin-top:2px; color:var(--text-secondary); font-size:.78rem; }
        .source-col { width:44%; }
        .url-col { width:44%; }
        .source-name-list { display:grid; gap:4px; }
        .source-slot-grid-cell { padding:0; }
        .source-slot-grid { display:grid; }
        .source-slot-row { display:grid; grid-template-columns:minmax(0, 1.05fr) minmax(0, .95fr); gap:20px; padding:7px 9px; border-bottom:1px solid var(--border-subtle); }
        .source-slot-row:last-child { border-bottom:0; }
        .source-row { display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:8px; align-items:start; }
        .source-title { font-weight:800; overflow-wrap:anywhere; }
        .source-title.missing { color:var(--accent-danger); }
        .source-slot { display:block; color:var(--text-secondary); font-size:.72rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
        .source-description { display:block; color:var(--text-secondary); font-size:.78rem; margin-top:2px; }
        .country-progress { display:grid; gap:4px; margin-top:8px; color:var(--text-secondary); font-size:.78rem; }
        .unlinked-source-list { display:grid; gap:3px; margin-top:10px; padding-top:8px; border-top:1px solid var(--border-subtle); color:var(--text-secondary); font-size:.78rem; }
        .unlinked-source-list strong { color:var(--text-primary); }
        .source-urls { display:grid; align-content:start; gap:3px; color:var(--text-secondary); font-size:.78rem; overflow-wrap:anywhere; }
        .source-urls a { color:var(--accent-primary); font-weight:700; text-decoration:none; }
        .source-urls a:hover { text-decoration:underline; }
        .source-url-edit { width:34px; height:30px; min-height:30px; padding:0; border-radius:6px; font-size:.76rem; }
        .source-url-modal[hidden] { display:none; }
        .source-url-modal { position:fixed; inset:0; z-index:10000; display:flex; align-items:center; justify-content:center; padding:24px; background:rgba(15, 23, 42, .46); overflow:auto; }
        .source-url-dialog { position:relative; z-index:10001; width:min(560px, 100%); max-height:calc(100vh - 48px); overflow:auto; border-radius:8px; border:1px solid var(--border-subtle); background:var(--bg-primary); box-shadow:0 24px 70px rgba(15, 23, 42, .28); }
        .source-url-dialog header { display:flex; justify-content:space-between; gap:12px; padding:12px 14px; border-bottom:1px solid var(--border-subtle); }
        .source-url-dialog h3 { margin:0; font-size:1rem; }
        .source-url-dialog form { display:grid; gap:9px; padding:14px; }
        .source-url-field { display:grid; gap:5px; }
        .source-url-field .muted { font-size:.74rem; line-height:1.35; }
        .source-url-field-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; color:var(--text-secondary); font-size:.78rem; font-weight:800; text-transform:uppercase; }
        .source-url-dialog label.checkbox-field { display:flex; flex-direction:row; align-items:center; gap:7px; text-transform:none; letter-spacing:0; color:var(--text-primary); font-size:.78rem; font-weight:800; white-space:nowrap; }
        .source-url-dialog label.checkbox-field input { width:auto; }
        .source-url-dialog input,
        .source-url-dialog select { width:100%; box-sizing:border-box; }
        .source-url-dialog .form-status { min-height:1.2em; color:var(--accent-danger); font-size:.82rem; }
        .source-url-reset { border-color:var(--accent-danger); color:var(--accent-danger); background:transparent; }
        .source-url-reset:hover { background:rgba(220, 38, 38, .08); }
    </style>
@endpush

@section('content')
    @php
        $sourceMetrics = $sourceMetrics ?? [];
        $sourceMetricHistory = collect($sourceMetricHistory ?? []);
        $latestMetric = $sourceMetricHistory->last();
        $firstMetric = $sourceMetricHistory->first();
        $completionDelta = $latestMetric && $firstMetric
            ? round((float) $latestMetric->completion_percent - (float) $firstMetric->completion_percent, 2)
            : 0;
    @endphp
    <div class="source-maintenance-page">
        <section class="panel stack">
            <div class="source-maintenance-head">
                <div>
                    <p class="eyebrow">Tracked Countries And Organizations</p>
                    <h2>Maintain source organization URLs</h2>
                    <p class="muted">This page is limited to the official source organizations used for social security intelligence. Each country has the same required source slots; researchers fill missing organization names and the General, Press, and Procurement URLs.</p>
                </div>
            </div>

            <div class="source-metric-grid" aria-label="Source maintenance counters">
                <div class="source-metric-card">
                    <strong>{{ number_format((int) ($sourceMetrics['url_count'] ?? 0)) }}</strong>
                    <span>URLs captured</span>
                </div>
                <div class="source-metric-card">
                    <strong>{{ number_format((int) ($sourceMetrics['confirmed_nonexistent_url_count'] ?? 0)) }}</strong>
                    <span>URLs confirmed non-existent</span>
                </div>
                <div class="source-metric-card">
                    <strong>{{ number_format((int) ($sourceMetrics['missing_organization_count'] ?? 0)) }}</strong>
                    <span>missing organization names</span>
                </div>
                <div class="source-metric-card">
                    <strong>{{ number_format((int) ($sourceMetrics['missing_url_count'] ?? 0)) }}</strong>
                    <span>missing URLs across General, Press, and Procurement</span>
                </div>
                <div class="source-metric-card">
                    <strong>{{ number_format((int) ($sourceMetrics['confirmed_nonexistent_organization_count'] ?? 0)) }}</strong>
                    <span>organizations confirmed non-existent</span>
                </div>
                <div class="source-metric-card">
                    <strong>{{ number_format((float) ($sourceMetrics['completion_percent'] ?? 0), 1) }}%</strong>
                    <span>complete toward {{ number_format((int) ($sourceMetrics['source_slot_count'] ?? 0)) }} organization slots</span>
                </div>
            </div>

            <div class="source-trend">
                <div class="source-trend-caption">
                    <span>Daily progress trend{{ $sourceMetricHistory->isNotEmpty() ? ' since ' . \Carbon\Carbon::parse($sourceMetricHistory->first()->snapshot_date)->format('M j') : '' }}</span>
                    <span>{{ $completionDelta >= 0 ? '+' : '' }}{{ number_format($completionDelta, 1) }} percentage points</span>
                </div>
                <div class="source-trend-bars" aria-label="Daily completion trend">
                    @forelse ($sourceMetricHistory as $point)
                        <span
                            class="source-trend-bar"
                            style="height:{{ max(2, min(100, (float) $point->completion_percent)) }}%;"
                            title="{{ \Carbon\Carbon::parse($point->snapshot_date)->format('Y-m-d') }}: {{ number_format((float) $point->completion_percent, 1) }}% complete, {{ number_format((int) $point->missing_organization_count) }} missing names, {{ number_format((int) $point->missing_url_count) }} missing URLs, {{ number_format((int) ($point->confirmed_nonexistent_organization_count ?? 0)) }} confirmed non-existent organizations, {{ number_format((int) ($point->confirmed_nonexistent_url_count ?? 0)) }} confirmed non-existent URLs"
                        ></span>
                    @empty
                        <span class="muted">Progress tracking starts after the daily snapshot runs.</span>
                    @endforelse
                </div>
            </div>

            <div class="tracked-country-filters">
                <label>
                    Country
                    <input type="search" id="tracked-country-filter" placeholder="Type country or organization">
                </label>
                <label>
                    Region
                    <select id="tracked-region-filter">
                        <option value="">All regions</option>
                        @foreach ($trackedRegions as $region)
                            <option value="{{ $region }}" @selected($region === 'Africa')>{{ $region }}</option>
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
                <label class="checkbox-field">
                    <input type="checkbox" id="tracked-missing-filter">
                    Missing only
                </label>
            </div>

            <div class="table-wrap">
                <table class="data-table maintenance-table">
                    <thead>
                        <tr>
                            <th class="country-col">Country</th>
                            <th class="source-col">Source organization slot</th>
                            <th class="url-col">URLs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($countries as $country)
                            <tr
                                data-tracked-country-row
                                data-search-text="{{ strtolower($country->name . ' ' . ($country->social_security_administration_links ?? collect())->map(fn ($source) => trim(($source['slot_label'] ?? '') . ' ' . ($source['name'] ?? '')))->implode(' ') . ' ' . ($country->unlinked_source_organization_names ?? collect())->implode(' ')) }}"
                                data-region="{{ $country->region }}"
                                data-language="{{ strtoupper($country->default_language_code ?? 'n/a') }}"
                                data-missing-count="{{ $country->missing_source_count ?? 0 }}"
                            >
                                <td class="country-col">
                                    <strong>{{ $country->name }}</strong>
                                    <span class="country-meta">{{ strtoupper($country->iso_code ?? 'n/a') }} / {{ $country->region }}</span>
                                    <span class="country-progress">
                                        <span>{{ $country->complete_source_count ?? 0 }}/{{ $country->total_source_count ?? 0 }} source slots identified</span>
                                        @if (($country->missing_source_count ?? 0) > 0)
                                            <span class="pill bad">{{ $country->missing_source_count }} missing</span>
                                        @else
                                            <span class="pill good">Complete</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="source-slot-grid-cell" colspan="2">
                                    @if (($country->social_security_administration_links ?? collect())->isNotEmpty())
                                        <div class="source-slot-grid">
                                            @foreach (($country->social_security_administration_links ?? collect()) as $admin)
                                                <div class="source-slot-row">
                                                    <div class="source-row">
                                                        <span>
                                                            <span class="source-slot">{{ $admin['slot_label'] ?? 'Source organization' }}</span>
                                                            @if (filled($admin['name'] ?? null))
                                                                <span class="source-title">{{ $admin['name'] }}</span>
                                                            @elseif (filled($admin['organization_nonexistent_confirmed_at'] ?? null))
                                                                <span class="source-title">Confirmed non-existent</span>
                                                            @else
                                                                <span class="source-title missing">Missing - add organization name</span>
                                                            @endif
                                                            @if (filled($admin['organization_nonexistent_confirmed_at'] ?? null))
                                                                <span class="source-description">Confirmed {{ \Carbon\Carbon::parse($admin['organization_nonexistent_confirmed_at'])->format('Y-m-d') }}</span>
                                                            @endif
                                                            @if (filled($admin['description'] ?? null))
                                                                <span class="source-description">{{ $admin['description'] }}</span>
                                                            @endif
                                                        </span>
                                                        <button
                                                            class="source-url-edit secondary"
                                                            type="button"
                                                            title="Edit URLs"
                                                            aria-label="Edit URLs for {{ $admin['slot_label'] ?? $admin['name'] }}"
                                                            data-country-iso="{{ strtoupper($country->iso_code ?? '') }}"
                                                            data-country-name="{{ $country->name }}"
                                                            data-region="{{ $country->region }}"
                                                            data-organization-name="{{ $admin['name'] ?? '' }}"
                                                            data-source-category="{{ $admin['source_category'] ?? 'social_security_administration' }}"
                                                            data-source-label="{{ $admin['slot_label'] ?? $admin['name'] }}"
                                                            data-source-suggestions='@json(($country->unlinked_source_organization_names ?? collect())->values())'
                                                            data-product-id="{{ $admin['product_id'] ?? optional($products->first())->id }}"
                                                            data-general-url="{{ $admin['general_url'] ?? '' }}"
                                                            data-press-url="{{ $admin['press_url'] ?? '' }}"
                                                            data-tenders-url="{{ $admin['tenders_url'] ?? '' }}"
                                                            data-organization-nonexistent="{{ filled($admin['organization_nonexistent_confirmed_at'] ?? null) ? '1' : '0' }}"
                                                            data-general-url-nonexistent="{{ filled($admin['general_url_nonexistent_confirmed_at'] ?? null) ? '1' : '0' }}"
                                                            data-press-url-nonexistent="{{ filled($admin['press_url_nonexistent_confirmed_at'] ?? null) ? '1' : '0' }}"
                                                            data-tenders-url-nonexistent="{{ filled($admin['tenders_url_nonexistent_confirmed_at'] ?? null) ? '1' : '0' }}"
                                                        >Edit</button>
                                                    </div>
                                                    <div class="source-urls">
                                                        <strong>{{ $admin['slot_label'] ?? $admin['name'] }}</strong>
                                                        @if (filled($admin['organization_nonexistent_confirmed_at'] ?? null))
                                                            <span>Organization: confirmed non-existent {{ \Carbon\Carbon::parse($admin['organization_nonexistent_confirmed_at'])->format('Y-m-d') }}</span>
                                                        @elseif (blank($admin['name'] ?? null))
                                                            <span>Organization: not identified</span>
                                                        @elseif (($admin['slot_label'] ?? null) && ($admin['name'] ?? null) && $admin['slot_label'] !== $admin['name'])
                                                            <span>Organization: {{ $admin['name'] }}</span>
                                                        @endif
                                                        @if (filled($admin['general_url'] ?? null))
                                                            <span>General: <a href="{{ $admin['general_url'] }}" target="_blank" rel="noreferrer">{{ $admin['general_url'] }}</a></span>
                                                        @elseif (filled($admin['general_url_nonexistent_confirmed_at'] ?? null))
                                                            <span>General: confirmed non-existent {{ \Carbon\Carbon::parse($admin['general_url_nonexistent_confirmed_at'])->format('Y-m-d') }}</span>
                                                        @else
                                                            <span>General: not set</span>
                                                        @endif
                                                        @if (filled($admin['press_url'] ?? null))
                                                            <span>Press: <a href="{{ $admin['press_url'] }}" target="_blank" rel="noreferrer">{{ $admin['press_url'] }}</a></span>
                                                        @elseif (filled($admin['press_url_nonexistent_confirmed_at'] ?? null))
                                                            <span>Press: confirmed non-existent {{ \Carbon\Carbon::parse($admin['press_url_nonexistent_confirmed_at'])->format('Y-m-d') }}</span>
                                                        @endif
                                                        @if (filled($admin['tenders_url'] ?? null))
                                                            <span>Tenders: <a href="{{ $admin['tenders_url'] }}" target="_blank" rel="noreferrer">{{ $admin['tenders_url'] }}</a></span>
                                                        @elseif (filled($admin['tenders_url_nonexistent_confirmed_at'] ?? null))
                                                            <span>Tenders: confirmed non-existent {{ \Carbon\Carbon::parse($admin['tenders_url_nonexistent_confirmed_at'])->format('Y-m-d') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        @if (($country->unlinked_source_organization_names ?? collect())->isNotEmpty())
                                            <div class="unlinked-source-list">
                                                <strong>Existing unassigned organizations</strong>
                                                @foreach (($country->unlinked_source_organization_names ?? collect()) as $name)
                                                    <span>{{ $name }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    @else
                                        <span class="missing-source">Missing official source</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr data-tracked-country-empty hidden>
                            <td colspan="3" class="muted">No tracked countries match these filters.</td>
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
                <input type="hidden" name="source_category">
                <input type="hidden" name="source_label">
                <input type="hidden" name="product_id" value="{{ optional($products->first())->id }}">
                <div class="source-url-field">
                    <div class="source-url-field-heading">
                        <span>Organization name</span>
                        <label class="checkbox-field">
                            <input type="checkbox" name="organization_nonexistent" value="1">
                            Confirmed Non-Existence
                        </label>
                    </div>
                    <input type="text" name="organization_name" list="source-url-suggestions" placeholder="Official organization name" autocomplete="off">
                    <span class="muted">Start typing to reuse an existing unassigned organization for this country, or enter a new official name.</span>
                </div>
                <datalist id="source-url-suggestions"></datalist>
                <div class="source-url-field">
                    <div class="source-url-field-heading">
                        <span>General</span>
                        <label class="checkbox-field">
                            <input type="checkbox" name="general_url_nonexistent" value="1">
                            Confirmed Non-Existence
                        </label>
                    </div>
                    <input type="url" name="general_url" placeholder="https://example.gov">
                </div>
                <div class="source-url-field">
                    <div class="source-url-field-heading">
                        <span>Press</span>
                        <label class="checkbox-field">
                            <input type="checkbox" name="press_url_nonexistent" value="1">
                            Confirmed Non-Existence
                        </label>
                    </div>
                    <input type="url" name="press_url" placeholder="https://example.gov/news">
                </div>
                <div class="source-url-field">
                    <div class="source-url-field-heading">
                        <span>Tenders</span>
                        <label class="checkbox-field">
                            <input type="checkbox" name="tenders_url_nonexistent" value="1">
                            Confirmed Non-Existence
                        </label>
                    </div>
                    <input type="url" name="tenders_url" placeholder="https://example.gov/procurement">
                </div>
                <div class="toolbar" style="justify-content:space-between;">
                    <button class="secondary tiny source-url-reset" type="button" id="source-url-reset">Reset slot</button>
                    <span class="form-status" id="source-url-status"></span>
                    <button class="button tiny" type="submit">Save URLs</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const countryInput = document.getElementById('tracked-country-filter');
            const regionSelect = document.getElementById('tracked-region-filter');
            const languageSelect = document.getElementById('tracked-language-filter');
            const missingOnly = document.getElementById('tracked-missing-filter');
            const rows = Array.from(document.querySelectorAll('[data-tracked-country-row]'));
            const emptyRow = document.querySelector('[data-tracked-country-empty]');

            const applyFilters = () => {
                const term = (countryInput?.value || '').trim().toLowerCase();
                const region = regionSelect?.value || '';
                const language = languageSelect?.value || '';
                const onlyMissing = missingOnly?.checked || false;
                let visible = 0;

                rows.forEach((row) => {
                    const matchesTerm = term === '' || (row.dataset.searchText || '').includes(term);
                    const matchesRegion = region === '' || row.dataset.region === region;
                    const matchesLanguage = language === '' || row.dataset.language === language;
                    const matchesMissing = !onlyMissing || Number(row.dataset.missingCount || 0) > 0;
                    const show = matchesTerm && matchesRegion && matchesLanguage && matchesMissing;
                    row.hidden = !show;
                    if (show) {
                        visible += 1;
                    }
                });

                if (emptyRow) {
                    emptyRow.hidden = visible !== 0;
                }
            };

            [countryInput, regionSelect, languageSelect, missingOnly].forEach((control) => {
                control?.addEventListener('input', applyFilters);
                control?.addEventListener('change', applyFilters);
            });

            applyFilters();
        })();
    </script>
    <script>
        (() => {
            const modal = document.getElementById('source-url-modal');
            const form = document.getElementById('source-url-form');
            const dialog = modal?.querySelector('.source-url-dialog');
            const title = document.getElementById('source-url-title');
            const country = document.getElementById('source-url-country');
            const status = document.getElementById('source-url-status');
            const suggestions = document.getElementById('source-url-suggestions');
            const resetButton = document.getElementById('source-url-reset');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const scrollStorageKey = 'sls.sourceMaintenance.scroll';
            let openerButton = null;
            let openerScroll = { x: 0, y: 0 };

            if (!modal || !form) {
                return;
            }

            restoreStoredScroll();

            const checkboxPairs = [
                ['organization_nonexistent', 'organization_name'],
                ['general_url_nonexistent', 'general_url'],
                ['press_url_nonexistent', 'press_url'],
                ['tenders_url_nonexistent', 'tenders_url'],
            ];

            const syncNonexistentControls = () => {
                checkboxPairs.forEach(([checkboxName, inputName]) => {
                    const checkbox = form.elements[checkboxName];
                    const input = form.elements[inputName];
                    if (!checkbox || !input) {
                        return;
                    }

                    input.disabled = checkbox.checked;
                    if (checkbox.checked) {
                        input.value = '';
                    }
                });
            };

            checkboxPairs.forEach(([checkboxName]) => {
                form.elements[checkboxName]?.addEventListener('change', syncNonexistentControls);
            });

            document.querySelectorAll('.source-url-edit').forEach((button) => {
                button.addEventListener('click', () => {
                    openerButton = button;
                    openerScroll = {
                        x: window.scrollX || window.pageXOffset || 0,
                        y: window.scrollY || window.pageYOffset || 0,
                    };
                    status.textContent = '';
                    title.textContent = button.dataset.sourceLabel || button.dataset.organizationName || 'Edit organization URLs';
                    country.textContent = [button.dataset.countryName, button.dataset.countryIso].filter(Boolean).join(' | ');
                    form.elements.country_iso.value = button.dataset.countryIso || '';
                    form.elements.country_name.value = button.dataset.countryName || '';
                    form.elements.region.value = button.dataset.region || '';
                    form.elements.organization_name.value = button.dataset.organizationName || '';
                    form.elements.source_category.value = button.dataset.sourceCategory || 'social_security_administration';
                    form.elements.source_label.value = button.dataset.sourceLabel || button.dataset.organizationName || '';
                    form.elements.product_id.value = button.dataset.productId || form.elements.product_id.value || '';
                    form.elements.general_url.value = button.dataset.generalUrl || '';
                    form.elements.press_url.value = button.dataset.pressUrl || '';
                    form.elements.tenders_url.value = button.dataset.tendersUrl || '';
                    form.elements.organization_nonexistent.checked = button.dataset.organizationNonexistent === '1';
                    form.elements.general_url_nonexistent.checked = button.dataset.generalUrlNonexistent === '1';
                    form.elements.press_url_nonexistent.checked = button.dataset.pressUrlNonexistent === '1';
                    form.elements.tenders_url_nonexistent.checked = button.dataset.tendersUrlNonexistent === '1';
                    syncNonexistentControls();
                    if (resetButton) {
                        resetButton.disabled = !(
                            button.dataset.organizationName
                            || button.dataset.generalUrl
                            || button.dataset.pressUrl
                            || button.dataset.tendersUrl
                            || button.dataset.organizationNonexistent === '1'
                            || button.dataset.generalUrlNonexistent === '1'
                            || button.dataset.pressUrlNonexistent === '1'
                            || button.dataset.tendersUrlNonexistent === '1'
                        );
                    }
                    if (suggestions) {
                        let names = [];
                        try {
                            names = JSON.parse(button.dataset.sourceSuggestions || '[]');
                        } catch (error) {
                            names = [];
                        }
                        suggestions.innerHTML = '';
                        names.forEach((name) => {
                            const option = document.createElement('option');
                            option.value = name;
                            suggestions.appendChild(option);
                        });
                    }
                    modal.hidden = false;
                    requestAnimationFrame(() => {
                        modal.scrollTop = 0;
                        if (dialog) {
                            dialog.scrollTop = 0;
                        }
                        form.elements.organization_name.focus({ preventScroll: true });
                    });
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

                try {
                    const response = await fetch('{{ route('sls.trackedCountries.adminUrls.update') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(Object.fromEntries(new FormData(form).entries())),
                    });
                    const body = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(body.message || 'Could not save URLs.');
                    }

                    storeCurrentScroll();
                    window.location.reload();
                } catch (error) {
                    status.textContent = error.message || 'Could not save URLs.';
                }
            });

            resetButton?.addEventListener('click', async () => {
                const label = form.elements.source_label.value || 'this source slot';
                if (!confirm(`Reset ${label}? This clears the assigned organization and URLs for this country slot.`)) {
                    return;
                }

                status.textContent = 'Resetting...';
                resetButton.disabled = true;

                try {
                    const payload = {
                        country_iso: form.elements.country_iso.value,
                        source_category: form.elements.source_category.value,
                        product_id: form.elements.product_id.value,
                    };
                    const response = await fetch('{{ route('sls.trackedCountries.adminUrls.reset') }}', {
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
                        throw new Error(body.message || 'Could not reset this source slot.');
                    }

                    storeCurrentScroll();
                    window.location.reload();
                } catch (error) {
                    status.textContent = error.message || 'Could not reset this source slot.';
                    resetButton.disabled = false;
                }
            });

            function closeModal() {
                modal.hidden = true;
                status.textContent = '';
                restoreOpenerPosition();
            }

            function restoreOpenerPosition() {
                window.scrollTo(openerScroll.x, openerScroll.y);
                openerButton?.focus({ preventScroll: true });
            }

            function storeCurrentScroll() {
                try {
                    window.sessionStorage?.setItem(scrollStorageKey, JSON.stringify({
                        x: openerScroll.x,
                        y: openerScroll.y,
                    }));
                } catch (error) {
                    // Browser storage can be disabled; the save still works without scroll restore.
                }
            }

            function restoreStoredScroll() {
                try {
                    const raw = window.sessionStorage?.getItem(scrollStorageKey);
                    if (!raw) {
                        return;
                    }

                    window.sessionStorage.removeItem(scrollStorageKey);
                    const position = JSON.parse(raw);
                    requestAnimationFrame(() => {
                        window.scrollTo(Number(position.x || 0), Number(position.y || 0));
                    });
                } catch (error) {
                    // Ignore malformed or blocked storage.
                }
            }
        })();
    </script>
@endpush
