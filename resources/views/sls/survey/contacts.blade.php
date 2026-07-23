@extends('sls.layouts.app')

@section('title', 'University Survey Contacts')
@section('eyebrow', '1G-SLS')
@section('page_title', 'University Survey Contacts')

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
        .legacy-page table { background: var(--bg-secondary); }
        .legacy-page .empty { border: 1px dashed var(--border-subtle); border-radius: var(--radius-card); padding: 14px; }
        @media (max-width: 980px) {
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page">
<header>
            <div>
                <p class="eyebrow">Survey Outreach</p>
                <h1>University Survey Contacts</h1>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('sls.survey.universities') }}">University targets</a>
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
            </div>
        </header>

        <main class="stack">
            <section class="panel">
                <p class="muted">These contacts were collected from public university web pages with source traceability. Pattern evidence is stored separately and is not treated as a verified email address.</p>
                <form class="filters" method="get" action="{{ route('sls.survey.contacts') }}" style="margin-top: .8rem;">
                    <input name="q" value="{{ $query }}" placeholder="Search university, country, name, title, email">
                    <select name="role">
                        @foreach (['all' => 'All roles', 'leadership' => 'Leadership', 'hr' => 'HR', 'it' => 'IT', 'general' => 'General'] as $value => $label)
                            <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="button" type="submit">Search</button>
                    <a class="button secondary" href="{{ route('sls.survey.contacts') }}">Clear</a>
                </form>
            </section>

            <section class="panel">
                <p class="eyebrow">Contacts</p>
                <h2>{{ $contacts->count() }} contact reference(s) shown</h2>
                <div class="table-wrap" style="margin-top: .75rem;">
                    <table>
                        <thead>
                            <tr>
                                <th class="email-col">Email</th>
                                <th class="school-col">University</th>
                                <th class="role-col">Role</th>
                                <th class="person-col">Person / title</th>
                                <th>Status</th>
                                <th class="context-col">Context</th>
                                <th class="source-col">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contacts as $contact)
                                <tr>
                                    <td>
                                        @if ($contact->email)
                                            <div class="copy-row">
                                                <span class="copy-value">{{ $contact->email }}</span>
                                                <button class="copy-button" type="button" title="Copy email" data-copy-text="{{ $contact->email }}">C</button>
                                            </div>
                                        @else
                                            <span class="muted">No published email</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $contact->target?->name ?? $contact->organization }}</strong>
                                        <p class="muted">{{ $contact->target?->country ?: $contact->target?->domain }}</p>
                                    </td>
                                    <td>{{ strtoupper($contact->role_category) }}</td>
                                    <td>
                                        <strong>{{ $contact->person_name ?: 'Not identified' }}</strong>
                                        <p class="muted">{{ $contact->job_title ?: 'Title not identified' }}</p>
                                    </td>
                                    <td>{{ str_replace('_', ' ', $contact->email_status) }}</td>
                                    <td class="muted">{{ $contact->context_excerpt }}</td>
                                    <td>
                                        <a href="{{ $contact->source_url }}" target="_blank" rel="noreferrer">Open source</a>
                                        <p class="muted">{{ $contact->found_at?->timezone('America/Chicago')->format('Y-m-d H:i') }}</p>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="muted">No university survey contacts match this filter yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
        <script>
            document.querySelectorAll('[data-copy-text]').forEach((button) => {
                button.addEventListener('click', async () => {
                    await navigator.clipboard.writeText(button.dataset.copyText || '');
                    button.classList.add('copied');
                    setTimeout(() => button.classList.remove('copied'), 1200);
                });
            });
        </script>
    </div>
@endsection