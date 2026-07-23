@extends('sls.layouts.app')

@section('title', 'Document Intake')
@section('eyebrow', 'Knowledge')
@section('page_title', 'Document Intake')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.knowledge.index') }}">Document library</a>
    <a class="button secondary" href="{{ route('sls.intelligence.contacts') }}">Source contacts</a>
@endsection

@push('head')
    <style>
        .intake-form { display: grid; gap: 12px; }
        .intake-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; align-items: end; }
        .intake-grid .wide { grid-column: span 2; }
        .intake-grid .full { grid-column: 1 / -1; }
        .intake-form label span,
        .intake-form .field-label { color: var(--text-secondary); display: block; font-size: 12px; font-weight: 800; margin-bottom: 4px; }
        .intake-help { color: var(--text-secondary); font-size: 12px; margin-top: 4px; }
        .product-checks { display: flex; flex-wrap: wrap; gap: 8px 14px; }
        .product-checks label { align-items: center; display: inline-flex; gap: 6px; white-space: nowrap; }
        .product-checks input { width: auto; }
        .file-note { color: var(--text-secondary); font-size: 12px; margin-top: 4px; }
        .compact-actions { display: flex; gap: 8px; justify-content: flex-end; }
        @media (max-width: 1100px) {
            .intake-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 680px) {
            .intake-grid { grid-template-columns: 1fr; }
            .intake-grid .wide { grid-column: auto; }
        }
    </style>
@endpush

@section('content')
    <section class="panel">
        @if ($errors->any())
            <div class="badge badge-warning">Please fix the highlighted intake details.</div>
        @endif

        <form class="intake-form" method="post" action="{{ route('sls.documents.intake.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="intake-grid">
                <label class="wide">
                    <span>Document title / batch label</span>
                    <input name="title" value="{{ old('title') }}" placeholder="Optional, otherwise filenames are used">
                </label>

                <label>
                    <span>Document category</span>
                    <select name="intake_category" required>
                        @foreach ($options['categories'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('intake_category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Action</span>
                    <select name="intake_action" required>
                        @foreach ($options['actions'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('intake_action', 'searchable_and_extract') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="intake-help">Choose an extraction action when the document contains names or emails.</span>
                </label>

                <label>
                    <span>Contact classification</span>
                    <select name="contact_relationship_type">
                        <option value="">Not extracting contacts</option>
                        @foreach ($options['relationships'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('contact_relationship_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('contact_relationship_type')
                        <span class="intake-help">{{ $message }}</span>
                    @enderror
                </label>

                <label>
                    <span>Source type</span>
                    <select name="source_type">
                        <option value="">Auto from category</option>
                        @foreach ($options['sourceTypes'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('source_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Language</span>
                    <select name="language_code" required>
                        @foreach ($options['languages'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('language_code', 'en') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Related country</span>
                    <select name="related_country_id">
                        <option value="">None / mixed</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}" @selected((string) old('related_country_id') === (string) $country->id)>
                                {{ $country->name }} ({{ $country->iso_code }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Source date</span>
                    <input type="date" name="source_date" value="{{ old('source_date') }}">
                </label>

                <label class="wide">
                    <span>Related organization</span>
                    <input name="related_organization_name" value="{{ old('related_organization_name') }}" placeholder="Optional account, ministry, fund, company, or institution">
                </label>

                <label class="wide">
                    <span>Source URL</span>
                    <input type="url" name="source_url" value="{{ old('source_url') }}" placeholder="Optional original web page or download URL">
                </label>

                <label class="full">
                    <span>Files</span>
                    <input type="file" name="source_files[]" accept=".pdf,.txt,.csv,.md,.html,.htm,.eml,.docx,.xlsx" multiple required>
                    <div class="file-note">Supported now: PDF, DOCX, XLSX, TXT, CSV, MD, HTML, HTM, and EML. Each file is stored as its own source document.</div>
                </label>

                <div class="full">
                    <span class="field-label">Product tags</span>
                    <div class="product-checks">
                        @foreach ($products as $product)
                            <label>
                                <input type="checkbox" name="products[]" value="{{ $product->id }}" @checked(in_array((string) $product->id, collect(old('products', []))->map(fn ($id) => (string) $id)->all(), true))>
                                <span>{{ $product->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <label class="full">
                    <span>Notes</span>
                    <textarea name="intake_notes" rows="3" placeholder="Why this was uploaded, how to use it, or what to verify later">{{ old('intake_notes') }}</textarea>
                </label>
            </div>

            <div class="compact-actions">
                <a class="button secondary" href="{{ route('sls.knowledge.index') }}">Cancel</a>
                <button class="button" type="submit">Upload document(s)</button>
            </div>
        </form>
    </section>
@endsection
