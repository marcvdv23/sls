@extends('sls.layouts.app')

@section('title', 'Global CRM Search - 1G-SLS')
@section('eyebrow', 'CRM')
@section('page_title', 'Global CRM Search')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.organizations.index') }}">Organizations</a>
    <a class="button secondary" href="{{ route('sls.intelligence.search') }}">Country search</a>
    <a class="button secondary" href="{{ route('sls.opportunities.index') }}">Story opportunities</a>
@endsection

@push('head')
    <style>
        .crm-search-grid { display:grid; grid-template-columns:minmax(260px, 1fr) 12rem 12rem 12rem auto; gap:10px; align-items:end; }
        .summary-grid { display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); gap:10px; }
        .stat strong { display:block; font-family:"Sora", sans-serif; font-size:1.55rem; line-height:1; margin-bottom:3px; }
        .result-section { display:grid; gap:10px; }
        .result-row { display:grid; grid-template-columns:10rem minmax(16rem, 1fr) 12rem 11rem; gap:12px; align-items:start; padding:10px 0; border-top:1px solid var(--border-subtle); }
        .result-row:first-of-type { border-top:0; }
        .kind { display:inline-flex; width:max-content; border-radius:999px; padding:3px 8px; background:color-mix(in srgb, var(--accent-primary) 12%, transparent); color:var(--accent-primary); font-size:11px; font-weight:800; }
        .kind.warn { background:color-mix(in srgb, var(--accent-warning) 16%, transparent); color:var(--accent-warning); }
        .meta { display:flex; flex-wrap:wrap; gap:5px; margin-top:6px; }
        .snippet { margin-top:5px; color:var(--text-secondary); line-height:1.45; overflow-wrap:anywhere; }
        .empty { border:1px dashed var(--border-subtle); background:var(--bg-secondary); border-radius:var(--radius-card); padding:16px; }
        label.field { display:grid; gap:4px; color:var(--text-secondary); font-size:12px; font-weight:800; }
        @media (max-width:1100px) {
            .crm-search-grid, .summary-grid, .result-row { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="stack">
        <section class="panel">
            <form class="crm-search-grid" method="get" action="{{ route('sls.crm.search') }}">
                <label class="field">Search anything
                    <input name="q" value="{{ $query }}" placeholder="Account, contact, email, country, tender, note, product opportunity">
                </label>
                <label class="field">Result type
                    <select name="type">
                        <option value="all" @selected($type === 'all')>All CRM data</option>
                        <option value="accounts" @selected($type === 'accounts')>Accounts</option>
                        <option value="contacts" @selected($type === 'contacts')>Contacts</option>
                        <option value="opportunities" @selected($type === 'opportunities')>Opportunities</option>
                        <option value="tasks" @selected($type === 'tasks')>Tasks / reminders</option>
                        <option value="activities" @selected($type === 'activities')>Activities / notes</option>
                        <option value="communications" @selected($type === 'communications')>Communications</option>
                        <option value="ocr_drafts" @selected($type === 'ocr_drafts')>OCR drafts</option>
                    </select>
                </label>
                <label class="field">Country
                    <input name="country" value="{{ $country }}" placeholder="Optional">
                </label>
                <label class="field">Status
                    <input name="status" value="{{ $status === 'all' ? '' : $status }}" placeholder="Optional">
                </label>
                <button type="submit">Search</button>
            </form>
        </section>

        @if ($query === '')
            <section class="empty">
                <h2>Search across accounts, contacts, opportunities, notes, tasks, communications, and OCR drafts.</h2>
                <p class="muted" style="margin-top:5px;">This is the single CRM search entry point. Staged OCR entries still appear separately until they are promoted into regular account/contact records.</p>
            </section>
        @else
            <section class="summary-grid">
                <div class="panel stat"><strong>{{ $total }}</strong><span class="muted">total shown</span></div>
                <div class="panel stat"><strong>{{ $summary['accounts'] }}</strong><span class="muted">accounts</span></div>
                <div class="panel stat"><strong>{{ $summary['contacts'] }}</strong><span class="muted">contacts</span></div>
                <div class="panel stat"><strong>{{ $summary['opportunities'] }}</strong><span class="muted">opportunities</span></div>
                <div class="panel stat"><strong>{{ $summary['tasks'] }}</strong><span class="muted">tasks</span></div>
                <div class="panel stat"><strong>{{ $summary['communications'] }}</strong><span class="muted">communications</span></div>
                <div class="panel stat"><strong>{{ $summary['ocr_drafts'] }}</strong><span class="muted">OCR drafts</span></div>
            </section>

            @forelse ($sections as $section)
                <section class="panel result-section">
                    <div>
                        <p class="eyebrow">{{ $section['label'] }}</p>
                        <h2>{{ $section['items']->count() }} result(s){{ isset($section['extra']) ? ' plus ' . $section['extra']->count() . ' generated opportunity draft(s)' : '' }}</h2>
                    </div>

                    @if ($section['key'] === 'accounts')
                        @foreach ($section['items'] as $item)
                            <article class="result-row">
                                <span class="kind">Account</span>
                                <div>
                                    <h3><a href="{{ route('sls.organizations.show', $item) }}">{{ $item->name }}</a></h3>
                                    <p class="snippet">{{ $item->notes ? Str::limit($item->notes, 220) : 'No notes yet.' }}</p>
                                    <div class="meta">
                                        <span class="pill">{{ $item->organization_type }}</span>
                                        @if ($item->industry)<span class="pill">{{ $item->industry }}</span>@endif
                                        @if ($item->organization_subcategory)<span class="pill">{{ $item->organization_subcategory }}</span>@endif
                                        <span class="pill">{{ $item->contacts_count }} contacts</span>
                                        <span class="pill">{{ $item->tasks_count }} tasks</span>
                                        <span class="pill">{{ $item->last_crawled_at ? 'Crawled ' . $item->last_crawled_at->copy()->timezone('America/Chicago')->format('Y-m-d') : 'Not crawled' }}</span>
                                        @if ($item->crawler)
                                            <span class="pill">{{ $item->crawler->name }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="muted">{{ $item->country ?: 'No country' }}{{ $item->country_iso ? ' (' . $item->country_iso . ')' : '' }}</div>
                                <div>
                                    @if ($item->website_domain)
                                        <a href="{{ $item->website_url ?: 'https://' . $item->website_domain }}" target="_blank" rel="noopener">{{ $item->website_domain }}</a>
                                    @else
                                        <span class="muted">No website</span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    @elseif ($section['key'] === 'contacts')
                        @foreach ($section['items'] as $item)
                            <article class="result-row">
                                <span class="kind">Contact</span>
                                <div>
                                    <h3>{{ $item->person_name ?: 'Unnamed contact' }}</h3>
                                    <p class="snippet">{{ $item->job_title ?: 'No title yet.' }}{{ $item->context_excerpt ? ' - ' . Str::limit($item->context_excerpt, 180) : '' }}</p>
                                    <div class="meta">
                                        @if ($item->email)<span class="pill">{{ $item->email }}</span>@endif
                                        @if ($item->phone)<span class="pill">{{ $item->phone }}</span>@endif
                                        <span class="pill">{{ $item->verification_status }}</span>
                                    </div>
                                </div>
                                <div>
                                    @if ($item->organization)
                                        <a href="{{ route('sls.organizations.show', $item->organization) }}">{{ $item->organization->name }}</a>
                                    @else
                                        <span class="muted">No account</span>
                                    @endif
                                </div>
                                <div class="muted">{{ $item->organization?->country ?: 'No country' }}</div>
                            </article>
                        @endforeach
                    @elseif ($section['key'] === 'opportunities')
                        @foreach ($section['items'] as $item)
                            <article class="result-row">
                                <span class="kind">Intel</span>
                                <div>
                                    <h3>{{ $item->title_english ?: $item->title_original ?: $item->title }}</h3>
                                    <p class="snippet">{{ Str::limit($item->summary ?: 'No summary yet.', 260) }}</p>
                                    <div class="meta">
                                        <span class="pill">{{ $item->topic?->name ?: 'No topic' }}</span>
                                        <span class="pill">{{ $item->review_status }}</span>
                                        @if ($item->publication_date)<span class="pill">{{ $item->publication_date->format('Y-m-d') }}</span>@endif
                                    </div>
                                </div>
                                <div class="muted">{{ $item->country?->name ?: 'No country' }}</div>
                                <div>
                                    @if ($item->source_url)<a href="{{ $item->source_url }}" target="_blank" rel="noopener">Open source</a>@endif
                                </div>
                            </article>
                        @endforeach
                        @foreach (($section['extra'] ?? collect()) as $item)
                            <article class="result-row">
                                <span class="kind warn">Draft</span>
                                <div>
                                    <h3><a href="{{ route('sls.opportunities.show', $item) }}">{{ $item->issue_area ?: 'Product opportunity' }}</a></h3>
                                    <p class="snippet">{{ Str::limit($item->issue_summary ?: $item->product_alignment ?: 'No summary yet.', 260) }}</p>
                                    <div class="meta">
                                        <span class="pill">{{ $item->product?->name ?: 'No product' }}</span>
                                        <span class="pill">{{ $item->opportunity_stage }}</span>
                                    </div>
                                </div>
                                <div class="muted">{{ $item->countryUpdate?->country?->name ?: 'No country' }}</div>
                                <div><a href="{{ route('sls.opportunities.show', $item) }}">Open draft</a></div>
                            </article>
                        @endforeach
                    @elseif ($section['key'] === 'tasks')
                        @foreach ($section['items'] as $item)
                            <article class="result-row">
                                <span class="kind">Task</span>
                                <div>
                                    <h3>{{ $item->title }}</h3>
                                    <p class="snippet">{{ Str::limit($item->notes ?: 'No task notes.', 220) }}</p>
                                    <div class="meta">
                                        <span class="pill">{{ $item->status }}</span>
                                        <span class="pill">{{ $item->task_type }}</span>
                                        @if ($item->due_at)<span class="pill">Due {{ $item->due_at->format('Y-m-d') }}</span>@endif
                                    </div>
                                </div>
                                <div>@if ($item->organization)<a href="{{ route('sls.organizations.show', $item->organization) }}">{{ $item->organization->name }}</a>@endif</div>
                                <div class="muted">{{ $item->organization?->country ?: 'No country' }}</div>
                            </article>
                        @endforeach
                    @elseif ($section['key'] === 'activities')
                        @foreach ($section['items'] as $item)
                            <article class="result-row">
                                <span class="kind">Note</span>
                                <div>
                                    <h3>{{ $item->subject ?: ucfirst($item->activity_type) }}</h3>
                                    <p class="snippet">{{ Str::limit($item->body ?: 'No note body.', 260) }}</p>
                                    <div class="meta">
                                        <span class="pill">{{ $item->activity_type }}</span>
                                        @if ($item->activity_at)<span class="pill">{{ $item->activity_at->format('Y-m-d H:i') }}</span>@endif
                                    </div>
                                </div>
                                <div>@if ($item->organization)<a href="{{ route('sls.organizations.show', $item->organization) }}">{{ $item->organization->name }}</a>@endif</div>
                                <div class="muted">{{ $item->organization?->country ?: 'No country' }}</div>
                            </article>
                        @endforeach
                    @elseif ($section['key'] === 'communications')
                        @foreach ($section['items'] as $item)
                            <article class="result-row">
                                <span class="kind">Communication</span>
                                <div>
                                    <h3>{{ $item->subject ?: ucfirst($item->channel) . ' communication' }}</h3>
                                    <p class="snippet">{{ Str::limit($item->body_excerpt ?: 'No body excerpt.', 240) }}</p>
                                    <div class="meta">
                                        <span class="pill">{{ $item->direction }}</span>
                                        <span class="pill">{{ $item->channel }}</span>
                                        @if ($item->from_address)<span class="pill">From {{ $item->from_address }}</span>@endif
                                        @if ($item->to_address)<span class="pill">To {{ $item->to_address }}</span>@endif
                                    </div>
                                </div>
                                <div>@if ($item->organization)<a href="{{ route('sls.organizations.show', $item->organization) }}">{{ $item->organization->name }}</a>@endif</div>
                                <div class="muted">{{ $item->contact?->person_name ?: $item->organization?->country ?: 'No contact' }}</div>
                            </article>
                        @endforeach
                    @elseif ($section['key'] === 'ocr_drafts')
                        @foreach ($section['items'] as $item)
                            <article class="result-row">
                                <span class="kind warn">OCR Draft</span>
                                <div>
                                    <h3>{{ $item->organization_name ?: 'Unnamed draft' }}</h3>
                                    <p class="snippet">{{ Str::limit($item->address_text ?: $item->raw_text ?: 'No extracted context.', 260) }}</p>
                                    <div class="meta">
                                        <span class="pill">{{ $item->review_status }}</span>
                                        @if ($item->executive_text)<span class="pill">{{ Str::limit($item->executive_text, 70) }}</span>@endif
                                        @if ($item->email)<span class="pill">{{ $item->email }}</span>@endif
                                        @if ($item->phone)<span class="pill">{{ $item->phone }}</span>@endif
                                    </div>
                                </div>
                                <div class="muted">{{ $item->country_normalized ?: $item->country_raw ?: 'No country' }}</div>
                                <div>
                                    @if ($item->batch)
                                        <a href="{{ route('sls.directoryImages.show', $item->batch) }}">Review batch</a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    @endif
                </section>
            @empty
                <section class="empty">
                    <h2>No matching CRM records found.</h2>
                    <p class="muted" style="margin-top:5px;">Try a shorter term, a country name, an email domain, or switch the result type back to all CRM data.</p>
                </section>
            @endforelse
        @endif
    </div>
@endsection
