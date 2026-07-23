@extends('sls.layouts.app')

@section('title', ($journalist->name ?: 'Journalist profile') . ' - 1G-SLS')
@section('eyebrow', 'Intelligence / Journalists')
@section('page_title', $journalist->name ?: 'Unknown journalist')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.journalists.index') }}">Journalist list</a>
    <a class="button secondary" href="{{ route('sls.intelligence.review') }}">Review Desk</a>
@endsection

@push('head')
    <style>
        .journalist-profile-grid { display:grid; grid-template-columns:minmax(280px, 420px) minmax(0, 1fr); gap:1rem; align-items:start; }
        .journalist-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.7rem; }
        .journalist-form-grid .wide { grid-column:1 / -1; }
        .journalist-article { border-top:1px solid var(--line); padding-top:.85rem; margin-top:.85rem; }
        .journalist-article:first-child { border-top:0; padding-top:0; margin-top:0; }
        @media (max-width: 960px) {
            .journalist-profile-grid, .journalist-form-grid { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <p class="muted" style="margin-top:-.5rem;">{{ $journalist->publication_name ?: 'Publication not captured yet' }}{{ $journalist->publicationCountry?->name || $journalist->publication_country_name ? ' | ' . ($journalist->publicationCountry?->name ?: $journalist->publication_country_name) : '' }}</p>

    <div class="journalist-profile-grid">
        <section class="panel">
            <p class="eyebrow">Profile</p>
            <form method="post" action="{{ route('sls.intelligence.journalists.update', $journalist) }}">
                @csrf
                @method('PATCH')
                <div class="journalist-form-grid">
                    <div>
                        <label>Name</label>
                        <input name="name" value="{{ $journalist->name }}">
                    </div>
                    <div>
                        <label>Email</label>
                        <input name="email" value="{{ $journalist->email }}" placeholder="name@publication.com">
                    </div>
                    <div>
                        <label>Publication</label>
                        <input name="publication_name" value="{{ $journalist->publication_name }}">
                    </div>
                    <div>
                        <label>Publication country</label>
                        <input name="publication_country_name" value="{{ $journalist->publicationCountry?->name ?: $journalist->publication_country_name }}">
                    </div>
                    <div class="wide">
                        <label>Topics</label>
                        <input name="topics" value="{{ implode(', ', $journalist->topics ?? []) }}">
                    </div>
                    <div class="wide">
                        <label>Profile summary</label>
                        <textarea name="profile_summary" placeholder="Short profile of what this journalist tends to cover.">{{ $journalist->profile_summary }}</textarea>
                    </div>
                    <div class="wide">
                        <label>Background</label>
                        <textarea name="background" placeholder="Professional background, beats, prior roles, awards, social links, etc.">{{ $journalist->background }}</textarea>
                    </div>
                    <div class="wide">
                        <label>Education</label>
                        <textarea name="education" placeholder="Education or training details as we find them.">{{ $journalist->education }}</textarea>
                    </div>
                    <div class="wide">
                        <label>Internal notes</label>
                        <textarea name="notes" placeholder="Anything useful before outreach.">{{ $journalist->notes }}</textarea>
                    </div>
                </div>
                <div class="toolbar" style="margin-top:.75rem;">
                    <button class="button" type="submit">Save profile</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <p class="eyebrow">Articles of note</p>
            @forelse ($journalist->articles as $article)
                <article class="journalist-article">
                    <h2>
                        @if ($article->article_url)
                            <a href="{{ $article->article_url }}" target="_blank" rel="noreferrer">{{ $article->article_title ?: 'Open article' }}</a>
                        @else
                            {{ $article->article_title ?: 'Untitled article' }}
                        @endif
                    </h2>
                    <p class="muted">
                        {{ $article->publication_name ?: $journalist->publication_name ?: 'Unknown publication' }}
                        @if ($article->countryUpdate?->country)
                            | {{ $article->countryUpdate->country->name }}
                        @endif
                        @if ($article->captured_at)
                            | captured {{ $article->captured_at->format('Y-m-d') }}
                        @endif
                    </p>
                    <p style="margin-top:.4rem;">
                        @foreach (($article->topics ?? []) as $topic)
                            <span class="pill">{{ $topic }}</span>
                        @endforeach
                    </p>
                    <form method="post" action="{{ route('sls.intelligence.journalistArticles.update', $article) }}" style="margin-top:.7rem;">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="journalist_id" value="{{ $journalist->id }}">
                        <div class="journalist-form-grid">
                            <div>
                                <label>Outreach status</label>
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
                </article>
            @empty
                <p class="muted">No articles have been attached yet.</p>
            @endforelse
        </section>
    </div>
@endsection
