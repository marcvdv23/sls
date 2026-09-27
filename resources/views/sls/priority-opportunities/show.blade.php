@extends('sls.layouts.app')

@section('title')
{{ $opportunity->institution }} - Priority Opportunity - 1G-SLS
@endsection
@section('eyebrow', 'Priority Opportunity')
@section('page_title')
{{ $opportunity->institution }}
@endsection

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.priorityOpportunities.index') }}">Priority list</a>
    @if ($opportunity->primaryOrganization)
        <a class="button secondary" href="{{ route('sls.organizations.show', $opportunity->primaryOrganization) }}">Open account</a>
    @endif
    <a class="button secondary" href="{{ route('sls.tasks.index') }}">To Do</a>
@endsection

@push('head')
    <style>
        .opportunity-layout { display:grid; grid-template-columns:minmax(0, 1.25fr) minmax(340px, .75fr); gap:14px; align-items:start; }
        .fact-grid { display:grid; grid-template-columns:170px minmax(0, 1fr); gap:6px 14px; }
        .fact-grid span:nth-child(odd) { color:var(--text-secondary); font-size:12px; font-weight:800; text-transform:uppercase; }
        .source-list { display:grid; gap:8px; }
        .update-form { display:grid; gap:10px; }
        .activity-table { min-width:760px; }
        @media (max-width:980px) {
            .opportunity-layout { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="opportunity-layout">
        <main class="stack">
            <section class="panel stack">
                <div>
                    <p class="eyebrow">{{ $opportunity->country_market }}{{ $opportunity->country_iso ? ' (' . $opportunity->country_iso . ')' : '' }}</p>
                    <h2>{{ $opportunity->reform_development ?: 'Priority opportunity' }}</h2>
                </div>
                <div class="fact-grid">
                    <span>Focus tier</span><strong>{{ $opportunity->focus_tier ?: '-' }}</strong>
                    <span>Stage</span><span>{{ $opportunity->stage_2026 ?: '-' }}</span>
                    <span>Confidence</span><span>{{ $opportunity->evidence_confidence ?: '-' }}</span>
                    <span>Evidence / scale</span><span>{{ $opportunity->evidence_scale ?: '-' }}</span>
                    <span>Donor support</span><span>{{ $opportunity->donor_support ?: '-' }}</span>
                    <span>Origin</span><span>{{ $opportunity->origin ?: '-' }}</span>
                </div>
            </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Rationale</p>
                    <h2>Why this matters</h2>
                </div>
                <p>{{ $opportunity->why_relevant ?: 'No rationale captured.' }}</p>
                @if ($opportunity->review_notes)
                    <p class="muted">{{ $opportunity->review_notes }}</p>
                @endif
            </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Evidence</p>
                    <h2>Sources</h2>
                </div>
                <div class="source-list">
                    @if ($opportunity->source_1)
                        <a class="button secondary" href="{{ $opportunity->source_1 }}" target="_blank" rel="noreferrer">Open source 1</a>
                    @endif
                    @if ($opportunity->source_2)
                        <a class="button secondary" href="{{ $opportunity->source_2 }}" target="_blank" rel="noreferrer">Open source 2</a>
                    @endif
                    @if ($opportunity->countryUpdate)
                        <a class="button secondary" href="{{ route('sls.intelligence.updates.sourcePage', $opportunity->countryUpdate) }}">Open Review Desk evidence</a>
                    @endif
                    @if (! $opportunity->source_1 && ! $opportunity->source_2 && ! $opportunity->countryUpdate)
                        <span class="muted">No source evidence linked yet.</span>
                    @endif
                </div>
            </section>

            @if ($opportunity->primaryOrganization)
                <section class="panel stack">
                    <div>
                        <p class="eyebrow">CRM Activity</p>
                        <h2>Recent account history</h2>
                    </div>
                    <div class="table-wrap">
                        <table class="activity-table">
                            <thead><tr><th>When</th><th>Type</th><th>Subject</th></tr></thead>
                            <tbody>
                                @forelse ($opportunity->primaryOrganization->activities->take(12) as $activity)
                                    <tr>
                                        <td>{{ $activity->activity_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? '-' }}</td>
                                        <td>{{ str_replace('_', ' ', $activity->activity_type) }}</td>
                                        <td><strong>{{ $activity->subject ?: '-' }}</strong><br><span class="muted">{{ $activity->body }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="muted">No account activity logged yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </main>

        <aside class="stack">
            <section class="panel stack">
                <div>
                    <p class="eyebrow">Pipeline</p>
                    <h2>Status and next action</h2>
                </div>
                <form class="update-form" method="post" action="{{ route('sls.priorityOpportunities.update', $opportunity) }}">
                    @csrf
                    <label>Status
                        <select name="status">
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}" @selected($opportunity->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Priority
                        <select name="priority">
                            @foreach ($priorities as $key => $label)
                                <option value="{{ $key }}" @selected($opportunity->priority === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Next follow-up
                        <input name="next_follow_up_at" type="datetime-local" value="{{ $opportunity->next_follow_up_at?->format('Y-m-d\\TH:i') }}">
                    </label>
                    <label>Recommended next action
                        <textarea name="recommended_next_action" rows="5">{{ $opportunity->recommended_next_action }}</textarea>
                    </label>
                    <label>Review notes
                        <textarea name="review_notes" rows="5">{{ $opportunity->review_notes }}</textarea>
                    </label>
                    <button class="button" type="submit">Update opportunity</button>
                </form>
            </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Links</p>
                    <h2>Existing SLS records</h2>
                </div>
                <div class="source-list">
                    @if ($opportunity->primaryOrganization)
                        <a href="{{ route('sls.organizations.show', $opportunity->primaryOrganization) }}">{{ $opportunity->primaryOrganization->name }}</a>
                        <span class="muted">{{ $opportunity->primaryOrganization->country ?: $opportunity->primaryOrganization->country_iso }} · {{ $opportunity->primaryOrganization->lead_source ?: 'CRM account' }}</span>
                    @else
                        <span class="muted">No primary account linked.</span>
                    @endif
                    @if ($opportunity->primaryTask)
                        <a href="{{ route('sls.tasks.index', ['q' => $opportunity->primaryTask->title]) }}">Task: {{ $opportunity->primaryTask->status }} · {{ $opportunity->primaryTask->due_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'no due date' }}</a>
                    @else
                        <span class="muted">No task linked.</span>
                    @endif
                    @foreach ($opportunity->organizations as $organization)
                        @if (! $opportunity->primaryOrganization || $organization->id !== $opportunity->primaryOrganization->id)
                            <a href="{{ route('sls.organizations.show', $organization) }}">{{ $organization->name }}</a>
                        @endif
                    @endforeach
                </div>
            </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Account mapping</p>
                    <h2>Link to CRM account</h2>
                    <p class="muted">Use this when the import created or picked the wrong account. It keeps the opportunity and points it back to the correct existing organization.</p>
                </div>
                @if ($suggestedOrganization && (! $opportunity->primaryOrganization || $suggestedOrganization->id !== $opportunity->primaryOrganization->id))
                    <p class="muted">Suggested match: <strong>{{ $suggestedOrganization->name }}</strong></p>
                @endif
                <form class="update-form" method="post" action="{{ route('sls.priorityOpportunities.linkAccount', $opportunity) }}">
                    @csrf
                    <label>CRM account
                        <select name="market_organization_id" required>
                            <option value="">Choose account</option>
                            @foreach ($organizationChoices as $organization)
                                <option value="{{ $organization->id }}" @selected($opportunity->primary_organization_id === $organization->id)>
                                    {{ $organization->name }}{{ $organization->country_iso ? ' (' . $organization->country_iso . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label><input type="checkbox" name="move_primary_task" value="1" checked> Move linked To Do task to this account</label>
                    <button class="button" type="submit">Link account</button>
                    <a class="button secondary" href="{{ route('sls.crm.search', ['q' => $opportunity->institution, 'type' => 'accounts', 'country' => $opportunity->country_iso ?: $opportunity->country_market]) }}">Search CRM accounts</a>
                </form>
            </section>
        </aside>
    </div>
@endsection
