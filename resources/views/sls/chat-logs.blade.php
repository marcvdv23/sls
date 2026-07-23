@extends('sls.layouts.app')

@section('title', '1G-SLS Chat Logs')
@section('eyebrow', '1G-SLS')
@section('page_title', '1G-SLS Chat Logs')

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
                <p class="eyebrow">Answer Review</p>
                <h1>Chat Logs</h1>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('sls.chat') }}">Chatbox</a>
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
            </div>
        </header>

        @php($austinTz = 'America/Chicago')
        <main class="stack">
            @forelse ($logs as $log)
                <article class="card">
                    <h2>{{ $log->question }}</h2>
                    <div class="badge-row">
                        <span class="badge">{{ $log->created_at?->copy()->timezone($austinTz)->format('Y-m-d H:i') }} Austin time</span>
                        <span class="badge">AI: {{ strtoupper($log->ai_provider ?? 'n/a') }}</span>
                        <span class="badge">Language: {{ $log->answer_language ?? 'n/a' }}</span>
                        <span class="badge">Product: {{ $log->product?->name ?? 'All products' }}</span>
                    </div>
                    <p class="answer">{{ $log->answer }}</p>
                    <details>
                        <summary>Retrieval terms and source chunks</summary>
                        <p class="muted" style="margin-top:.75rem;">Terms: {{ collect($log->retrieval_terms ?? [])->implode(', ') ?: 'None recorded' }}</p>
                        <div class="stack" style="margin-top:.75rem;">
                            @foreach (($log->source_matches ?? []) as $match)
                                <div class="card">
                                    <h3>{{ $match['chunk_title'] ?? 'Unknown chunk' }}</h3>
                                    <p class="muted">Score {{ $match['score'] ?? 'n/a' }} | Chunk {{ $match['chunk_id'] ?? 'n/a' }} | {{ $match['citation_label'] ?? 'No citation' }}</p>
                                    <p class="muted">{{ $match['excerpt'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    </details>
                </article>
            @empty
                <section class="card">
                    <p class="muted">No chat answers have been logged yet. New questions will be logged from now on.</p>
                </section>
            @endforelse
        </main>
    </div>
@endsection