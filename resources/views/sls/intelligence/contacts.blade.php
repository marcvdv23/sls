@extends('sls.layouts.app')

@section('title', 'Intelligence Contacts')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'Extracted Contact Directory')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.review', ['focus' => 'all', 'region' => 'all']) }}">Tenders and research</a>
    <a class="button secondary" href="{{ route('sls.intelligence.sources') }}">Sources</a>
@endsection

@push('head')
    <style>
        .contact-filters { display:grid; grid-template-columns:minmax(260px, 1fr) 14rem auto auto; gap:10px; align-items:end; }
        .contacts-table { min-width:1500px; table-layout:fixed; }
        .document-filter-panel {
            display:grid;
            grid-template-columns:minmax(0, 1fr) auto;
            gap:12px;
            align-items:center;
            padding:14px 16px;
            border:1px solid var(--accent-primary);
            border-radius:var(--radius-card);
            background:color-mix(in srgb, var(--accent-primary) 8%, var(--bg-secondary));
        }
        .document-filter-panel h2 { margin:3px 0 0; font-size:18px; }
        .document-filter-panel p { margin:2px 0 0; }
        .email-col { width:19rem; }
        .country-col { width:9rem; }
        .org-col { width:19rem; }
        .person-col { width:16rem; }
        .title-col { width:28rem; }
        .context-col { width:38rem; }
        .origin-col { width:12rem; }
        .source-col { width:14rem; }
        .cell-text,
        .cell-title {
            display:-webkit-box;
            -webkit-box-orient:vertical;
            overflow:hidden;
            overflow-wrap:anywhere;
            line-height:1.32;
        }
        .cell-text { -webkit-line-clamp:4; }
        .cell-title { -webkit-line-clamp:3; }
        .copy-row { display:flex; gap:7px; align-items:flex-start; min-width:0; }
        .copy-value { overflow-wrap:anywhere; min-width:0; }
        .copy-button { flex:0 0 auto; }
        .copy-button.copied { border-color:var(--accent-success); color:var(--accent-success); }
        @media (max-width:980px) {
            .contact-filters { grid-template-columns:1fr; }
            .contacts-table { min-width:900px; }
        }
    </style>
@endpush

@section('content')
    <div class="stack">
        <section class="panel stack">
            <p class="muted">Emails extracted from tender PDFs and source documents are stored with source traceability, so you can see where each address came from later.</p>
            @if (! empty($sourceDocument))
                <div class="document-filter-panel">
                    <div>
                        <p class="eyebrow">Filtered Contact View</p>
                        <h2>Only showing contacts extracted from intake document #{{ $sourceDocument->id }}</h2>
                        <p><strong>{{ $sourceDocument->title }}</strong></p>
                        @if ($sourceDocument->original_filename)
                            <p class="muted">Uploaded file: {{ $sourceDocument->original_filename }}</p>
                        @endif
                    </div>
                    <div class="button-row">
                        <a class="button secondary" href="{{ route('sls.knowledge.show', $sourceDocument) }}">Open intake document</a>
                        <form method="post" action="{{ route('sls.knowledge.enrich_competitors', $sourceDocument) }}">
                            @csrf
                            <button class="button" type="submit">Enrich competitor accounts</button>
                        </form>
                        <a class="button secondary" href="{{ route('sls.intelligence.contacts') }}">Show all contacts</a>
                    </div>
                </div>
            @endif
            <form class="contact-filters" method="get" action="{{ route('sls.intelligence.contacts') }}">
                @if (! empty($sourceDocumentId))
                    <input type="hidden" name="source_document_id" value="{{ $sourceDocumentId }}">
                @endif
                <input name="q" value="{{ $query }}" placeholder="Search email, organization, title, context">
                <input name="country" value="{{ $country }}" placeholder="Country">
                <button type="submit">Search</button>
                <a class="button secondary" href="{{ route('sls.intelligence.contacts') }}">Clear</a>
            </form>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Contacts</p>
                <h2>{{ $contacts->count() }} email reference(s) shown</h2>
            </div>
            <div class="table-wrap">
                <table class="data-table contacts-table">
                    <thead>
                        <tr>
                            <th class="email-col">Email</th>
                            <th class="country-col">Country</th>
                            <th class="org-col">Organization</th>
                            <th class="person-col">Person / title</th>
                            <th class="title-col">Source item</th>
                            <th class="context-col">Context</th>
                            <th class="origin-col">Origin</th>
                            <th class="source-col">Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contacts as $contact)
                            <tr>
                                <td>
                                    <div class="copy-row">
                                        <span class="copy-value">{{ $contact->email }}</span>
                                        <button class="secondary tiny copy-button" type="button" title="Copy email" data-copy-text="{{ $contact->email }}">Copy</button>
                                    </div>
                                </td>
                                <td>{{ $contact->country?->name ?? 'Unknown' }}</td>
                                <td><span class="cell-text">{{ $contact->organization ?: $contact->source_name ?: 'Unknown' }}</span></td>
                                <td>
                                    <strong>{{ $contact->person_name ?: 'Not identified' }}</strong>
                                    <p class="muted">{{ $contact->job_title ?: 'Title not identified' }}</p>
                                </td>
                                <td><span class="cell-title">{{ $contact->document_title ?: $contact->countryUpdate?->title }}</span></td>
                                <td class="muted"><span class="cell-text">{{ $contact->context_excerpt }}</span></td>
                                <td>
                                    @if ($contact->sourceDocument)
                                        <span class="badge">Document intake</span>
                                        <p class="muted">Doc #{{ $contact->sourceDocument->id }}</p>
                                    @elseif ($contact->countryUpdate)
                                        <span class="badge">Tender/source page</span>
                                        <p class="muted">INT-{{ str_pad((string) $contact->countryUpdate->id, 5, '0', STR_PAD_LEFT) }}</p>
                                    @else
                                        <span class="badge">Manual/imported</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($contact->countryUpdate)
                                        <p><a href="{{ route('sls.intelligence.updates.sourcePage', $contact->countryUpdate) }}">Open source view</a></p>
                                    @endif
                                    @if ($contact->sourceDocument)
                                        <p><a href="{{ route('sls.knowledge.show', $contact->sourceDocument) }}">Open intake document</a></p>
                                    @endif
                                    @if ($contact->document_url)
                                        <p><a href="{{ $contact->document_url }}" target="_blank" rel="noreferrer">Original document</a></p>
                                    @endif
                                    @if ($contact->intelligenceDocument?->storage_path)
                                        <p class="muted">Archived locally</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="muted">No extracted contacts match this search yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-copy-text]').forEach((button) => {
            button.addEventListener('click', async () => {
                await navigator.clipboard.writeText(button.dataset.copyText || '');
                button.classList.add('copied');
                setTimeout(() => button.classList.remove('copied'), 1200);
            });
        });
    </script>
@endpush
