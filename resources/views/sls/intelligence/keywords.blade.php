@extends('sls.layouts.app')

@section('title', 'Intelligence Keywords')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Intelligence Keywords')

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
        .term-list { display:flex; flex-wrap:wrap; gap:6px; max-width:640px; }
        .term-chip { display:inline-flex; border-radius:999px; padding:.22rem .5rem; background:#eef4ff; color:var(--accent-primary); font-size:.76rem; font-weight:800; line-height:1.2; }
        .term-chip.signal { background:#ecfdf3; color:#047857; }
        @media (max-width: 980px) {
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page">
<header>
            <div>
                <p class="eyebrow">Country Intelligence</p>
                <h1>Keyword Directory</h1>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('sls.intelligence.sources', ['region' => 'all']) }}">Source registry</a>
                <a class="button secondary" href="{{ route('sls.intelligence.crawlerSettings') }}">Crawler settings</a>
                <a class="button secondary" href="{{ route('sls.intelligence.review', ['focus' => 'social_security', 'region' => 'all']) }}">Review desk</a>
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
            </div>
        </header>

        <main class="stack">
            @if (session('status'))
                <section class="panel"><p class="muted">{{ session('status') }}</p></section>
            @endif

            <section class="panel">
                <p class="eyebrow">Workspace Monitored Terms</p>
                <h2>Terms used by this workspace's crawlers</h2>
                <p class="muted" style="margin-top:.35rem;">These are the enabled review-category terms and strong signals for the active workspace. The crawler uses them to find, classify, and explain matched stories, tenders, and regulatory items.</p>

                <div class="table-wrap" style="margin-top:12px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Focus</th>
                                <th>Terms</th>
                                <th>Strong signals</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reviewFocuses as $reviewFocus)
                                <tr>
                                    <td>
                                        <strong>{{ $reviewFocus->label }}</strong>
                                        @if ($reviewFocus->description)
                                            <p class="muted">{{ $reviewFocus->description }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="term-list">
                                            @forelse (($reviewFocus->terms ?? []) as $term)
                                                <span class="term-chip">{{ $term }}</span>
                                            @empty
                                                <span class="muted">No terms configured.</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>
                                        <div class="term-list">
                                            @forelse (($reviewFocus->strong_signals ?? []) as $term)
                                                <span class="term-chip signal">{{ $term }}</span>
                                            @empty
                                                <span class="muted">No strong signals configured.</span>
                                            @endforelse
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="muted">No workspace review categories are configured yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <p class="eyebrow">Manage Terms</p>
                <h2>Additional keyword rules</h2>
                <p class="muted" style="margin-top:.35rem;">These workspace-specific keyword rows are added to the review-category terms above. Source Discovery terms are used by SerpAPI to find official sources for this workspace.</p>

                <form class="filter-grid" method="get" action="{{ route('sls.intelligence.keywords') }}">
                    <label>Focus
                        <select name="focus">
                            <option value="all" @selected($focusFilter === 'all')>All focuses</option>
                            @foreach ($focuses as $key => $focus)
                                <option value="{{ $key }}" @selected($focusFilter === $key)>{{ $focus['label'] ?? $key }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Language
                        <select name="language">
                            <option value="all" @selected($languageFilter === 'all')>All</option>
                            @foreach ($languages as $language)
                                <option value="{{ $language }}" @selected($languageFilter === $language)>{{ $language }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Category
                        <select name="category">
                            <option value="all" @selected($categoryFilter === 'all')>All</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected($categoryFilter === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="button secondary" type="submit">Filter</button>
                </form>

                <form class="form-grid" method="post" action="{{ route('sls.intelligence.keywords.store') }}">
                    @csrf
                    <label>Term
                        <input name="term" required placeholder="e.g. pension reform">
                    </label>
                    <label>Focus
                        <select name="focus" required>
                            @foreach ($focuses as $key => $focus)
                                <option value="{{ $key }}" @selected($key === $focusFilter)>{{ $focus['label'] ?? $key }}</option>
                            @endforeach
                            <option value="all">All focuses</option>
                        </select>
                    </label>
                    <label>Language
                        <input name="language_code" value="en" required>
                    </label>
                    <label>Category
                        <input name="category" value="custom">
                    </label>
                    <button class="button" type="submit">Add term</button>
                </form>
            </section>

            <section class="panel">
                <p class="eyebrow">Directory</p>
                <h2>{{ $keywords->count() }} monitored terms</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Focus</th>
                                <th>Term</th>
                                <th>Language</th>
                                <th>Category</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($keywords as $keyword)
                                <tr>
                                    <td><span class="pill {{ $keyword->is_enabled ? 'good' : 'bad' }}">{{ $keyword->is_enabled ? 'Enabled' : 'Disabled' }}</span></td>
                                    <td>{{ $focuses[$keyword->focus]['label'] ?? $keyword->focus }}</td>
                                    <td><strong>{{ $keyword->term }}</strong></td>
                                    <td>{{ $keyword->language_code }}</td>
                                    <td>{{ $keyword->category ?: 'uncategorized' }}</td>
                                    <td>
                                        <form class="inline-form" method="post" action="{{ route('sls.intelligence.keywords.toggle', $keyword) }}">
                                            @csrf
                                            <button class="button secondary" type="submit">{{ $keyword->is_enabled ? 'Disable' : 'Enable' }}</button>
                                        </form>
                                        <form class="inline-form" method="post" action="{{ route('sls.intelligence.keywords.delete', $keyword) }}">
                                            @csrf
                                            <button class="button secondary" type="submit">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection
