@extends('sls.layouts.app')

@section('title', 'Priority Account Mapping - 1G-SLS')
@section('eyebrow', 'Work Queue')
@section('page_title', 'Priority Account Mapping')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.priorityOpportunities.index') }}">Priority Opportunities</a>
    <a class="button secondary" href="{{ route('sls.organizations.index') }}">Organizations</a>
@endsection

@push('head')
    <style>
        .mapping-filter-grid { display:grid; grid-template-columns:minmax(260px, 1fr) 220px 130px 170px; gap:8px; align-items:end; }
        .mapping-table { min-width:1320px; }
        .mapping-table th,
        .mapping-table td { padding:8px 10px; vertical-align:top; }
        .candidate-list { display:grid; gap:8px; min-width:300px; }
        .candidate-row { display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:8px; align-items:center; }
        .mapping-current { min-width:260px; }
        .mapping-status { min-width:160px; }
        .muted.small { font-size:12px; }
        @media (max-width:1000px) {
            .mapping-filter-grid { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="stack">
        <section class="panel stack">
            <div>
                <p class="eyebrow">Account matching</p>
                <h2>Review imported vs existing CRM accounts</h2>
                <p class="muted">Rows marked as import-created are real CRM accounts, but they may duplicate an account already in SLS. Suggestions are limited to the same country.</p>
            </div>
            <form class="mapping-filter-grid" method="get" action="{{ route('sls.priorityOpportunities.accountMapping') }}">
                <label>Search
                    <input name="q" value="{{ $query }}" placeholder="Country, ISO, institution, account">
                </label>
                <label>Mapping status
                    <select name="status">
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button" type="submit">Apply</button>
                <a class="button secondary" href="{{ route('sls.priorityOpportunities.accountMapping') }}">Clear</a>
            </form>
            <form method="post" action="{{ route('sls.priorityOpportunities.accountMapping.autoMatch') }}">
                @csrf
                <button class="button secondary" type="submit">Run conservative auto-match</button>
            </form>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Mapping table</p>
                <h2>{{ $opportunities->total() }} priority row{{ $opportunities->total() === 1 ? '' : 's' }}</h2>
            </div>
            <div class="table-wrap">
                <table class="mapping-table">
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>Priority opportunity</th>
                            <th>Current account</th>
                            <th>Status</th>
                            <th>Same-country suggestions</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($opportunities as $opportunity)
                            @php
                                $isImportCreated = $opportunity->primaryOrganization && $opportunity->primaryOrganization->lead_source === 'priority opportunity import';
                                $mappingLabel = ! $opportunity->primaryOrganization
                                    ? 'Unlinked'
                                    : ($isImportCreated ? 'Import-created account' : 'Linked existing account');
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $opportunity->country_market }}</strong>
                                    <p class="muted small">{{ $opportunity->country_iso ?: 'No ISO' }}</p>
                                </td>
                                <td>
                                    <strong>{{ $opportunity->institution }}</strong>
                                    <p class="muted small">{{ $opportunity->focus_tier ?: 'No tier' }} · {{ $opportunity->stage_2026 ?: 'No stage' }}</p>
                                </td>
                                <td class="mapping-current">
                                    @if ($opportunity->primaryOrganization)
                                        <a href="{{ route('sls.organizations.show', $opportunity->primaryOrganization) }}">{{ $opportunity->primaryOrganization->name }}</a>
                                        <p class="muted small">#{{ $opportunity->primaryOrganization->id }} · {{ $opportunity->primaryOrganization->lead_source ?: 'existing CRM account' }}</p>
                                    @else
                                        <span class="muted">No account linked</span>
                                    @endif
                                </td>
                                <td class="mapping-status">
                                    <span class="pill {{ $isImportCreated || ! $opportunity->primaryOrganization ? 'warn' : 'good' }}">{{ $mappingLabel }}</span>
                                </td>
                                <td>
                                    <div class="candidate-list">
                                        @forelse ($opportunity->candidate_accounts as $candidate)
                                            <div class="candidate-row">
                                                <div>
                                                    <a href="{{ route('sls.organizations.show', $candidate) }}">{{ $candidate->name }}</a>
                                                    <p class="muted small">#{{ $candidate->id }} · score {{ $candidate->match_score }} · {{ $candidate->lead_source ?: 'existing CRM account' }}</p>
                                                </div>
                                                <form method="post" action="{{ route('sls.priorityOpportunities.linkAccount', $opportunity) }}">
                                                    @csrf
                                                    <input type="hidden" name="market_organization_id" value="{{ $candidate->id }}">
                                                    <input type="hidden" name="move_primary_task" value="1">
                                                    <button class="button secondary" type="submit">Link</button>
                                                </form>
                                            </div>
                                        @empty
                                            <span class="muted">No same-country suggestions.</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td><a class="button secondary" href="{{ route('sls.priorityOpportunities.show', $opportunity) }}">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">No priority opportunities match this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $opportunities->links() }}
        </section>
    </div>
@endsection
