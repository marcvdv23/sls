@extends('sls.layouts.app')

@section('title', '{{ $batch->title }} - Directory Image Import')
@section('eyebrow', '1G-SLS')
@section('page_title', '{{ $batch->title }} - Directory Image Import')

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
                <p class="eyebrow">Directory Image Import</p>
                <h1>{{ $batch->title }}</h1>
                <p class="muted">{{ $batch->organization_type }} / {{ $batch->industry }}{{ $batch->organization_subcategory ? ' / ' . $batch->organization_subcategory : '' }} · {{ $batch->status }}</p>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('sls.directoryImages.index') }}">Image imports</a>
                <a class="button secondary" href="{{ route('sls.organizations.index') }}">Organization directory</a>
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
            </div>
        </header>
        <main class="stack">
            @if (session('status'))
                <section class="panel"><p class="muted">{{ session('status') }}</p></section>
            @endif
            <section class="summary">
                <div class="panel stat"><strong>{{ $batch->pages->count() }}</strong><span class="muted">images</span></div>
                <div class="panel stat"><strong>{{ $batch->entries->count() }}</strong><span class="muted">draft entries</span></div>
                <div class="panel stat"><strong>{{ $batch->entries->where('review_status', 'imported')->count() }}</strong><span class="muted">imported</span></div>
                <div class="panel stat"><strong>{{ $batch->entries->where('review_status', 'needs_review')->count() }}</strong><span class="muted">needs review</span></div>
            </section>
            <section class="panel">
                <div class="actions" style="justify-content:space-between;">
                    <div>
                        <p class="eyebrow">Process</p>
                        <h2>OCR and structure extraction</h2>
                        <p class="muted">This may take a few minutes for 50 images. Entries remain drafts until imported.</p>
                    </div>
                    <div class="actions">
                        <a class="button secondary" href="{{ route('sls.directoryImages.export', $batch) }}">Export CSV</a>
                        <form method="post" action="{{ route('sls.directoryImages.importCorrections', $batch) }}" enctype="multipart/form-data" class="actions">
                            @csrf
                            <input type="file" name="corrections" accept=".csv,text/csv" required style="max-width:260px;">
                            <button class="button secondary" type="submit">Import corrections</button>
                        </form>
                        <form method="post" action="{{ route('sls.directoryImages.process', $batch) }}">
                            @csrf
                            <button class="button" type="submit">Process OCR</button>
                        </form>
                    </div>
                </div>
            </section>
            <section class="panel">
                <p class="eyebrow">Draft Entries</p>
                <h2>Review extracted organizations</h2>
                <div class="table-wrap">
                    <table>
                        <colgroup>
                            <col style="width:7%;">
                            <col class="company-col">
                            <col class="country-col">
                            <col class="website-col">
                            <col class="contact-col">
                            <col class="text-col">
                            <col class="text-col">
                            <col style="width:4%;">
                            <col class="action-col">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="company-col">Company</th>
                                <th class="country-col">Country</th>
                                <th>Website</th>
                                <th>Email / phone</th>
                                <th>Executives</th>
                                <th>Address</th>
                                <th>Confidence</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($batch->entries as $entry)
                                <tr>
                                    <td><span class="pill">{{ $entry->review_status }}</span></td>
                                    <td><input form="entry-form-{{ $entry->id }}" name="organization_name" value="{{ $entry->organization_name }}"></td>
                                    <td>
                                            <input form="entry-form-{{ $entry->id }}" name="country_normalized" value="{{ $entry->country_normalized ?: $entry->country_raw }}">
                                            <span class="muted">Raw: {{ $entry->country_raw ?: 'not detected' }}</span>
                                    </td>
                                    <td><input form="entry-form-{{ $entry->id }}" name="website" value="{{ $entry->website }}"></td>
                                    <td>
                                            <input form="entry-form-{{ $entry->id }}" name="email" value="{{ $entry->email }}" placeholder="No email">
                                            <input form="entry-form-{{ $entry->id }}" name="phone" value="{{ $entry->phone }}" placeholder="No phone" style="margin-top:.35rem;">
                                    </td>
                                    <td><textarea form="entry-form-{{ $entry->id }}" name="executive_text">{{ $entry->executive_text }}</textarea></td>
                                    <td><textarea form="entry-form-{{ $entry->id }}" name="address_text">{{ $entry->address_text }}</textarea></td>
                                    <td>{{ $entry->confidence }}%</td>
                                    <td>
                                        <form id="entry-form-{{ $entry->id }}" method="post" action="{{ route('sls.directoryImageEntries.update', $entry) }}">
                                            @csrf
                                            @method('patch')
                                        </form>
                                        <div class="actions" style="gap:.25rem;">
                                            <button class="button small secondary" type="submit" form="entry-form-{{ $entry->id }}">Save</button>
                                            <form method="post" action="{{ route('sls.directoryImageEntries.import', $entry) }}">
                                                @csrf
                                                <button class="button small" type="submit">Import</button>
                                            </form>
                                        </div>
                                        <details style="margin-top:.5rem;">
                                            <summary class="muted">Raw OCR block</summary>
                                            <pre>{{ $entry->raw_text }}</pre>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="muted">No draft entries yet. Click Process OCR after uploading images.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="panel">
                <p class="eyebrow">Images</p>
                <h2>OCR source pages</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>File</th><th>Status</th><th>Error</th><th>OCR preview</th></tr></thead>
                        <tbody>
                            @foreach ($batch->pages as $page)
                                <tr>
                                    <td>{{ $page->original_filename }}</td>
                                    <td><span class="pill">{{ $page->status }}</span></td>
                                    <td>{{ $page->last_error ?: 'None' }}</td>
                                    <td><pre>{{ \Illuminate\Support\Str::limit($page->ocr_text, 1200) }}</pre></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection