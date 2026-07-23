@extends('sls.layouts.app')

@section('title', 'Opportunity Draft - 1G-SLS')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Opportunity Draft')

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
        .draft-workspace {
            max-width: min(820px, 50vw);
            width: 100%;
        }
        .email-draft-editor {
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            color: var(--text-primary);
            font: 15px/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            min-height: 340px;
            padding: 12px;
            resize: vertical;
            white-space: pre-wrap;
            width: 100%;
        }
        .draft-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        @media (max-width: 980px) {
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
            .draft-workspace { max-width: 100%; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page">
<header>
            <div>
                <p class="eyebrow">SSAS Opportunity Draft</p>
                <h1>{{ $opportunity->issue_area }}</h1>
            </div>
            <div>
                <a class="button secondary" href="{{ route('sls.opportunities.index') }}">All opportunities</a>
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
                <article class="panel">
                    <p class="eyebrow">Source Story</p>
                    <h2>{{ $opportunity->countryUpdate?->title_english ?: $opportunity->countryUpdate?->title }}</h2>
                    <p class="muted">{{ $opportunity->countryUpdate?->country?->name ?? 'Unknown country' }} | {{ $opportunity->countryUpdate?->publication_date?->toDateString() ?? 'No date captured' }}</p>
                    <p class="muted" style="margin-top: .75rem;">{{ $opportunity->countryUpdate?->summary }}</p>
                    @if ($opportunity->countryUpdate?->source_url)
                        <p style="margin-top: .75rem;"><a href="{{ $opportunity->countryUpdate->source_url }}" target="_blank" rel="noreferrer">Open original story</a></p>
                    @endif
                </article>
                <article class="panel">
                    <p class="eyebrow">Interact SSAS Fit</p>
                    <h2>{{ $opportunity->product?->name ?? 'Interact SSAS' }}</h2>
                    <p class="muted">{{ $opportunity->product_alignment }}</p>
                </article>
            </section>

            <section class="panel">
                <p class="eyebrow">Issue Summary</p>
                <p>{{ $opportunity->issue_summary }}</p>
            </section>

            <section class="panel draft-workspace">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <div>
                        <p class="eyebrow">Suggested Email Draft</p>
                        <p class="muted" style="margin:0 0 10px;">Generated with Gemini when available; otherwise SLS keeps the existing fallback draft.</p>
                    </div>
                    <form method="post" action="{{ route('sls.opportunities.email.regenerate', $opportunity) }}">
                        @csrf
                        <button class="button" type="submit">Regenerate AI draft</button>
                    </form>
                </div>
                <form method="post" action="{{ route('sls.opportunities.email.update', $opportunity) }}">
                    @csrf
                    <textarea class="email-draft-editor" name="suggested_email">{{ $opportunity->suggested_email }}</textarea>
                    <div class="draft-actions">
                        <button class="button primary" type="submit">Save draft</button>
                    </div>
                </form>
            </section>

            <section class="grid">
                <article class="panel">
                    <p class="eyebrow">Approved Knowledge Used</p>
                    @forelse ($knowledgeChunks as $chunk)
                        <div class="card" style="margin-top: .75rem;">
                            <h3>{{ $chunk->chunk_title }}</h3>
                            <p class="muted">{{ \Illuminate\Support\Str::limit($chunk->chunk_text, 420) }}</p>
                            <p class="muted">{{ $chunk->citation_label }}</p>
                        </div>
                    @empty
                        <p class="muted">No approved text chunks were matched yet.</p>
                    @endforelse
                </article>
                <article class="panel">
                    <p class="eyebrow">Screenshots / Demo Moments</p>
                    @if ($demoFrames->isNotEmpty())
                        <div class="visual-grid">
                            @foreach ($demoFrames as $frame)
                                <a href="{{ route('sls.demo-media.frames.show', $frame) }}" target="_blank" rel="noopener">
                                    <img src="{{ route('sls.demo-media.frames.show', $frame) }}" alt="Demo screenshot at {{ round($frame->timestamp_ms / 1000, 1) }} seconds">
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="muted">No approved demo screenshots are linked yet. After the MP4 is processed, relevant screenshots can appear here.</p>
                    @endif
                    @foreach ($demoMoments as $moment)
                        <div class="card" style="margin-top: .75rem;">
                            <h3>{{ $moment->feature_name }}</h3>
                            <p class="muted">{{ $moment->summary }}</p>
                        </div>
                    @endforeach
                </article>
            </section>
        </main>
    </div>
@endsection
