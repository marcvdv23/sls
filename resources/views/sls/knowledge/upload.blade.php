@extends('sls.layouts.app')

@section('title', 'Upload Knowledge Source')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Upload Knowledge Source')

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
                <p class="eyebrow">1G-SLS Knowledge Base</p>
                <h1>Upload Source</h1>
            </div>
            <a href="{{ route('sls.knowledge.index') }}">Back to knowledge base</a>
        </header>

        <main>
            <section class="panel">
                @php
                    $uploadDefaults = $uploadDefaults ?? null;
                    $selectedSourceType = old('source_type', $uploadDefaults['source_type'] ?? 'manual');
                    $selectedLanguage = old('language_code', $uploadDefaults['language_code'] ?? 'en');
                    $selectedProducts = collect(old('products', $uploadDefaults['products'] ?? []))->map(fn ($id) => (string) $id)->all();
                @endphp

                @if ($errors->any())
                    <div class="error">
                        <p>Please fix the highlighted upload details.</p>
                    </div>
                @endif

                @if ($uploadDefaults)
                    <p class="hint">
                        Defaults copied from previous upload: {{ $uploadDefaults['based_on_title'] }}.
                        <a href="{{ route('sls.knowledge.upload', ['blank' => 1]) }}">Start blank</a>
                    </p>
                @endif

                <form method="post" action="{{ route('sls.knowledge.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="form-grid">
                        <label>
                            <span>Title prefix (optional)</span>
                            <input name="title" value="{{ old('title', '') }}" placeholder="Leave blank to use filenames">
                        </label>

                        <label>
                            <span>Source type</span>
                            <select name="source_type" required>
                                <option value="manual" @selected($selectedSourceType === 'manual')>Manual</option>
                                <option value="email" @selected($selectedSourceType === 'email')>Email</option>
                                <option value="email_attachment" @selected($selectedSourceType === 'email_attachment')>Email attachment</option>
                                <option value="rfp" @selected($selectedSourceType === 'rfp')>Prior RFP</option>
                                <option value="brochure" @selected($selectedSourceType === 'brochure')>Brochure</option>
                                <option value="implementation_note" @selected($selectedSourceType === 'implementation_note')>Implementation note</option>
                                <option value="security_document" @selected($selectedSourceType === 'security_document')>Security document</option>
                                <option value="webinar_transcript" @selected($selectedSourceType === 'webinar_transcript')>Webinar Transcript</option>
                                <option value="product_demo_transcript" @selected($selectedSourceType === 'product_demo_transcript')>Product Demo Transcript</option>
                                <option value="country_specific_information" @selected($selectedSourceType === 'country_specific_information')>Country Specific Information</option>
                                <option value="organization_specific_information" @selected($selectedSourceType === 'organization_specific_information')>Organization Specific Information</option>
                            </select>
                        </label>
                    </div>

                    <div class="meta-grid">
                        <label>
                            <span>Language</span>
                            <select name="language_code" required>
                                <option value="en" @selected($selectedLanguage === 'en')>English</option>
                                <option value="fr" @selected($selectedLanguage === 'fr')>French</option>
                                <option value="es" @selected($selectedLanguage === 'es')>Spanish</option>
                                <option value="pt" @selected($selectedLanguage === 'pt')>Portuguese</option>
                            </select>
                        </label>

                        <label>
                            <span>Source date</span>
                            <input type="date" name="source_date" value="{{ old('source_date', $uploadDefaults['source_date'] ?? '') }}">
                        </label>

                        <div class="file-field">
                            <span>Files</span>
                            <span class="file-control">
                                <button class="file-button" type="button">Choose Files</button>
                                <span class="file-name" id="selected-file-name">No files selected</span>
                                <input id="source-file" type="file" name="source_files[]" accept=".pdf,.txt,.csv,.md,.html,.htm,.eml,.docx,.xlsx" multiple required>
                            </span>
                            <span class="file-note">Select all parts at once in order. Each file becomes its own source document named from the filename; missing part numbers are added automatically.</span>
                        </div>
                    </div>

                    <fieldset>
                        <legend>Products</legend>
                        <div class="checks">
                            @foreach ($products as $product)
                                <label>
                                    <input type="checkbox" name="products[]" value="{{ $product->id }}" @checked(in_array((string) $product->id, $selectedProducts, true))>
                                    <span>{{ $product->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="upload-row">
                        <span class="hint">Review the settings, choose one or more files, then upload. Leave title blank to name sources from filenames.</span>
                        <button type="submit">Upload and chunk</button>
                    </div>
                </form>
            </section>
        </main>
        <script>
            const fileInput = document.getElementById('source-file');
            const fileName = document.getElementById('selected-file-name');
            const fileButton = document.querySelector('.file-button');

            const openFilePicker = (event) => {
                event.preventDefault();
                event.stopPropagation();
                fileInput.click();
            };

            fileButton.addEventListener('click', openFilePicker);
            fileName.addEventListener('click', openFilePicker);

            fileInput.addEventListener('change', () => {
                const files = Array.from(fileInput.files);

                if (files.length === 0) {
                    fileName.textContent = 'No files selected';
                } else if (files.length === 1) {
                    fileName.textContent = files[0].name;
                } else {
                    fileName.textContent = files.length + ' files: ' + files.map((file) => file.name).join(', ');
                }

                fileName.style.color = fileInput.files.length > 0 ? '#172033' : '#647084';
            });
        </script>
    </div>
@endsection
