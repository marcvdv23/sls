@extends('sls.layouts.app')

@section('title', '1G-SLS Knowledge')
@section('eyebrow', '1G-SLS')
@section('page_title', '1G-SLS Knowledge')

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
                <p class="eyebrow">1G-SLS</p>
                <h1>Knowledge Base</h1>
            </div>
            <div class="badge-row">
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
                <a class="button" href="{{ route('sls.chat') }}">Ask chatbox</a>
                <a class="button" href="{{ route('sls.documents.intake') }}">Document intake</a>
                <a class="button" href="{{ route('sls.knowledge.upload') }}">Upload source</a>
            </div>
        </header>

        <main>
            @if (session('status'))
                <section class="panel" style="margin-bottom: 1rem;">
                    <p>{{ session('status') }}</p>
                </section>
            @endif

            <section class="panel">
                <form method="get" action="{{ route('sls.knowledge.index') }}" class="filters" style="margin-bottom: 12px;">
                    <label style="flex:1; min-width: 260px;">
                        <span>Search document library</span>
                        <input name="q" value="{{ $query ?? '' }}" placeholder="Search title, filename, notes, organization, or document content">
                    </label>
                    <button class="button" type="submit">Search</button>
                    @if (! empty($query))
                        <a class="button secondary" href="{{ route('sls.knowledge.index') }}">Clear</a>
                    @endif
                </form>

                <div class="summary-grid" aria-label="Knowledge base summary">
                    <div class="summary-card">
                        <p class="summary-label">Total documents</p>
                        <p class="summary-value">{{ $documentCount }}</p>
                    </div>
                    <div class="summary-card">
                        <p class="summary-label">Total chunks</p>
                        <p class="summary-value">{{ $chunkCount }}</p>
                    </div>
                    <div class="summary-card">
                        <p class="summary-label">Chunks approved</p>
                        <p class="summary-value">{{ $approvedChunkCount }}</p>
                    </div>
                    <div class="summary-card pending">
                        <p class="summary-label">Pending approval</p>
                        <p class="summary-value">{{ $pendingChunkCount }}</p>
                    </div>
                </div>
                <div class="stack" style="margin-top: 1rem;">
                    @forelse ($documents as $document)
                        <article class="card {{ $document->pending_chunks_count > 0 ? 'needs-review' : '' }}">
                            <h2><a href="{{ route('sls.knowledge.show', $document) }}">{{ $document->title }}</a></h2>
                            <p class="muted">{{ $document->original_filename }} · {{ $document->source_type }}</p>
                            @if ($document->intake_category || $document->related_organization_name)
                                <p class="muted">
                                    {{ $document->intake_category ? str($document->intake_category)->replace('_', ' ')->title() : '' }}
                                    @if ($document->related_organization_name)
                                        {{ $document->intake_category ? '-' : '' }} {{ $document->related_organization_name }}
                                    @endif
                                </p>
                            @endif
                            <div class="badge-row">
                                <span class="badge">{{ strtoupper($document->language_code ?? 'n/a') }}</span>
                                <span class="badge">source status: {{ $document->approval_status }}</span>
                                <span class="badge">{{ $document->chunks_count }} total chunks</span>
                                <span class="badge approved">{{ $document->approved_chunks_count }} approved</span>
                                <span class="badge pending">{{ $document->pending_chunks_count }} pending approval</span>
                                @if ($document->rejected_chunks_count > 0)
                                    <span class="badge rejected">{{ $document->rejected_chunks_count }} rejected</span>
                                @endif
                                @foreach ($document->products as $product)
                                    <span class="badge">{{ $product->name }}</span>
                                @endforeach
                            </div>
                            <div class="badge-row">
                                <a class="button secondary" href="{{ route('sls.knowledge.coverage', $document) }}">Coverage report</a>
                                <a class="button secondary" href="{{ route('sls.knowledge.show', $document) }}">Review chunks</a>
                            </div>
                        </article>
                    @empty
                        <p class="muted">No product sources uploaded yet.</p>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
@endsection
