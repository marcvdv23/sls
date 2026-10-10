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
        .maintenance-table { min-width:1180px; table-layout:fixed; }
        .maintenance-table td,
        .maintenance-table th { padding:7px 9px; vertical-align:top; }
        .country-col { width:190px; }
        .iso-col { width:62px; }
        .region-col { width:135px; }
        .source-col { width:36%; }
        .url-col { width:38%; }
        .source-name-list { display:grid; gap:4px; }
        .source-row { display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:8px; align-items:start; padding:4px 0; border-bottom:1px solid var(--border-subtle); }
        .source-row:last-child { border-bottom:0; }
        .source-title { font-weight:800; overflow-wrap:anywhere; }
        .source-title.missing { color:var(--accent-danger); }
        .source-slot { display:block; color:var(--text-secondary); font-size:.72rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
        .source-description { display:block; color:var(--text-secondary); font-size:.78rem; margin-top:2px; }
        .country-progress { display:grid; gap:4px; margin-top:8px; color:var(--text-secondary); font-size:.78rem; }
        .unlinked-source-list { display:grid; gap:3px; margin-top:10px; padding-top:8px; border-top:1px solid var(--border-subtle); color:var(--text-secondary); font-size:.78rem; }
        .unlinked-source-list strong { color:var(--text-primary); }
        .source-urls { display:grid; gap:3px; color:var(--text-secondary); font-size:.78rem; overflow-wrap:anywhere; }
        .source-urls a { color:var(--accent-primary); font-weight:700; text-decoration:none; }
        .source-urls a:hover { text-decoration:underline; }
        .source-url-edit { width:34px; height:30px; min-height:30px; padding:0; border-radius:6px; font-size:.76rem; }
        .source-url-modal[hidden] { display:none; }
        .source-url-modal { position:fixed; inset:0; z-index:1000; display:grid; place-items:center; padding:24px; background:rgba(15, 23, 42, .46); }
        .source-url-dialog { width:min(560px, 100%); border-radius:8px; border:1px solid var(--border-subtle); background:var(--bg-primary); box-shadow:0 24px 70px rgba(15, 23, 42, .28); }
        .source-url-dialog header { display:flex; justify-content:space-between; gap:12px; padding:14px 16px; border-bottom:1px solid var(--border-subtle); }
        .source-url-dialog h3 { margin:0; font-size:1rem; }
        .source-url-dialog form { display:grid; gap:12px; padding:16px; }
        .source-url-dialog label { display:grid; gap:5px; color:var(--text-secondary); font-size:.78rem; font-weight:800; text-transform:uppercase; }
        .source-url-dialog input,
        .source-url-dialog select { width:100%; box-sizing:border-box; }
        .source-url-dialog .form-status { min-height:1.2em; color:var(--accent-danger); font-size:.82rem; }
        .source-url-reset { border-color:var(--accent-danger); color:var(--accent-danger); background:transparent; }
        .source-url-reset:hover { background:rgba(220, 38, 38, .08); }
    </style>
@endpush

@section('content')
    <div class="source-maintenance-page">
        <section class="panel stack">
            <div class="source-maintenance-head">
                <div>
                    <p class="eyebrow">Tracked Countries And Organizations</p>
                    <h2>Maintain source organization URLs</h2>
                    <p class="muted">This page is limited to the official source organizations used for social security intelligence. Each country has the same required source slots; researchers fill missing organization names and the General, Press, and Procurement URLs.</p>
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
                            <th class="iso-col">ISO</th>
                            <th class="region-col">Region</th>
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
                                    <span class="country-progress">
                                        <span>{{ $country->complete_source_count ?? 0 }}/{{ $country->total_source_count ?? 0 }} source slots identified</span>
                                        @if (($country->missing_source_count ?? 0) > 0)
                                            <span class="pill bad">{{ $country->missing_source_count }} missing</span>
                                        @else
                                            <span class="pill good">Complete</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="mono iso-col">{{ strtoupper($country->iso_code ?? 'n/a') }}</td>
                                <td class="region-col">{{ $country->region }}</td>
                                <td class="source-col">
                                    @if (($country->social_security_administration_links ?? collect())->isNotEmpty())
                                        <div class="source-name-list">
                                            @foreach (($country->social_security_administration_links ?? collect()) as $admin)
                                                <div class="source-row">
                                                    <span>
                                                        <span class="source-slot">{{ $admin['slot_label'] ?? 'Source organization' }}</span>
                                                        @if (filled($admin['name'] ?? null))
                                                            <span class="source-title">{{ $admin['name'] }}</span>
                                                        @else
                                                            <span class="source-title missing">Missing - add organization name</span>
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
                                                    >Edit</button>
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
                                <td class="url-col">
                                    <div class="source-name-list">
                                        @foreach (($country->social_security_administration_links ?? collect()) as $admin)
                                            <div class="source-urls">
                                                <strong>{{ $admin['slot_label'] ?? $admin['name'] }}</strong>
                                                @if (blank($admin['name'] ?? null))
                                                    <span>Organization: not identified</span>
                                                @elseif (($admin['slot_label'] ?? null) && ($admin['name'] ?? null) && $admin['slot_label'] !== $admin['name'])
                                                    <span>Organization: {{ $admin['name'] }}</span>
                                                @endif
                                                @if (filled($admin['general_url'] ?? null))
                                                    <span>General: <a href="{{ $admin['general_url'] }}" target="_blank" rel="noreferrer">{{ $admin['general_url'] }}</a></span>
                                                @else
                                                    <span>General: not set</span>
                                                @endif
                                                @if (filled($admin['press_url'] ?? null))
                                                    <span>Press: <a href="{{ $admin['press_url'] }}" target="_blank" rel="noreferrer">{{ $admin['press_url'] }}</a></span>
                                                @endif
                                                @if (filled($admin['tenders_url'] ?? null))
                                                    <span>Tenders: <a href="{{ $admin['tenders_url'] }}" target="_blank" rel="noreferrer">{{ $admin['tenders_url'] }}</a></span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        <tr data-tracked-country-empty hidden>
                            <td colspan="5" class="muted">No tracked countries match these filters.</td>
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
                <label>
                    Organization name
                    <input type="text" name="organization_name" list="source-url-suggestions" placeholder="Official organization name" autocomplete="off" required>
                    <span class="muted">Start typing to reuse an existing unassigned organization for this country, or enter a new official name.</span>
                </label>
                <datalist id="source-url-suggestions"></datalist>
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
        })();
    </script>
    <script>
        (() => {
            const modal = document.getElementById('source-url-modal');
            const form = document.getElementById('source-url-form');
            const title = document.getElementById('source-url-title');
            const country = document.getElementById('source-url-country');
            const status = document.getElementById('source-url-status');
            const suggestions = document.getElementById('source-url-suggestions');
            const resetButton = document.getElementById('source-url-reset');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            if (!modal || !form) {
                return;
            }

            document.querySelectorAll('.source-url-edit').forEach((button) => {
                button.addEventListener('click', () => {
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
                    if (resetButton) {
                        resetButton.disabled = !(
                            button.dataset.organizationName
                            || button.dataset.generalUrl
                            || button.dataset.pressUrl
                            || button.dataset.tendersUrl
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
                    form.elements.organization_name.focus();
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

                    window.location.reload();
                } catch (error) {
                    status.textContent = error.message || 'Could not reset this source slot.';
                    resetButton.disabled = false;
                }
            });

            function closeModal() {
                modal.hidden = true;
                status.textContent = '';
            }
        })();
    </script>
@endpush
