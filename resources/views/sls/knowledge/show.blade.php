@extends('sls.layouts.app')

@section('title')
{{ $document->title }}
@endsection
@section('eyebrow', '1G-SLS')
@section('page_title')
{{ $document->title }}
@endsection

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
            $approvedCount = $document->chunks->where('approval_status', 'approved')->count();
            $rejectedCount = $document->chunks->where('approval_status', 'rejected')->count();
            $unreviewedCount = $document->chunks->where('approval_status', 'unreviewed')->count();
        @endphp

        <header>
            <p class="eyebrow">Knowledge Source</p>
            <h1>{{ $document->title }}</h1>
            <div class="badge-row">
                <span class="badge">{{ $document->source_type }}</span>
                <span class="badge">{{ strtoupper($document->language_code ?? 'n/a') }}</span>
                <span class="badge">{{ $document->approval_status }}</span>
                <span class="badge approved">{{ $approvedCount }} approved</span>
                <span class="badge">{{ $unreviewedCount }} unreviewed</span>
                <span class="badge rejected">{{ $rejectedCount }} rejected</span>
                @foreach ($document->products as $product)
                    <span class="badge">{{ $product->name }}</span>
                @endforeach
            </div>
        </header>

        <main>
            @if (session('status'))
                <section class="panel" style="margin-bottom: 1rem;">
                    <p>{{ session('status') }}</p>
                </section>
            @endif

            @if ($document->chunks->count() > 0 && $approvedCount === $document->chunks->count())
                <section class="panel ready" style="margin-bottom: 1rem;">
                    <h2>Ready for chat and proposals</h2>
                    <p class="muted">All {{ $approvedCount }} chunks from this source are approved. The approve buttons are disabled because there is nothing left to approve.</p>
                </section>
            @endif

            <section class="panel">
                <h2>Document Intake</h2>
                <div class="badge-row">
                    <span class="badge">{{ $document->intake_category ? str($document->intake_category)->replace('_', ' ')->title() : 'No intake category' }}</span>
                    <span class="badge">{{ $document->intake_action ? str($document->intake_action)->replace('_', ' ')->title() : 'No intake action' }}</span>
                    <span class="badge">{{ $document->intakeContacts->count() }} extracted contact(s)</span>
                    @if ($document->related_organization_name)
                        <span class="badge">{{ $document->related_organization_name }}</span>
                    @endif
                </div>
                <form class="toolbar js-action-form" method="post" action="{{ route('sls.knowledge.extract_contacts', $document) }}" style="margin-top:10px;">
                    @csrf
                    <label style="min-width:260px;">
                        <span>Classify contacts as</span>
                        <select name="contact_relationship_type" required>
                            @foreach (($contactRelationshipOptions ?? []) as $value => $label)
                                <option value="{{ $value }}" @selected(old('contact_relationship_type', $document->contact_relationship_type ?: 'prospect') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="button" type="submit" data-pending-label="Extracting contacts...">Extract contacts now</button>
                    <a class="button secondary" href="{{ route('sls.intelligence.contacts', ['source_document_id' => $document->id]) }}">View source contacts</a>
                </form>
                <form class="toolbar js-action-form" method="post" action="{{ route('sls.knowledge.enrich_competitors', $document) }}" style="margin-top:8px;">
                    @csrf
                    <button class="button secondary" type="submit" data-pending-label="Starting enrichment crawl...">Enrich competitor/bidder accounts</button>
                    <span class="muted">Use after extraction to crawl outside-company domains found in this document.</span>
                </form>
                <p class="muted">Use this when a document was uploaded for contact extraction or when the wrong intake action was selected during upload.</p>
            </section>

            <section class="panel">
                <h2>Extracted Chunks</h2>
                <p class="muted">Approved chunks are available to the chat and proposal workflows. Keep anything uncertain unreviewed until you are ready.</p>
                <div class="toolbar">
                    <form class="js-action-form" method="post" action="{{ route('sls.knowledge.approve_all', $document) }}">
                        @csrf
                        <button class="button" type="submit" data-pending-label="Approving all..." @disabled($unreviewedCount === 0 && $rejectedCount === 0)>Approve all chunks</button>
                    </form>
                    <form class="js-action-form" method="post" action="{{ route('sls.knowledge.reject_all', $document) }}">
                        @csrf
                        <button class="button danger" type="submit" data-pending-label="Rejecting all..." @disabled($unreviewedCount === 0 && $approvedCount === 0)>Reject all chunks</button>
                    </form>
                    <form class="js-action-form" method="get" action="{{ route('sls.chat') }}">
                        <button class="button secondary" type="submit" data-pending-label="Opening chat...">Test in chat</button>
                    </form>
                    <form class="js-action-form" method="get" action="{{ route('sls.knowledge.upload') }}">
                        <button class="button secondary" type="submit" data-pending-label="Opening upload...">Upload source</button>
                    </form>
                    <form class="js-action-form" method="get" action="{{ route('sls.knowledge.index') }}">
                        <button class="button secondary" type="submit" data-pending-label="Opening knowledge base...">Back to knowledge base</button>
                    </form>
                    <form class="js-action-form" method="get" action="{{ route('sls.knowledge.coverage', $document) }}">
                        <button class="button secondary" type="submit" data-pending-label="Opening coverage...">Coverage report</button>
                    </form>
                </div>
                <div class="stack">
                    @foreach ($document->chunks as $chunk)
                        <article class="card {{ $chunk->approval_status }}">
                            <h2>{{ $chunk->chunk_title }}</h2>
                            <p class="muted">{{ $chunk->chunk_text }}</p>
                            <div class="badge-row">
                                <span class="badge {{ $chunk->approval_status }}">{{ $chunk->approval_status }}</span>
                                <span class="badge">{{ $chunk->content_type ?? 'unclassified' }}</span>
                                <span class="badge">{{ $chunk->business_area ?? 'general' }}</span>
                                <span class="badge">priority {{ $chunk->answer_priority ?? 50 }}</span>
                                <span class="badge">{{ $chunk->citation_label }}</span>
                            </div>
                            <div class="badge-row">
                                <form class="js-action-form" method="post" action="{{ route('sls.knowledge.chunks.approve', $chunk) }}">
                                    @csrf
                                    <button class="button" type="submit" data-pending-label="Approving..." @disabled($chunk->approval_status === 'approved')>
                                        {{ $chunk->approval_status === 'approved' ? 'Approved' : 'Approve' }}
                                    </button>
                                </form>
                                <form class="js-action-form" method="post" action="{{ route('sls.knowledge.chunks.reject', $chunk) }}">
                                    @csrf
                                    <button class="button danger" type="submit" data-pending-label="Rejecting..." @disabled($chunk->approval_status === 'rejected')>
                                        {{ $chunk->approval_status === 'rejected' ? 'Rejected' : 'Reject' }}
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </main>
        <script>
            document.querySelectorAll('.js-action-form').forEach((form) => {
                form.addEventListener('submit', () => {
                    const button = form.querySelector('button[type="submit"]');

                    if (! button || button.disabled) {
                        return;
                    }

                    button.dataset.originalLabel = button.textContent.trim();
                    button.textContent = button.dataset.pendingLabel || 'Working...';
                    button.classList.add('is-submitting');
                    button.disabled = true;
                });
            });
        </script>
    </div>
@endsection
