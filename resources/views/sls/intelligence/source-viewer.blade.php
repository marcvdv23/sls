@extends('sls.layouts.app')

@section('title', 'Original Source')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Original Source')

@push('head')
    <style>
        .sls-main:has(.source-viewer-page) { max-width: none; }
        .source-viewer-page { display: grid; gap: 12px; min-height: calc(100vh - 112px); }
        .source-viewer-header,
        .source-workbench,
        .source-note,
        .tagged-orgs {
            background: var(--bg-secondary);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card);
            box-shadow: var(--card-shadow);
        }
        .source-viewer-header { display: grid; grid-template-columns: minmax(260px, 1fr) auto; gap: 14px; align-items: start; padding: 14px; }
        .source-viewer-header h1 { margin: 3px 0 0; max-width: 980px; }
        .source-title-link { color: var(--text-primary); text-decoration: none; }
        .source-title-link:hover { color: var(--accent); text-decoration: underline; }
        .source-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
        .source-workbench { display: grid; gap: 10px; padding: 12px 14px; }
        .source-workbench-grid { display: grid; grid-template-columns: minmax(210px, 0.9fr) minmax(210px, 0.9fr) minmax(260px, 1.1fr) minmax(300px, 1.2fr) minmax(280px, 1fr); gap: 14px; align-items: start; }
        .source-tool { display: grid; gap: 7px; align-content: start; }
        .source-tool label, .source-tool .tool-label { color: var(--text-secondary); font-size: 11px; font-weight: 900; letter-spacing: .04em; text-transform: uppercase; }
        .source-tool select, .source-tool input, .source-tool textarea {
            width: 100%;
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            padding: 9px 10px;
            font: inherit;
        }
        .source-tool textarea { min-height: 41px; resize: vertical; }
        .product-check-list { display: grid; gap: 6px; align-self: start; }
        .product-check-option { display: flex; align-items: center; gap: 7px; color: var(--text-primary); font-weight: 800; line-height: 1.2; }
        .product-check-option input { width: auto; padding: 0; }
        .product-map-stack { display: grid; gap: 8px; align-items: start; }
        .product-map-stack select[multiple] { min-height: 106px; padding: 7px 9px; }
        .product-map-actions { display: flex; align-items: center; gap: 10px; }
        .source-tool small { align-self: start; }
        .compact-row { display: flex; gap: 7px; align-items: center; min-height: 42px; }
        .compact-row > * { min-width: 0; }
        .compact-row select, .compact-row input { flex: 1; }
        .source-button-link { border: 0; background: transparent; color: var(--accent); cursor: pointer; font-weight: 900; padding: 0; text-align: left; }
        .source-button-link.danger { color: #dc2626; }
        .source-note { padding: 10px 14px; color: var(--text-secondary); }
        .tagged-orgs { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px 14px; }
        .org-chip { display: inline-flex; gap: 8px; align-items: center; border: 1px solid var(--border-subtle); border-radius: 999px; padding: 5px 9px; background: var(--bg-primary); }
        .org-chip form { display: inline; }
        .suggestion-box { position: relative; }
        .org-suggestions {
            display: none;
            position: absolute;
            z-index: 20;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            max-height: 260px;
            overflow: auto;
            background: var(--bg-secondary);
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            box-shadow: var(--card-shadow);
        }
        .org-suggestions.active { display: block; }
        .org-suggestion { display: block; width: 100%; border: 0; background: transparent; text-align: left; padding: 8px 10px; cursor: pointer; color: var(--text-primary); }
        .org-suggestion:hover { background: var(--bg-muted); }
        .org-suggestion small { display: block; color: var(--text-secondary); }
        @media (max-width: 780px) {
            .source-viewer-header { grid-template-columns: 1fr; }
            .source-actions { justify-content: flex-start; }
            .source-workbench-grid { grid-template-columns: 1fr; }
            .source-viewer-page .viewer { min-height: 620px; height: 70vh; }
        }
    </style>
@endpush

@section('content')
    @php
        $sourceUrl = (string) $update->source_url;
        $sourceHost = strtolower((string) parse_url($sourceUrl, PHP_URL_HOST));
        $isPdfProxy = $sourceHost === 'idbdocs.iadb.org' || str_ends_with(strtolower($sourceUrl), '.pdf');
        $sourceButtonLabel = $isPdfProxy ? 'Open PDF' : 'Open original';
        $title = $update->title_english ?: $update->title ?: $update->title_original;
        $selectedProductIds = collect(old('product_ids', $mappedProductIds))->map(fn ($id) => (string) $id)->all();
    @endphp

    <div class="source-viewer-page">
        <header class="source-viewer-header">
            <div>
                <p class="muted">{{ $update->country?->name ?? 'Country intelligence' }} | {{ $update->source_name ?: $sourceHost }}</p>
                <h1><a class="source-title-link source-action" href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer">{{ $title }}</a></h1>
                @if ($update->review_status === 'rejected')
                    <p class="muted"><strong>Dropped:</strong> {{ $update->rejection_reason_code ? str_replace('_', ' ', $update->rejection_reason_code) : 'No reason captured' }}</p>
                @elseif ($update->map_processed_at)
                    <p class="muted"><strong>Processed:</strong> {{ $update->map_action_status ? str_replace('_', ' ', $update->map_action_status) : 'reviewed' }}</p>
                @endif
            </div>
            <div class="source-actions">
                <button class="button secondary" type="button" onclick="window.print()">Print</button>
                <form method="post" action="{{ route('sls.intelligence.updates.extractContacts', $update) }}">
                    @csrf
                    <button class="button secondary" type="submit">Extract contacts</button>
                </form>
                <form method="post" action="{{ route('sls.intelligence.updates.checkAward', $update) }}">
                    @csrf
                    <button class="button secondary" type="submit">Check award</button>
                </form>
                <a class="button secondary" href="{{ route('sls.intelligence.awardedCompanies.create', ['country_update_id' => $update->id]) }}">Add awarded company</a>
                <a class="button secondary" href="{{ route('sls.intelligence.review', ['focus' => request('focus', 'all'), 'region' => request('region', 'all')]) }}">Back to review</a>
                <a class="button source-action" href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer">{{ $sourceButtonLabel }}</a>
            </div>
        </header>

        @if (session('status'))
            <p class="source-note">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="source-note" style="color:#dc2626;">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="source-workbench" aria-label="Source review actions">
            <div class="source-workbench-grid">
                <form class="source-tool" method="post" action="{{ route('sls.intelligence.updates.productMap', $update) }}">
                    @csrf
                    <span class="tool-label">Map to product</span>
                    <div class="product-map-stack">
                        @if (($mappingSelectorMode ?? 'multi_select_dropdown') === 'checkbox_list')
                            <div class="product-check-list" role="group" aria-label="Map story to products">
                                @foreach ($products as $product)
                                    @php($productValue = (string) $product->id)
                                    <label class="product-check-option">
                                        <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked(in_array($productValue, $selectedProductIds, true))>
                                        <span>{{ str_replace('Interact ', '', $product->name) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <select name="product_ids[]" multiple aria-label="Map story to products">
                                @foreach ($products as $product)
                                    @php($productValue = (string) $product->id)
                                    <option value="{{ $product->id }}" @selected(in_array($productValue, $selectedProductIds, true))>{{ str_replace('Interact ', '', $product->name) }}</option>
                                @endforeach
                            </select>
                        @endif
                        <div class="product-map-actions">
                            <button class="source-button-link" type="submit">Save</button>
                            <small class="muted">Replaces previous mappings.</small>
                        </div>
                    </div>
                </form>

                <form class="source-tool" method="post" action="{{ route('sls.intelligence.updates.drop', $update) }}">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ url()->full() }}">
                    <label for="reason_code">Drop</label>
                    <div class="compact-row">
                        <select id="reason_code" name="reason_code">
                            @foreach ($dropReasonOptions as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="source-button-link danger" type="submit">Drop</button>
                    </div>
                </form>

                <form class="source-tool" method="post" action="{{ route('sls.intelligence.updates.markRead', $update) }}">
                    @csrf
                    <label for="map_action_status">Close / action</label>
                    <div class="compact-row">
                        <select id="map_action_status" name="map_action_status">
                            <option value="no_action_required" @selected(old('map_action_status', $update->map_action_status ?: 'no_action_required') === 'no_action_required')>Read, no follow-up</option>
                            <option value="action_taken" @selected(old('map_action_status', $update->map_action_status) === 'action_taken')>Action taken</option>
                            <option value="follow_up" @selected(old('map_action_status', $update->map_action_status) === 'follow_up')>Follow-up needed</option>
                        </select>
                        <button class="source-button-link" type="submit">Save</button>
                    </div>
                    <input name="map_action_note" placeholder="Optional action note" value="{{ old('map_action_note', $update->map_action_note) }}">
                    @if ($update->map_action_status === 'follow_up')
                        <small class="muted">This item is in <a href="{{ route('sls.intelligence.favorites', ['status' => 'open']) }}">Favorites & Reminders</a>{{ $update->reminder_due_at ? ' for ' . $update->reminder_due_at->copy()->timezone('America/Chicago')->format('Y-m-d H:i') . ' Austin' : ' with no due date yet' }}.</small>
                    @endif
                </form>

                <form id="follow-up" class="source-tool" method="post" action="{{ route('sls.intelligence.updates.favorite', $update) }}">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ url()->full() }}">
                    <label for="reminder_due_at">Schedule follow-up</label>
                    <div class="compact-row">
                        <input id="reminder_due_at" type="datetime-local" name="reminder_due_at" value="{{ old('reminder_due_at', $update->reminder_due_at?->copy()->timezone('America/Chicago')->format('Y-m-d\TH:i')) }}">
                        <button class="source-button-link" type="submit">Save</button>
                    </div>
                    <input name="favorite_note" placeholder="Reminder note" value="{{ old('favorite_note', $update->favorite_note) }}">
                    <small class="muted">Saved follow-ups appear in <a href="{{ route('sls.intelligence.favorites', ['status' => 'open']) }}">Favorites & Reminders</a>.</small>
                </form>

                <form class="source-tool" method="post" action="{{ route('sls.intelligence.updates.organizations.store', $update) }}" id="organization-tag-form">
                    @csrf
                    <input type="hidden" name="market_organization_id" id="market_organization_id">
                    <input type="hidden" name="context" value="mentioned">
                    <span class="tool-label">Tag organization</span>
                    <div class="compact-row suggestion-box">
                        <input id="organization_name" name="organization_name" autocomplete="off" placeholder="Type organization name">
                        <button class="source-button-link" type="submit">Tag</button>
                        <div id="org-suggestions" class="org-suggestions" role="listbox"></div>
                    </div>
                    <small class="muted">Search defaults to {{ $update->country?->name ?? 'known country' }}; new names are created if no match is chosen.</small>
                </form>
            </div>
        </section>

        @if ($update->organizations->isNotEmpty() || $update->intelligenceContacts->isNotEmpty() || $update->intelligenceDocuments->isNotEmpty() || $update->award_checked_at)
            <section class="tagged-orgs">
                @foreach ($update->organizations as $organization)
                    <span class="org-chip">
                        <a href="{{ route('sls.organizations.show', $organization) }}">{{ \App\Support\SocialSecurityAdminNameCleaner::repairMojibake((string) $organization->name) }}</a>
                        <form method="post" action="{{ route('sls.intelligence.updates.organizations.destroy', [$update, $organization]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="source-button-link danger" type="submit" aria-label="Remove {{ \App\Support\SocialSecurityAdminNameCleaner::repairMojibake((string) $organization->name) }} tag">x</button>
                        </form>
                    </span>
                @endforeach
                @if ($update->intelligenceContacts->isNotEmpty())
                    <span class="muted"><strong>{{ $update->intelligenceContacts->count() }}</strong> contact reference(s)</span>
                @endif
                @if ($update->intelligenceDocuments->isNotEmpty())
                    <span class="muted"><strong>{{ $update->intelligenceDocuments->count() }}</strong> archived document(s)</span>
                @endif
                @if ($update->award_checked_at)
                    <span class="muted"><strong>Award:</strong> {{ $update->award_status === 'awarded' ? 'found' : ($update->award_status === 'not_found' ? 'not found' : 'checked') }}</span>
                @endif
            </section>
        @endif
        <p class="source-note">Many publishers block embedded viewing with browser security rules or bot protection, so the embedded viewer has been removed. Use the title or {{ $sourceButtonLabel }} to open the original in a separate window while keeping this SLS page open for mapping, tagging, dropping, and follow-up.</p></div>

    <script>
        const orgInput = document.getElementById('organization_name');
        const orgIdInput = document.getElementById('market_organization_id');
        const suggestionBox = document.getElementById('org-suggestions');
        let orgSearchTimer = null;

        function clearOrgSuggestions() {
            suggestionBox.innerHTML = '';
            suggestionBox.classList.remove('active');
        }

        orgInput?.addEventListener('input', () => {
            orgIdInput.value = '';
            window.clearTimeout(orgSearchTimer);
            const q = orgInput.value.trim();
            if (q.length < 2) {
                clearOrgSuggestions();
                return;
            }
            orgSearchTimer = window.setTimeout(async () => {
                try {
                    const response = await fetch(`{{ route('sls.intelligence.updates.organizationSearch', $update) }}?q=${encodeURIComponent(q)}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!response.ok) {
                        clearOrgSuggestions();
                        return;
                    }
                    const matches = await response.json();
                    suggestionBox.innerHTML = '';
                    matches.forEach((match) => {
                        const button = document.createElement('button');
                        const title = document.createElement('strong');
                        const meta = document.createElement('small');
                        button.type = 'button';
                        button.className = 'org-suggestion';
                        title.textContent = match.name || '';
                        meta.textContent = `${match.country || ''}${match.country_iso ? ' (' + match.country_iso + ')' : ''}${match.website_url ? ' | ' + match.website_url : ''}`;
                        button.appendChild(title);
                        button.appendChild(meta);
                        button.addEventListener('click', () => {
                            orgInput.value = match.name;
                            orgIdInput.value = match.id;
                            clearOrgSuggestions();
                        });
                        suggestionBox.appendChild(button);
                    });
                    suggestionBox.classList.toggle('active', matches.length > 0);
                } catch (error) {
                    clearOrgSuggestions();
                }
            }, 220);
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.suggestion-box')) {
                clearOrgSuggestions();
            }
        });

        document.querySelectorAll('.source-action').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const width = Math.min(1120, Math.max(820, Math.floor(window.screen.availWidth * 0.58)));
                const height = Math.min(980, Math.max(720, Math.floor(window.screen.availHeight * 0.88)));
                const left = Math.max(0, window.screen.availWidth - width - 24);
                const top = 24;
                const popup = window.open(
                    button.href,
                    'sls_original_source_{{ $update->id }}',
                    `popup=yes,width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes,noopener=yes`
                );

                if (!popup) {
                    window.open(button.href, '_blank', 'noopener');
                }

                button.dataset.originalText = button.textContent;
                button.textContent = 'Opening...';
                setTimeout(() => {
                    button.textContent = button.dataset.originalText || 'Open original';
                }, 1400);
            });
        });
    </script>
@endsection

