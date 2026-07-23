@extends('sls.layouts.app')

@section('title', 'Journalist Directory')
@section('eyebrow', 'Intelligence')
@section('page_title', 'Journalist Directory')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.review') }}">Review Desk</a>
@endsection

@push('head')
    <style>
        .journalist-directory { display:grid; gap:1rem; }
        .journalist-card { display:grid; grid-template-columns:minmax(220px, 320px) minmax(0, 1fr); gap:1rem; }
        .journalist-meta { display:grid; gap:.55rem; align-content:start; }
        .journalist-article { border-top:1px solid var(--line); padding-top:.85rem; margin-top:.85rem; }
        .journalist-article:first-child { border-top:0; padding-top:0; margin-top:0; }
        .journalist-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.7rem; margin-top:.7rem; }
        .journalist-form-grid .wide { grid-column:1 / -1; }
        .journalist-search { display:flex; flex-wrap:wrap; gap:.5rem; align-items:end; }
        .journalist-search-field { min-width:min(100%, 420px); }
        .journalist-card h2, .journalist-card h3 { margin:0; }
        @media (max-width: 900px) {
            .journalist-card, .journalist-form-grid { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <section class="panel stack">
        <div>
            <p class="eyebrow">Tracked journalists</p>
            <h2>People writing about social security, public sector IT, and related topics</h2>
            <p class="muted">Use Add journalist from Review Desk to attach a story and start building a profile.</p>
        </div>
        <form class="journalist-search" method="get" action="{{ route('sls.intelligence.journalists.index') }}">
            <div class="journalist-search-field">
                <label for="q">Search</label>
                <input id="q" name="q" value="{{ $search }}" placeholder="Name, email, publication, or country">
            </div>
            <button class="button" type="submit">Search</button>
            @if ($search !== '')
                <a class="button secondary" href="{{ route('sls.intelligence.journalists.index') }}">Clear</a>
            @endif
        </form>
    </section>

    <section class="journalist-directory">
        @forelse ($journalists as $journalist)
            <article class="panel journalist-card">
                <div class="journalist-meta">
                    <div>
                        <p class="eyebrow">{{ str_replace('_', ' ', $journalist->discovery_status) }}</p>
                        <h2><a href="{{ route('sls.intelligence.journalists.show', $journalist) }}">{{ $journalist->name ?: 'Unknown journalist' }}</a></h2>
                        <p class="muted">{{ $journalist->publication_name ?: 'Publication not captured yet' }}</p>
                    </div>
                    <p><strong>Email:</strong> {{ $journalist->email ?: 'Not found yet' }}</p>
                    <p><strong>Country:</strong> {{ $journalist->publicationCountry?->name ?: $journalist->publication_country_name ?: 'Unknown' }}</p>
                    <p><strong>Stories tracked:</strong> {{ $journalist->articles_count }}</p>
                    @if ($journalist->profile_summary || $journalist->background || $journalist->education)
                        <p class="muted">{{ $journalist->profile_summary ?: trim(collect([$journalist->background, $journalist->education])->filter()->implode(' ')) }}</p>
                    @else
                        <p class="muted">Profile can start empty. Add background, education, and notes as we learn more.</p>
                    @endif
                    <p>
                        @foreach (($journalist->topics ?? []) as $topic)
                            <span class="pill">{{ $topic }}</span>
                        @endforeach
                    </p>
                    <a class="button secondary" href="{{ route('sls.intelligence.journalists.show', $journalist) }}">Open profile</a>
                </div>

                <div>
                    @foreach ($journalist->articles as $article)
                        <section class="journalist-article">
                            <h3>
                                @if ($article->article_url)
                                    <a href="{{ $article->article_url }}" target="_blank" rel="noreferrer">{{ $article->article_title ?: 'Open article' }}</a>
                                @else
                                    {{ $article->article_title ?: 'Untitled article' }}
                                @endif
                            </h3>
                            <p class="muted">
                                {{ $article->countryUpdate?->country?->name ?: 'Unknown country' }}
                                @if ($article->captured_at)
                                    | captured {{ $article->captured_at->format('Y-m-d') }}
                                @endif
                            </p>

                            <form method="post" action="{{ route('sls.intelligence.journalistArticles.update', $article) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="journalist_id" value="{{ $journalist->id }}">
                                <div class="journalist-form-grid">
                                    <div>
                                        <label>Status</label>
                                        <select name="outreach_status">
                                            @foreach (['draft', 'ready', 'contacted', 'replied', 'do_not_contact'] as $status)
                                                <option value="{{ $status }}" @selected($article->outreach_status === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="wide">
                                        <label>Editable praise note</label>
                                        <textarea name="praise_note">{{ $article->praise_note }}</textarea>
                                    </div>
                                </div>
                                <div class="toolbar" style="margin-top:.7rem;">
                                    <button class="button" type="submit">Save note</button>
                                    @if ($article->author_name_raw || $article->author_email_raw)
                                        <span class="muted">Detected: {{ collect([$article->author_name_raw, $article->author_email_raw])->filter()->implode(' | ') }}</span>
                                    @endif
                                </div>
                            </form>
                        </section>
                    @endforeach
                </div>
            </article>
        @empty
            <section class="panel">
                <p class="muted">No journalists have been tracked yet. Use Add journalist from Review Desk on a relevant story.</p>
            </section>
        @endforelse
    </section>

    <div class="pagination">
        {{ $journalists->links() }}
    </div>
@endsection
