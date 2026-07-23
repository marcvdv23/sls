@extends('sls.layouts.app')

@section('title', 'Operation Run #' . $operationRun->id . ' - 1G-SLS')
@section('eyebrow', 'Operations')
@section('page_title', $operationRun->operation_name . ' run')

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
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
        .progress-track {
            height: 12px;
            overflow: hidden;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-subtle);
            border-radius: 999px;
        }
        .progress-fill {
            width: 0%;
            height: 100%;
            background: var(--accent-primary);
            transition: width .25s ease;
        }
        .run-table { min-width: 980px; }
        .run-table th,
        .run-table td { padding: 8px 10px; vertical-align: top; }
        .reason-list { margin: 0; padding-left: 18px; }
        .muted.small { font-size: 12px; }
        .status-line { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .domain-cell { display: inline-flex; align-items: baseline; gap: 8px; }
        .domain-actions { display: inline-flex; align-items: center; gap: 6px; }
        .mini-action {
            display: inline;
            margin: 0;
            padding: 0 2px;
            color: var(--text-secondary);
            background: transparent;
            border: 0;
            font: inherit;
            font-weight: 800;
            line-height: 1;
            cursor: pointer;
        }
        .mini-action:hover { color: var(--accent-primary); }
        .mini-action.danger { color: #dc2626; }
        .mini-action.danger:hover { color: #b91c1c; }
        @media (max-width: 760px) {
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page"
         data-run-url="{{ route('sls.operations.runs.status', $operationRun) }}"
         data-save-url-template="{{ url('/sls/operations/runs/' . $operationRun->id . '/items/__INDEX__/save') }}"
         data-reject-url-template="{{ url('/sls/operations/runs/' . $operationRun->id . '/items/__INDEX__/reject') }}">
        <header>
            <div>
                <p class="eyebrow">Run #{{ $operationRun->id }}</p>
                <h1>{{ $operationRun->operation_name }}</h1>
                <p class="muted">This page updates as each attempt is recorded.</p>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('sls.operations.index') }}">Operations</a>
                <a class="button" href="{{ route('sls.organizations.index') }}">Organizations</a>
            </div>
        </header>

        <main>
            <section class="panel">
                <div class="status-line">
                    <p class="eyebrow" style="margin:0;">Status</p>
                    <span id="run-status" class="pill">{{ str_replace('_', ' ', $operationRun->status) }}</span>
                    <span id="run-mode" class="pill">{{ $operationRun->dry_run ? 'Dry run' : 'Writing results' }}</span>
                    <span id="run-updated" class="muted small">Updated {{ optional($operationRun->updated_at)->format('Y-m-d H:i:s') }}</span>
                </div>
                <div class="progress-track" style="margin-top:10px;">
                    <div id="run-progress" class="progress-fill"></div>
                </div>
            </section>

            <section class="summary">
                <div class="panel stat"><strong id="run-total">{{ number_format($operationRun->total_count) }}</strong><span class="muted">eligible</span></div>
                <div class="panel stat"><strong id="run-processed">{{ number_format($operationRun->processed_count) }}</strong><span class="muted">processed</span></div>
                <div class="panel stat"><strong id="run-success">{{ number_format($operationRun->success_count) }}</strong><span class="muted">ready for crawl</span></div>
                <div class="panel stat"><strong id="run-failure">{{ number_format($operationRun->failure_count) }}</strong><span class="muted">not found</span></div>
                <div class="panel stat"><strong id="run-checks">0</strong><span class="muted">checks made</span></div>
            </section>

            <section id="run-error-panel" class="panel" style="display:none;">
                <p class="eyebrow">Error</p>
                <p id="run-error" class="muted"></p>
            </section>

            <section class="panel">
                <p class="eyebrow">Attempts</p>
                <h2>Checked organizations</h2>
                <div class="table-wrap" style="margin-top:10px;">
                    <table class="run-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Organization</th>
                                <th>Country</th>
                                <th>Status</th>
                                <th>Best domain</th>
                                <th>Confidence</th>
                                <th>Evidence</th>
                            </tr>
                        </thead>
                        <tbody id="run-items">
                            <tr>
                                <td colspan="7" class="muted">Waiting for the first attempt...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const root = document.querySelector('[data-run-url]');
            const url = root.dataset.runUrl;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const finishedStatuses = new Set(['completed', 'failed']);
            const formatter = new Intl.NumberFormat();
            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char]));

            const setText = (id, value) => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = value;
                }
            };

            const renderItems = (items) => {
                const body = document.getElementById('run-items');
                if (!body) {
                    return;
                }

                if (!items.length) {
                    body.innerHTML = '<tr><td colspan="7" class="muted">Waiting for the first attempt...</td></tr>';
                    return;
                }

                body.innerHTML = items.map((item, index) => {
                    const reasons = (item.reasons || []).length
                        ? `<ul class="reason-list">${item.reasons.map((reason) => `<li>${escapeHtml(reason)}</li>`).join('')}</ul>`
                        : '<span class="muted">No evidence captured</span>';
                    const domainActions = `
                        <span class="domain-actions">
                            <button class="mini-action" type="button" data-edit-finding="${index}" data-current-url="${escapeHtml(item.best_url || '')}" title="Edit URL">&#9998;</button>
                            <button class="mini-action danger" type="button" data-reject-finding="${index}" title="Reject finding">&times;</button>
                        </span>
                    `;
                    const domain = item.best_url
                        ? `<span class="domain-cell"><a href="${escapeHtml(item.best_url)}" target="_blank" rel="noopener">${escapeHtml(item.best_domain)}</a>${domainActions}</span>`
                        : `<span class="domain-cell"><span class="muted">No candidate passed</span>${domainActions}</span>`;
                    const organization = `<a href="{{ url('/sls/organizations') }}/${Number(item.id)}">${escapeHtml(item.name)}</a>`;

                    return `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${organization}<p class="muted small">${formatter.format(Number(item.checked || 0))}/${formatter.format(Number(item.guesses || 0))} guesses checked</p></td>
                            <td>${escapeHtml(item.country || '')} ${item.country_iso ? `(${escapeHtml(item.country_iso)})` : ''}</td>
                            <td><span class="pill">${escapeHtml(String(item.status || '').replaceAll('_', ' '))}</span></td>
                            <td>${domain}</td>
                            <td>${Number(item.confidence || 0)}%</td>
                            <td>${reasons}</td>
                        </tr>
                    `;
                }).join('');
            };

            document.addEventListener('click', async (event) => {
                const editButton = event.target.closest('[data-edit-finding]');
                const rejectButton = event.target.closest('[data-reject-finding]');

                if (editButton) {
                    const index = editButton.dataset.editFinding;
                    const value = window.prompt('Website URL', editButton.dataset.currentUrl || '');
                    if (value === null) {
                        return;
                    }

                    const endpoint = root.dataset.saveUrlTemplate.replace('__INDEX__', index);
                    const body = new FormData();
                    body.append('website_url', value);

                    await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body,
                    });
                    await update();
                }

                if (rejectButton) {
                    const index = rejectButton.dataset.rejectFinding;
                    const endpoint = root.dataset.rejectUrlTemplate.replace('__INDEX__', index);
                    rejectButton.disabled = true;
                    await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });
                    await update();
                }
            });

            const update = async () => {
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await response.json();
                const total = Number(data.total_count || data.summary?.eligible || 0);
                const processed = Number(data.processed_count || data.summary?.processed || 0);
                const checks = Number(data.summary?.checks || 0);
                const progress = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;

                setText('run-status', String(data.status || '').replaceAll('_', ' '));
                setText('run-mode', data.dry_run ? 'Dry run' : 'Writing results');
                setText('run-updated', data.updated_at ? `Updated ${data.updated_at}` : '');
                setText('run-total', formatter.format(total));
                setText('run-processed', formatter.format(processed));
                setText('run-success', formatter.format(Number(data.success_count || data.summary?.ready_for_crawl || 0)));
                setText('run-failure', formatter.format(Number(data.failure_count || data.summary?.domain_guess_not_found || 0)));
                setText('run-checks', formatter.format(checks));
                document.getElementById('run-progress').style.width = `${progress}%`;

                const errorPanel = document.getElementById('run-error-panel');
                if (data.error_message) {
                    errorPanel.style.display = '';
                    setText('run-error', data.error_message);
                } else {
                    errorPanel.style.display = 'none';
                }

                renderItems(data.items || []);

                if (!finishedStatuses.has(data.status)) {
                    window.setTimeout(update, 2000);
                }
            };

            update().catch(() => {
                window.setTimeout(update, 4000);
            });
        })();
    </script>
@endpush
