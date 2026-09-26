@extends('sls.layouts.app')

@section('title', 'Priority Opportunities - 1G-SLS')
@section('eyebrow', 'Work Queue')
@section('page_title', 'Priority Opportunities')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.tasks.index') }}">To Do</a>
    <a class="button secondary" href="{{ route('sls.organizations.index') }}">Organizations</a>
@endsection

@push('head')
    <style>
        .priority-grid { display:grid; grid-template-columns:minmax(360px, .8fr) minmax(560px, 1.2fr); gap:14px; align-items:start; }
        .priority-form { display:grid; gap:10px; }
        .priority-filter-grid { display:grid; grid-template-columns:2fr 160px 180px 120px 120px; gap:8px; align-items:end; }
        .priority-table { min-width:1280px; }
        .priority-table th,
        .priority-table td { padding:8px 10px; vertical-align:top; }
        .priority-table .opportunity-title { min-width:320px; }
        .priority-table .action-cell { min-width:280px; }
        .muted.small { font-size:12px; }
        .source-list { display:grid; gap:4px; }
        @media (max-width:1100px) {
            .priority-grid,
            .priority-filter-grid { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="stack">
        <section class="priority-grid">
            <div class="panel stack">
                <div>
                    <p class="eyebrow">Import</p>
                    <h2>Load curated shortlist</h2>
                    <p class="muted">Imports priority opportunities, creates or links target account records, creates initial To Do tasks, and logs a CRM activity on the account.</p>
                </div>
                <form class="priority-form" method="post" action="{{ route('sls.priorityOpportunities.import') }}" enctype="multipart/form-data">
                    @csrf
                    <label>CSV file
                        <input type="file" name="import_file" accept=".csv,text/csv" required>
                    </label>
                    <input type="hidden" name="create_organizations" value="0">
                    <label><input type="checkbox" name="create_organizations" value="1" checked> Create or link CRM organizations</label>
                    <input type="hidden" name="create_tasks" value="0">
                    <label><input type="checkbox" name="create_tasks" value="1" checked> Create initial follow-up tasks</label>
                    <button class="button" type="submit">Import priority opportunities</button>
                </form>
            </div>

            <div class="panel stack">
                <div>
                    <p class="eyebrow">Focus</p>
                    <h2>Filter pursuit list</h2>
                </div>
                <form class="priority-filter-grid" method="get" action="{{ route('sls.priorityOpportunities.index') }}">
                    <label>Search
                        <input name="q" value="{{ $query }}" placeholder="Country, institution, reform, next action">
                    </label>
                    <label>Status
                        <select name="status">
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Focus tier
                        <select name="tier">
                            <option value="all" @selected($tier === 'all')>All</option>
                            @foreach ($tiers as $tierValue)
                                <option value="{{ $tierValue }}" @selected($tier === $tierValue)>{{ $tierValue }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="button" type="submit">Apply</button>
                    <a class="button secondary" href="{{ route('sls.priorityOpportunities.index') }}">Clear</a>
                </form>
            </div>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Pipeline</p>
                <h2>Curated social security opportunities</h2>
            </div>
            <div class="table-wrap">
                <table class="priority-table">
                    <thead>
                        <tr>
                            <th>Opportunity</th>
                            <th>Status</th>
                            <th>Account</th>
                            <th>Evidence</th>
                            <th>Next action</th>
                            <th>Task</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($opportunities as $opportunity)
                            <tr>
                                <td class="opportunity-title">
                                    <strong>{{ $opportunity->country_market }} | {{ $opportunity->institution }}</strong>
                                    <p class="muted small">{{ $opportunity->focus_tier ?: 'No tier' }} · {{ $opportunity->evidence_confidence ?: 'confidence not set' }} · {{ $opportunity->stage_2026 ?: 'stage not set' }}</p>
                                    <p>{{ $opportunity->reform_development }}</p>
                                    @if ($opportunity->why_relevant)
                                        <p class="muted small">{{ $opportunity->why_relevant }}</p>
                                    @endif
                                </td>
                                <td>
                                    <span class="pill {{ in_array($opportunity->status, ['active_pursuit', 'qualified'], true) ? 'good' : ($opportunity->status === 'parked' ? 'warn' : 'normal') }}">{{ str_replace('_', ' ', $opportunity->status) }}</span>
                                    <p class="muted small">{{ ucfirst($opportunity->priority) }} priority</p>
                                </td>
                                <td>
                                    @if ($opportunity->primaryOrganization)
                                        <a href="{{ route('sls.organizations.show', $opportunity->primaryOrganization) }}">{{ $opportunity->primaryOrganization->name }}</a>
                                    @else
                                        <span class="muted">Not linked</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="source-list">
                                        @if ($opportunity->source_1)
                                            <a href="{{ $opportunity->source_1 }}" target="_blank" rel="noreferrer">Source 1</a>
                                        @endif
                                        @if ($opportunity->source_2)
                                            <a href="{{ $opportunity->source_2 }}" target="_blank" rel="noreferrer">Source 2</a>
                                        @endif
                                        @if (! $opportunity->source_1 && ! $opportunity->source_2)
                                            <span class="muted">No source URL</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="action-cell">{{ $opportunity->recommended_next_action ?: 'No next action captured.' }}</td>
                                <td>
                                    @if ($opportunity->primaryTask)
                                        <a href="{{ route('sls.tasks.index', ['q' => $opportunity->primaryTask->title]) }}">{{ $opportunity->primaryTask->status }} · {{ $opportunity->primaryTask->due_at?->copy()->timezone('America/Chicago')->format('Y-m-d') ?? 'no due date' }}</a>
                                    @else
                                        <span class="muted">No task</span>
                                    @endif
                                </td>
                                <td><a class="button secondary" href="{{ route('sls.priorityOpportunities.show', $opportunity) }}">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">No priority opportunities yet. Import the shortlist CSV to start.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $opportunities->links() }}
        </section>
    </div>
@endsection
