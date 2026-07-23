@extends('sls.layouts.app')

@section('title', 'Upload Batch Review')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Upload Batch Review')

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
@php
            $totalChunks = $documents->sum(fn ($document) => $document->chunks->count());
            $approvedChunks = $documents->sum(fn ($document) => $document->chunks->where('approval_status', 'approved')->count());
            $unreviewedChunks = $documents->sum(fn ($document) => $document->chunks->where('approval_status', 'unreviewed')->count());
        @endphp

        <header>
            <div>
                <p class="eyebrow">Knowledge Upload Batch</p>
                <h1>{{ $documents->count() }} Source Documents</h1>
            </div>
            <div class="toolbar">
                <a class="button secondary" href="{{ route('sls.knowledge.index') }}">Knowledge base</a>
                <a class="button secondary" href="{{ route('sls.knowledge.upload') }}">Upload more</a>
                <a class="button" href="{{ route('sls.chat') }}">Test in chat</a>
            </div>
        </header>

        <main class="stack">
            @if (session('status'))
                <section class="panel">
                    <p>{{ session('status') }}</p>
                </section>
            @endif

            <section class="panel {{ $unreviewedChunks === 0 ? 'ready' : '' }}">
                <h2>Batch Approval</h2>
                <p class="muted">
                    Approved chunks are available to chat and proposal workflows. This batch has {{ $approvedChunks }} approved chunks and {{ $unreviewedChunks }} unreviewed chunks out of {{ $totalChunks }}.
                </p>
                <div class="toolbar">
                    <form method="post" action="{{ route('sls.knowledge.batch.approve_all') }}">
                        @csrf
                        <input type="hidden" name="sources" value="{{ $sourceIds }}">
                        <button class="button" type="submit" @disabled($unreviewedChunks === 0)>Approve all chunks in batch</button>
                    </form>
                </div>
            </section>

            <section class="grid">
                @foreach ($documents as $document)
                    @php
                        $approvedCount = $document->chunks->where('approval_status', 'approved')->count();
                        $unreviewedCount = $document->chunks->where('approval_status', 'unreviewed')->count();
                    @endphp
                    <article class="card {{ $unreviewedCount === 0 ? 'ready' : '' }}">
                        <h2>{{ $document->title }}</h2>
                        <p class="muted">{{ $document->original_filename }}</p>
                        <div class="badge-row">
                            <span class="badge">{{ $document->chunks->count() }} chunks</span>
                            <span class="badge approved">{{ $approvedCount }} approved</span>
                            <span class="badge unreviewed">{{ $unreviewedCount }} unreviewed</span>
                            @foreach ($document->products as $product)
                                <span class="badge">{{ $product->name }}</span>
                            @endforeach
                        </div>
                        <div class="toolbar">
                            <a class="button secondary" href="{{ route('sls.knowledge.show', $document) }}">Review chunks</a>
                        </div>
                    </article>
                @endforeach
            </section>
        </main>
    </div>
@endsection