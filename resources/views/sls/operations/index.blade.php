@extends('sls.layouts.app')

@section('title', 'Operations - 1G-SLS')
@section('eyebrow', 'System')
@section('page_title', 'Operations')

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
        .operations-grid { display: grid; grid-template-columns: minmax(260px, .8fr) minmax(520px, 1.4fr); gap: 14px; align-items: start; }
        .operation-card { display: grid; gap: 8px; }
        .operation-card.active { border-color: var(--accent-primary); }
        .operation-form { display: grid; gap: 12px; }
        .operation-form-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 10px; align-items: end; }
        .operation-form label { display: grid; gap: 5px; color: var(--text-secondary); font-size: 12px; font-weight: 800; }
        .operation-form input,
        .operation-form select { min-height: 38px; }
        .operation-form .span-2 { grid-column: span 2; }
        .operation-form .span-3 { grid-column: span 3; }
        .operation-form .span-4 { grid-column: span 4; }
        .operation-form .span-6 { grid-column: span 6; }
        .operation-form .span-12 { grid-column: span 12; }
        .check-row { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
        .check-row label { display: inline-flex; flex-direction: row; align-items: center; gap: 8px; }
        .check-row input { min-height: auto; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
        .result-table { min-width: 960px; }
        .result-table th,
        .result-table td { padding: 8px 10px; vertical-align: top; }
        .reason-list { margin: 0; padding-left: 18px; }
        .muted.small { font-size: 12px; }
        @media (max-width: 1100px) {
            .operations-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
            .operation-form-grid { grid-template-columns: 1fr; }
            .operation-form .span-2,
            .operation-form .span-3,
            .operation-form .span-4,
            .operation-form .span-6,
            .operation-form .span-12 { grid-column: auto; }
        }
    </style>
@endpush

@php
    $input = array_merge([
        'limit' => 50,
        'region' => '',
        'countries' => [],
        'retry_previous_not_found' => true,
        'dry_run' => true,
        'pause' => 1,
        'confidence' => 70,
        'checks' => 12,
    ], $lastInput ?? []);
    $selectedCountries = collect($input['countries'] ?? [])->map(fn ($value) => strtoupper((string) $value))->all();
@endphp

@section('content')
    <div class="legacy-page">
        <header>
            <div>
                <p class="eyebrow">Reusable job launcher</p>
                <h1>Operations</h1>
                <p class="muted">Run controlled maintenance and enrichment tasks without opening a terminal.</p>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('sls.organizations.index', ['type' => 'financial_institution', 'industry' => 'banking_finance']) }}">Bank records</a>
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
            </div>
        </header>

        <main>
            @if ($errors->any())
                <section class="panel">
                    <p class="eyebrow">Check settings</p>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="summary">
                <div class="panel stat"><strong>{{ number_format($operationStats['eligible_banks']) }}</strong><span class="muted">bank records missing domains</span></div>
                <div class="panel stat"><strong>{{ number_format($operationStats['regions']) }}</strong><span class="muted">regions available</span></div>
                <div class="panel stat"><strong>{{ number_format($operationStats['countries']) }}</strong><span class="muted">countries available</span></div>
            </section>

            <section class="operations-grid">
                <aside class="panel operation-card active">
                    <p class="eyebrow">Available operation</p>
                    <h2>Bank domain guesser</h2>
                    <p class="muted">Guesses missing official bank domains from organization names, checks candidates, and can update high-confidence matches.</p>
                    <span class="pill">First reusable operation</span>
                </aside>

                <section class="panel">
                    <p class="eyebrow">Run parameters</p>
                    <h2>Start bank domain guessing</h2>
                    <form class="operation-form" method="post" action="{{ route('sls.operations.bankDomains.run') }}">
                        @csrf
                        <div class="operation-form-grid">
                            <label class="span-2">Limit
                                <input type="number" name="limit" min="1" max="500" value="{{ old('limit', $input['limit']) }}">
                            </label>
                            <label class="span-3">Region
                                <select name="region">
                                    <option value="">All regions</option>
                                    @foreach ($regions as $region)
                                        <option value="{{ $region->region }}" {{ old('region', $input['region']) === $region->region ? 'selected' : '' }}>
                                            {{ $region->region }} ({{ number_format($region->missing_count) }})
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="span-4">Countries
                                <select name="countries[]" multiple size="5">
                                    @foreach ($countries as $country)
                                        <option value="{{ $country['value'] }}" {{ in_array($country['value'], old('countries', $selectedCountries), true) ? 'selected' : '' }}>
                                            {{ $country['label'] }} - {{ number_format($country['missing_count']) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="span-3">Confidence threshold
                                <select name="confidence">
                                    @foreach ([60, 70, 80, 90] as $confidence)
                                        <option value="{{ $confidence }}" {{ (int) old('confidence', $input['confidence']) === $confidence ? 'selected' : '' }}>{{ $confidence }}%</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="span-2">Pause seconds
                                <input type="number" name="pause" min="0" max="30" value="{{ old('pause', $input['pause']) }}">
                            </label>
                            <label class="span-2">Checks per bank
                                <input type="number" name="checks" min="1" max="48" value="{{ old('checks', $input['checks']) }}">
                            </label>
                            <div class="span-8 check-row">
                                <input type="hidden" name="retry_previous_not_found" value="0">
                                <label>
                                    <input type="checkbox" name="retry_previous_not_found" value="1" {{ old('retry_previous_not_found', $input['retry_previous_not_found']) ? 'checked' : '' }}>
                                    Retry earlier misses
                                </label>
                                <input type="hidden" name="dry_run" value="0">
                                <label>
                                    <input type="checkbox" name="dry_run" value="1" {{ old('dry_run', $input['dry_run']) ? 'checked' : '' }}>
                                    Dry run only
                                </label>
                            </div>
                        </div>
                        <div class="actions">
                            <button class="button" type="submit">Run operation</button>
                            <span class="muted small">Uncheck dry run to write successful website/domain guesses to the organization records.</span>
                        </div>
                    </form>
                </section>
            </section>

            @if ($lastResult)
                <section class="panel">
                    <p class="eyebrow">Latest run</p>
                    <h2>{{ $lastInput['dry_run'] ? 'Dry run' : 'Update run' }} completed</h2>
                    <section class="summary" style="margin-top:10px;">
                        <div class="panel stat"><strong>{{ number_format($lastResult['eligible']) }}</strong><span class="muted">eligible at start</span></div>
                        <div class="panel stat"><strong>{{ number_format($lastResult['processed']) }}</strong><span class="muted">processed</span></div>
                        <div class="panel stat"><strong>{{ number_format($lastResult['ready_for_crawl']) }}</strong><span class="muted">ready for crawl</span></div>
                        <div class="panel stat"><strong>{{ number_format($lastResult['domain_guess_not_found']) }}</strong><span class="muted">not found</span></div>
                        <div class="panel stat"><strong>{{ number_format($lastResult['checks']) }}</strong><span class="muted">checks made</span></div>
                    </section>

                    <div class="table-wrap" style="margin-top:12px;">
                        <table class="result-table">
                            <thead>
                                <tr>
                                    <th>Organization</th>
                                    <th>Country</th>
                                    <th>Status</th>
                                    <th>Best domain</th>
                                    <th>Confidence</th>
                                    <th>Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lastResult['items'] as $item)
                                    <tr>
                                        <td>
                                            <a href="{{ route('sls.organizations.show', $item['id']) }}">{{ $item['name'] }}</a>
                                            <p class="muted small">{{ number_format($item['guesses']) }} guesses, {{ number_format($item['checked']) }} checked</p>
                                        </td>
                                        <td>{{ $item['country'] }} {{ $item['country_iso'] ? '(' . $item['country_iso'] . ')' : '' }}</td>
                                        <td><span class="pill">{{ str_replace('_', ' ', $item['status']) }}</span></td>
                                        <td>
                                            @if ($item['best_url'])
                                                <a href="{{ $item['best_url'] }}" target="_blank" rel="noopener">{{ $item['best_domain'] }}</a>
                                            @else
                                                <span class="muted">No candidate passed</span>
                                            @endif
                                        </td>
                                        <td>{{ (int) $item['confidence'] }}%</td>
                                        <td>
                                            @if ($item['reasons'])
                                                <ul class="reason-list">
                                                    @foreach ($item['reasons'] as $reason)
                                                        <li>{{ $reason }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="muted">No evidence captured</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="muted">No records matched these parameters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </main>
    </div>
@endsection
