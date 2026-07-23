@extends('sls.layouts.app')

@section('title', '{{ $document->title }} Coverage')
@section('eyebrow', '1G-SLS')
@section('page_title', '{{ $document->title }} Coverage')

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
                <p class="eyebrow">Extraction Coverage</p>
                <h1>{{ $document->title }}</h1>
                <p class="muted">{{ $document->original_filename }}</p>
                <div class="badge-row">
                    @foreach ($document->products as $product)
                        <span class="badge">{{ $product->name }}</span>
                    @endforeach
                    <span class="badge">{{ strtoupper($document->language_code ?? 'n/a') }}</span>
                    <span class="badge">{{ $document->source_type }}</span>
                </div>
            </div>
            <div class="badge-row">
                <a class="button secondary" href="{{ route('sls.knowledge.show', $document) }}">Review chunks</a>
                <a class="button secondary" href="{{ route('sls.knowledge.index') }}">Knowledge base</a>
                <a class="button" href="{{ route('sls.chat') }}">Ask chatbox</a>
            </div>
        </header>

        <main class="stack">
            <section class="grid">
                <article class="card">
                    <p class="muted">Chunks</p>
                    <p class="metric">{{ $chunkCount }}</p>
                </article>
                <article class="card">
                    <p class="muted">Approved chunks</p>
                    <p class="metric">{{ $approvedCount }}</p>
                </article>
                <article class="card">
                    <p class="muted">Approx. words</p>
                    <p class="metric">{{ number_format($wordCount) }}</p>
                </article>
                <article class="card">
                    <p class="muted">Avg. words/chunk</p>
                    <p class="metric">{{ number_format($averageWords) }}</p>
                </article>
            </section>

            <section class="panel">
                <h2>Extraction Quality Signals</h2>
                <p class="muted">These are warning signs from PDF text extraction. A few are normal; high counts mean the document may need a cleaner PDF, Word file, transcript, or manual correction.</p>
                <div class="badge-row">
                    @foreach ($artifactPatterns as $label => $count)
                        <span class="badge {{ $count > 0 ? 'warn' : 'ok' }}">{{ $label }}: {{ number_format($count) }}</span>
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <h2>Chunk Coverage</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Chunk</th>
                            <th>Status</th>
                            <th>Words</th>
                            <th>Artifacts</th>
                            <th>Preview</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($chunkStats as $stat)
                            <tr>
                                <td>{{ $stat['chunk']->chunk_title }}</td>
                                <td>{{ $stat['chunk']->approval_status }}</td>
                                <td>{{ number_format($stat['words']) }}</td>
                                <td>{{ number_format($stat['artifact_count']) }}</td>
                                <td class="muted">{{ $stat['preview'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </main>
    </div>
@endsection