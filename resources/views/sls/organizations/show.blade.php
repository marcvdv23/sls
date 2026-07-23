@extends('sls.layouts.app')

@section('title')
{{ $organization->name }} - 1G-SLS
@endsection
@section('eyebrow', '1G-SLS')
@section('page_title')
{{ $organization->name }}
@endsection

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
        .org-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2px 18px; margin-top: 10px; }
        .org-fact { display: grid; grid-template-columns: 150px minmax(0, 1fr); gap: 10px; align-items: start; border-bottom: 1px solid var(--border-subtle); padding: 5px 0; }
        .org-fact span:first-child { color: var(--text-secondary); font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .org-fact span:last-child,
        .org-fact strong,
        .org-fact a { overflow-wrap: anywhere; }
        .legacy-page table { background: var(--bg-secondary); }
        .contact-table { min-width: 900px; table-layout: fixed; }
        .contact-table th,
        .contact-table td { padding: 12px 16px; vertical-align: top; }
        .contact-table .role-col { width: 110px; }
        .contact-table .name-col { width: 220px; }
        .contact-table .email-col { width: 280px; }
        .contact-table .phone-col { width: 160px; }
        .contact-table .notes-col { width: auto; }
        .contact-table td { line-height: 1.35; overflow-wrap: anywhere; }
        .legacy-page .empty { border: 1px dashed var(--border-subtle); border-radius: var(--radius-card); padding: 14px; }
        @media (max-width: 980px) {
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
            .org-facts { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page">
<header>
            <div>
                <p class="eyebrow">Organization CRM</p>
                <h1>{{ $organization->name }}</h1>
                <p class="muted">{{ $types[$organization->organization_type] ?? $organization->organization_type }} · {{ $industries[$organization->industry] ?? $organization->industry }} · {{ $organization->country ?: 'Country unknown' }}{{ $organization->country_iso ? ' (' . $organization->country_iso . ')' : '' }}</p>
            </div>
            <div class="actions">
                <a class="button" href="{{ route('sls.organizations.edit', $organization) }}">Edit organization</a>
                <a class="button secondary" href="{{ route('sls.organizations.index') }}">Organization directory</a>
                <a class="button secondary" href="{{ route('sls.emailAccounts.index') }}">Email boxes</a>
                <a class="button secondary" href="{{ url('/sls') }}">Dashboard</a>
            </div>
        </header>

        <main class="stack">
            @if (session('status'))
                <section class="panel"><p class="muted">{{ session('status') }}</p></section>
            @endif

            <section class="grid-2">
                <div class="panel">
                    <p class="eyebrow">Profile</p>
                    <h2>Organization details</h2>
                    <div class="org-facts">
                        <div class="org-fact"><span>Lead status</span><strong>{{ $leadStatuses[$organization->lead_status] ?? $organization->lead_status }}</strong></div>
                        <div class="org-fact"><span>Country status</span><span>{{ $organization->country_resolution_status ?: 'resolved' }}</span></div>
                        <div class="org-fact"><span>Country uploaded as</span><span>{{ $organization->country_raw ?: 'Not captured separately' }}</span></div>
                        <div class="org-fact"><span>Subcategory</span><span>{{ $subcategories[$organization->organization_subcategory] ?? $organization->organization_subcategory ?? 'None' }}</span></div>
                        @if ($organization->organization_subcategory === 'school_district')
                            <div class="org-fact"><span>Students</span><strong>{{ $organization->student_count !== null ? number_format($organization->student_count) : 'Not captured' }}</strong></div>
                        @endif
                        <div class="org-fact"><span>Website</span>{!! $organization->website_url ? '<a href="' . e($organization->website_url) . '" target="_blank" rel="noreferrer">' . e($organization->website_domain ?: $organization->website_url) . '</a>' : '<span class="muted">Missing</span>' !!}</div>
                        <div class="org-fact"><span>Phone</span><span>{{ $organization->organization_phone ?: 'Not captured' }}</span></div>
                        <div class="org-fact"><span>Procurement</span>{!! $organization->procurement_page_url ? '<a href="' . e($organization->procurement_page_url) . '" target="_blank" rel="noreferrer">Open procurement page</a>' : '<span class="muted">Not captured</span>' !!}</div>
                        <div class="org-fact"><span>Leadership</span>{!! $organization->leadership_page_url ? '<a href="' . e($organization->leadership_page_url) . '" target="_blank" rel="noreferrer">Open leadership page</a>' : '<span class="muted">Not captured</span>' !!}</div>
                        <div class="org-fact"><span>Last crawled</span><span>{{ $organization->last_crawled_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'Not crawled yet' }}</span></div>
                        <div class="org-fact"><span>Crawler</span><span>{{ $organization->crawler?->name ?? $organization->last_crawler_name ?? 'Unassigned' }}</span></div>
                        <div class="org-fact"><span>SerpAPI calls</span><strong>{{ $organization->crawlerRuns->where('run_type', 'serpapi_discovery')->count() }}</strong></div>
                        <div class="org-fact"><span>Direct crawls</span><strong>{{ $organization->crawlerRuns->where('run_type', 'direct_page_crawl')->count() }}</strong></div>
                    </div>
                </div>

                <div class="panel">
                    <p class="eyebrow">Lead Management</p>
                    <h2>Status and notes</h2>
                    <form method="post" action="{{ route('sls.organizations.leadStatus.update', $organization) }}" class="stack" style="margin-top:.75rem;">
                        @csrf
                        <label>Lead status
                            <select name="lead_status" required>
                                @foreach ($leadStatuses as $value => $label)
                                    <option value="{{ $value }}" {{ $organization->lead_status === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Lead source
                            <input name="lead_source" value="{{ $organization->lead_source }}" placeholder="crawler, import list, event, referral">
                        </label>
                        <label>Notes
                            <textarea name="notes">{{ $organization->notes }}</textarea>
                        </label>
                        <button class="button" type="submit">Update lead</button>
                    </form>
                </div>
            </section>

            <section class="grid-3">
                <div class="panel">
                    <p class="eyebrow">Activity</p>
                    <h2>Log an activity</h2>
                    <form method="post" action="{{ route('sls.organizations.activities.store', $organization) }}" class="stack" style="margin-top:.75rem;">
                        @csrf
                        <label>Type
                            <select name="activity_type">
                                <option value="note">Note</option>
                                <option value="call">Call</option>
                                <option value="meeting">Meeting</option>
                                <option value="research">Research</option>
                                <option value="website_review">Website review</option>
                            </select>
                        </label>
                        <label>Subject <input name="subject" placeholder="Short summary"></label>
                        <label>Details <textarea name="body"></textarea></label>
                        <button class="button" type="submit">Log activity</button>
                    </form>
                </div>

                <div class="panel">
                    <p class="eyebrow">Task</p>
                    <h2>Schedule a task</h2>
                    <form method="post" action="{{ route('sls.organizations.tasks.store', $organization) }}" class="stack" style="margin-top:.75rem;">
                        @csrf
                        <label>Task <input name="title" required placeholder="Follow up, research, prepare email"></label>
                        <label>Type
                            <select name="task_type">
                                <option value="follow_up">Follow up</option>
                                <option value="research">Research</option>
                                <option value="email">Email</option>
                                <option value="call">Call</option>
                                <option value="proposal">Proposal</option>
                            </select>
                        </label>
                        <label>Due date <input name="due_at" type="datetime-local"></label>
                        <label>Notes <textarea name="notes"></textarea></label>
                        <button class="button" type="submit">Create task</button>
                    </form>
                </div>

                <div class="panel">
                    <p class="eyebrow">Communication</p>
                    <h2>Log email/call/message</h2>
                    <form method="post" action="{{ route('sls.organizations.communications.store', $organization) }}" class="stack" style="margin-top:.75rem;">
                        @csrf
                        <label>Channel
                            <select name="channel">
                                <option value="email">Email</option>
                                <option value="phone">Phone</option>
                                <option value="meeting">Meeting</option>
                                <option value="web_form">Web form</option>
                            </select>
                        </label>
                        <label>Direction
                            <select name="direction">
                                <option value="outbound">Outbound</option>
                                <option value="inbound">Inbound</option>
                            </select>
                        </label>
                        <label>Subject <input name="subject"></label>
                        <label>From <input name="from_address" type="email"></label>
                        <label>To <input name="to_address" type="email"></label>
                        <label>Excerpt <textarea name="body_excerpt"></textarea></label>
                        <button class="button" type="submit">Log communication</button>
                    </form>
                </div>
            </section>

            <section class="panel">
                <p class="eyebrow">Crawler Evidence</p>
                <h2>SerpAPI and crawl history for this organization</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Crawler</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Query / URL</th>
                                <th>HTTP</th>
                                <th>Found</th>
                                <th>Updated</th>
                                <th>Contacts</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($organization->crawlerRuns as $run)
                                @php
                                    $payload = is_array($run->result_payload) ? $run->result_payload : [];
                                    $updatedFields = collect($payload['updated_fields'] ?? [])
                                        ->map(fn ($field) => str_replace('_page_url', '', str_replace('_url', '', $field)))
                                        ->implode(', ');
                                    $checkedSummary = collect($payload['checked_urls'] ?? [])->take(2)->implode(' | ');
                                @endphp
                                <tr>
                                    <td class="nowrap">{{ $run->started_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') }}</td>
                                    <td>{{ $run->crawler?->name ?: $organization->crawler?->name ?: '-' }}</td>
                                    <td>{{ str_replace('_', ' ', $run->run_type) }}</td>
                                    <td><span class="pill {{ $run->status === 'completed' ? 'good' : ($run->status === 'error' ? 'bad' : 'warn') }}">{{ $run->status }}</span></td>
                                    <td class="summary-cell">{{ $run->query_text ?: $run->url_checked ?: $checkedSummary ?: $run->request_url }}</td>
                                    <td>{{ $run->http_status ?: '-' }}</td>
                                    <td>{{ $run->items_found }}</td>
                                    <td>{{ $updatedFields ?: ($run->urls_updated ?: '-') }}</td>
                                    <td>{{ $run->contacts_found }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="muted">No SerpAPI or crawler run has been logged for this organization yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid-2">
                <div class="panel">
                    <p class="eyebrow">Open Tasks</p>
                    <h2>Tasks and reminders</h2>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Task</th><th>Due</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($organization->tasks as $task)
                                    <tr>
                                        <td><strong>{{ $task->title }}</strong><br><span class="muted">{{ $task->notes }}</span></td>
                                        <td>{{ $task->due_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'No date' }}</td>
                                        <td><span class="pill">{{ $task->status }}</span></td>
                                        <td>
                                            @if ($task->status !== 'completed')
                                                <form method="post" action="{{ route('sls.organizations.tasks.complete', $task) }}">
                                                    @csrf
                                                    <button class="button small secondary" type="submit">Complete</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="muted">No tasks yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <p class="eyebrow">Contacts</p>
                    <h2>Known contacts</h2>
                    <div class="table-wrap">
                        <table class="contact-table">
                            <thead><tr><th class="role-col">Role</th><th class="name-col">Name/title</th><th class="email-col">Email</th><th class="phone-col">Phone</th><th class="notes-col">Notes</th></tr></thead>
                            <tbody>
                                @forelse ($organization->contacts as $contact)
                                    <tr>
                                        <td>{{ $contact->contact_type }}</td>
                                        <td><strong>{{ $contact->person_name ?: 'Unknown' }}</strong><br><span class="muted">{{ $contact->job_title }}</span></td>
                                        <td>{{ $contact->email ?: 'Not captured' }}</td>
                                        <td>{{ $contact->phone ?: 'Not captured' }}</td>
                                        <td>{{ $contact->notes ?: 'No notes yet' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="muted">No contacts linked yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="grid-2">
                <div class="panel">
                    <p class="eyebrow">Communications</p>
                    <h2>Emails, calls, meetings</h2>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>When</th><th>Direction</th><th>Subject</th><th>Addresses</th></tr></thead>
                            <tbody>
                                @forelse ($organization->communications as $communication)
                                    <tr>
                                        <td>{{ $communication->sent_or_received_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'No date' }}</td>
                                        <td>{{ $communication->channel }} / {{ $communication->direction }}</td>
                                        <td><strong>{{ $communication->subject ?: 'No subject' }}</strong><br><span class="muted">{{ $communication->body_excerpt }}</span></td>
                                        <td><span class="muted">From:</span> {{ $communication->from_address ?: '-' }}<br><span class="muted">To:</span> {{ $communication->to_address ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="muted">No communications logged yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <p class="eyebrow">Activity History</p>
                    <h2>Logged activity</h2>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>When</th><th>Type</th><th>Subject</th></tr></thead>
                            <tbody>
                                @forelse ($organization->activities as $activity)
                                    <tr>
                                        <td>{{ $activity->activity_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'No date' }}</td>
                                        <td>{{ $activity->activity_type }}</td>
                                        <td><strong>{{ $activity->subject ?: 'No subject' }}</strong><br><span class="muted">{{ $activity->body }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="muted">No activities logged yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
@endsection
