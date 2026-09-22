@extends('sls.layouts.app')

@section('title', 'SerpAPI Searches - 1G-SLS')
@section('eyebrow', 'Intelligence')
@section('page_title', 'SerpAPI Searches')

@push('head')
    <style>
        .serp-grid { display: grid; grid-template-columns: minmax(320px, .9fr) minmax(540px, 1.4fr); gap: 14px; align-items: start; }
        .serp-form { display: grid; gap: 12px; }
        .serp-form-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 10px; align-items: end; }
        .serp-form label { display: grid; gap: 5px; color: var(--text-secondary); font-size: 12px; font-weight: 800; }
        .serp-form .span-3 { grid-column: span 3; }
        .serp-form .span-4 { grid-column: span 4; }
        .serp-form .span-6 { grid-column: span 6; }
        .serp-form .span-12 { grid-column: span 12; }
        .serp-form textarea { min-height: 96px; }
        .serp-country-select { width: 100%; min-width: 0; }
        .serp-country-count { display: block; margin-top: 3px; }
        .check-row { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; }
        .check-row label { display: inline-flex; flex-direction: row; align-items: center; gap: 7px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
        .run-card { display: grid; gap: 8px; }
        .result-table { min-width: 1100px; }
        .result-table td { vertical-align: top; }
        .muted.small { font-size: 12px; }
        @media (max-width: 1100px) {
            .serp-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .serp-form-grid { grid-template-columns: 1fr; }
            .serp-form .span-3,
            .serp-form .span-4,
            .serp-form .span-6,
            .serp-form .span-12 { grid-column: auto; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        (() => {
            const regionSelect = document.querySelector('[data-serp-region]');
            const languageSelect = document.querySelector('[data-serp-language]');
            const countrySelect = document.querySelector('[data-serp-countries]');
            const countryCount = document.querySelector('[data-serp-country-count]');

            if (!regionSelect || !languageSelect || !countrySelect) {
                return;
            }

            const options = Array.from(countrySelect.options);

            const applyCountryFilters = () => {
                const region = (regionSelect.value || '').toLowerCase();
                const language = (languageSelect.value || '').toLowerCase();
                let visible = 0;

                options.forEach((option) => {
                    const matchesRegion = !region || (option.dataset.region || '').toLowerCase() === region;
                    const matchesLanguage = !language || (option.dataset.language || '').toLowerCase() === language;
                    const isVisible = matchesRegion && matchesLanguage;

                    option.hidden = !isVisible;

                    if (!isVisible) {
                        option.selected = false;
                    } else {
                        visible += 1;
                    }
                });

                if (countryCount) {
                    countryCount.textContent = `${visible} countr${visible === 1 ? 'y' : 'ies'} match the current region and language filters.`;
                }
            };

            regionSelect.addEventListener('change', applyCountryFilters);
            languageSelect.addEventListener('change', applyCountryFilters);
            applyCountryFilters();
        })();
    </script>
@endpush

@php
    $latestRun = $recentRuns->first();
    $latestItems = collect($latestRun?->items ?? []);
@endphp

@section('content')
    <div class="stack">
        @if ($migrationMissing)
            <section class="panel">
                <p class="eyebrow">Setup required</p>
                <h2>Run migrations first</h2>
                <p class="muted">{{ $setupError ?? 'The SerpAPI search template table does not exist yet.' }}</p>
                <p class="muted">Deploy this update and run <code>php artisan migrate</code>, then <code>php artisan optimize:clear</code>.</p>
            </section>
        @else
            <section class="stats-grid">
                @forelse ($monthlyStats as $month)
                    <div class="panel stat">
                        <strong>{{ $month->run_month }}</strong>
                        <span class="muted">{{ number_format((int) $month->query_count) }} SerpAPI call(s)</span>
                        <span class="muted small">{{ number_format((int) $month->result_count) }} results, {{ number_format((int) $month->captured_count) }} captured</span>
                    </div>
                @empty
                    <div class="panel stat"><strong>0</strong><span class="muted">SerpAPI calls tracked so far</span></div>
                @endforelse
            </section>

            <section class="serp-grid">
                <section class="panel">
                    <p class="eyebrow">Run Search</p>
                    <h2>Start a SerpAPI search</h2>
                    <form class="serp-form" method="post" action="{{ route('sls.serpapiSearches.run') }}">
                        @csrf
                        <div class="serp-form-grid">
                            <label class="span-6">Saved search
                                <select name="template_id">
                                    <option value="">Custom search</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @selected(old('template_id') == $template->id)>
                                            {{ $template->name }}{{ $template->is_enabled ? '' : ' (disabled)' }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="span-3">Results per country
                                <input type="number" name="results_per_country" min="1" max="20" value="{{ old('results_per_country', 10) }}">
                            </label>
                            <label class="span-3">Region
                                <select name="region" data-serp-region>
                                    <option value="">Any region</option>
                                    @foreach ($regions as $region)
                                        <option value="{{ $region }}" @selected(old('region') === $region)>{{ $region }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="span-3">Language group
                                <select name="language" data-serp-language>
                                    @foreach ($languageOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('language') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="span-12">Specific countries
                                <select class="serp-country-select" name="countries[]" multiple size="8" data-serp-countries>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country['iso_code'] }}" data-region="{{ $country['region'] }}" data-language="{{ $country['language'] }}" @selected(in_array($country['iso_code'], old('countries', []), true))>
                                            {{ $country['name'] }} ({{ $country['iso_code'] }}) - {{ $country['region'] }}{{ $country['language'] ? ' / ' . strtoupper($country['language']) : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="muted small serp-country-count" data-serp-country-count></span>
                            </label>
                            <label class="span-12">Custom query template
                                <input name="custom_query_template" value="{{ old('custom_query_template') }}" placeholder='"{country}" ({keywords}) tender OR RFP'>
                                <span class="muted small">Placeholders: <code>{country}</code>, <code>{iso}</code>, <code>{keywords}</code>. Leave blank to use the saved template.</span>
                            </label>
                            <label class="span-12">Custom keywords, one per line
                                <textarea name="custom_keywords_text" placeholder="social insurance software tender&#10;pension administration system RFP&#10;beneficiary registry procurement">{{ old('custom_keywords_text') }}</textarea>
                            </label>
                            <div class="span-12 check-row">
                                <input type="hidden" name="dry_run" value="0">
                                <label><input type="checkbox" name="dry_run" value="1" checked> Dry run only</label>
                                <input type="hidden" name="capture" value="0">
                                <label><input type="checkbox" name="capture" value="1" checked> Capture non-duplicates into Review Desk</label>
                            </div>
                        </div>
                        <div class="actions">
                            <button class="button" type="submit">Run SerpAPI search</button>
                            <span class="muted small">If no countries are selected, the region and language filters define the batch.</span>
                        </div>
                    </form>
                </section>

                <section class="panel">
                    <p class="eyebrow">Configure Keywords</p>
                    <h2>Save a search template</h2>
                    <form class="serp-form" method="post" action="{{ route('sls.serpapiSearches.templates.store') }}">
                        @csrf
                        <div class="serp-form-grid">
                            <label class="span-6">Name
                                <input name="name" value="{{ old('name') }}" placeholder="SSAS tender search">
                            </label>
                            <label class="span-3">Focus
                                <select name="focus">
                                    <option value="social_security">SSAS / social security</option>
                                    <option value="hrms_tenders">HRMS</option>
                                    <option value="erms_tenders">ERMS</option>
                                    <option value="ebpc_tenders">EBPC</option>
                                    <option value="sector_tenders">Sector tender</option>
                                </select>
                            </label>
                            <label class="span-3">Default results
                                <input type="number" name="results_per_country" min="1" max="20" value="{{ old('results_per_country', 10) }}">
                            </label>
                            <label class="span-12">Query template
                                <input name="query_template" value="{{ old('query_template', '"{country}" ({keywords})') }}">
                            </label>
                            <label class="span-12">Keywords
                                <textarea name="keywords_text">{{ old('keywords_text') }}</textarea>
                            </label>
                            <div class="span-12 check-row">
                                <label><input type="checkbox" name="is_enabled" value="1" checked> Enabled</label>
                            </div>
                        </div>
                        <button class="button secondary" type="submit">Save template</button>
                    </form>
                </section>
            </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Saved Templates</p>
                    <h2>Configured SerpAPI searches</h2>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Focus</th>
                                <th>Query</th>
                                <th>Keywords</th>
                                <th>Default results</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($templates as $template)
                                <tr>
                                    <td>{{ $template->name }}</td>
                                    <td>{{ $template->focus }}</td>
                                    <td><code>{{ $template->query_template }}</code></td>
                                    <td>{{ collect($template->keywords ?? [])->take(5)->implode(', ') }}</td>
                                    <td>{{ $template->results_per_country }}</td>
                                    <td><span class="pill {{ $template->is_enabled ? 'good' : 'warn' }}">{{ $template->is_enabled ? 'enabled' : 'disabled' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="muted">No saved search templates yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Recent Runs</p>
                    <h2>SerpAPI run history and results</h2>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Run</th>
                                <th>Status</th>
                                <th>Countries</th>
                                <th>Calls</th>
                                <th>Results</th>
                                <th>Captured</th>
                                <th>Started</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentRuns as $run)
                                @php($summary = $run->summary ?? [])
                                <tr>
                                    <td>{{ $run->operation_name }}</td>
                                    <td><span class="pill {{ $run->status === 'completed' ? 'good' : ($run->status === 'failed' ? 'bad' : 'warn') }}">{{ $run->status }}</span></td>
                                    <td>{{ number_format((int) ($summary['countries'] ?? $run->total_count)) }}</td>
                                    <td>{{ number_format((int) ($summary['queries'] ?? $run->processed_count)) }}</td>
                                    <td>{{ number_format((int) ($summary['results'] ?? 0)) }}</td>
                                    <td>{{ number_format((int) ($summary['captured'] ?? $run->success_count)) }}</td>
                                    <td>{{ $run->started_at?->format('Y-m-d H:i') ?: $run->created_at?->format('Y-m-d H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="muted">No SerpAPI runs yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($latestRun)
                <section class="panel stack">
                    <div>
                        <p class="eyebrow">Latest Results</p>
                        <h2>{{ $latestRun->operation_name }}</h2>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table result-table">
                            <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>Status</th>
                                    <th>Title</th>
                                    <th>Source</th>
                                    <th>Query</th>
                                    <th>Captured item</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($latestItems as $item)
                                    <tr>
                                        <td>{{ $item['country'] ?? '' }} {{ isset($item['iso_code']) ? '(' . $item['iso_code'] . ')' : '' }}</td>
                                        <td><span class="pill {{ ($item['status'] ?? '') === 'captured' ? 'good' : (($item['status'] ?? '') === 'error' ? 'bad' : 'warn') }}">{{ $item['status'] ?? 'found' }}</span></td>
                                        <td>
                                            @if (! empty($item['source_url']))
                                                <a href="{{ $item['source_url'] }}" target="_blank" rel="noreferrer">{{ $item['title'] ?? $item['source_url'] }}</a>
                                            @else
                                                {{ $item['title'] ?? ($item['error'] ?? '') }}
                                            @endif
                                            @if (! empty($item['snippet']))
                                                <p class="muted small">{{ Str::limit($item['snippet'], 220) }}</p>
                                            @endif
                                        </td>
                                        <td>{{ $item['source_name'] ?? '' }}</td>
                                        <td class="muted small">{{ $item['query'] ?? '' }}</td>
                                        <td>
                                            @if (! empty($item['country_update_id']))
                                                <a href="{{ route('sls.intelligence.updates.sourcePage', $item['country_update_id']) }}">Open #{{ str_pad((string) $item['country_update_id'], 5, '0', STR_PAD_LEFT) }}</a>
                                            @else
                                                <span class="muted">Not captured</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="muted">No results recorded for the latest run yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @endif
    </div>
@endsection
