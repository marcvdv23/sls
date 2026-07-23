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
                <h1>University Contact Discovery</h1>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('sls.survey.contacts') }}">Survey contacts</a>
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
            </div>
        </header>

        <main class="stack">
            @if (session('status'))
                <section class="panel">
                    <p>{{ session('status') }}</p>
                </section>
            @endif

            <section class="grid">
                <div class="stat">
                    <strong>{{ $summary['targets'] }}</strong>
                    <span class="muted">university targets</span>
                </div>
                <div class="stat">
                    <strong>{{ $summary['pending'] }}</strong>
                    <span class="muted">pending crawl</span>
                </div>
                <div class="stat">
                    <strong>{{ $summary['contacts'] }}</strong>
                    <span class="muted">contact references</span>
                </div>
            </section>

            <section class="panel">
                <p class="eyebrow">Import Targets</p>
                <h2>Paste universities one per line</h2>
                <p class="muted" style="margin-top: .3rem;">Use CSV-style lines like: University Name, Country, https://www.example.edu. The crawler stores only published contacts and source evidence.</p>
                <form method="post" action="{{ route('sls.survey.universities.import') }}" class="stack" style="margin-top: .8rem;">
                    @csrf
                    <textarea name="targets" placeholder="University of Example, Country, https://www.example.edu"></textarea>
                    <div class="actions">
                        <button class="button" type="submit">Import universities</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <form class="filters" method="get" action="{{ route('sls.survey.universities') }}">
                    <input name="q" value="{{ $query }}" placeholder="Search university, country, domain">
                    <select name="status">
                        @foreach (['all' => 'All statuses', 'pending' => 'Pending', 'crawled' => 'Crawled', 'error' => 'Error', 'disabled' => 'Disabled'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="button" type="submit">Filter</button>
                    <a class="button secondary" href="{{ route('sls.survey.universities') }}">Clear</a>
                </form>
            </section>

            <section class="panel">
                <p class="eyebrow">Targets</p>
                <h2>{{ $targets->count() }} target(s) shown</h2>
                <div class="table-wrap" style="margin-top: .75rem;">
                    <table>
                        <thead>
                            <tr>
                                <th class="name-col">University</th>
                                <th class="country-col">Country</th>
                                <th class="url-col">Website</th>
                                <th class="status-col">Status</th>
                                <th>Last / next crawl</th>
                                <th>Contacts</th>
                                <th class="pattern-col">Published email patterns</th>
                                <th class="actions-col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($targets as $target)
                                <tr>
                                    <td>
                                        <strong>{{ $target->name }}</strong>
                                        <p class="muted">{{ $target->domain }}</p>
                                    </td>
                                    <td>{{ $target->country ?: 'Unknown' }}</td>
                                    <td><a href="{{ $target->website_url }}" target="_blank" rel="noreferrer">{{ $target->website_url }}</a></td>
                                    <td><span class="badge {{ $target->status }}">{{ $target->status }}</span></td>
                                    <td>
                                        <p>{{ $target->last_crawled_at?->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'Not crawled yet' }}</p>
                                        <p class="muted">Next: {{ $target->next_crawl_at?->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'not scheduled' }}</p>
                                        @if ($target->last_error)
                                            <p class="muted">{{ $target->last_error }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $target->contacts_count }}</strong>
                                        <p class="muted">{{ $target->pages_checked }} page(s) checked</p>
                                    </td>
                                    <td class="muted">{{ collect($target->published_email_patterns ?? [])->implode(', ') ?: 'No pattern evidence yet' }}</td>
                                    <td>
                                        <div class="actions">
                                            <form method="post" action="{{ route('sls.survey.universities.crawl', $target) }}">
                                                @csrf
                                                <button class="button small" type="submit">Crawl now</button>
                                            </form>
                                            <a class="button small secondary" href="{{ route('sls.survey.contacts', ['q' => $target->name]) }}">Contacts</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="muted">No university targets match this filter yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection