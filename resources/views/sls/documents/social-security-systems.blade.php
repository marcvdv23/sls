@extends('sls.layouts.app')

@section('title', 'Social Security System Imports - 1G-SLS')
@section('eyebrow', 'Documents')
@section('page_title', 'Social Security System Imports')

@push('head')
    <style>
        .system-import-page { display: grid; gap: 14px; }
        .system-import-page .compact-form { display: grid; grid-template-columns: minmax(260px, 1fr) minmax(320px, 2fr) 180px; gap: 10px; align-items: end; }
        .system-import-page table { min-width: 1180px; }
        .system-import-page th,
        .system-import-page td { padding: 8px 10px; vertical-align: top; }
        .system-import-page .action-row { display: flex; gap: 6px; flex-wrap: wrap; }
        .system-import-page .evidence-cell { max-width: 320px; color: var(--text-secondary); font-size: 12px; }
        .system-import-page .role-cell { max-width: 300px; }
        .system-import-page .context-list { display: grid; gap: 6px; font-size: 12px; margin-top: 4px; }
        .system-import-page .context-item { border-left: 2px solid var(--border-subtle); padding-left: 8px; }
        .system-import-page .classification-row { color: var(--text-secondary); margin-top: 2px; }
        .system-import-page .classification-row strong { color: var(--text-primary); }
        @media (max-width: 980px) {
            .system-import-page .compact-form { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="system-import-page">
        @if (session('status'))
            <section class="panel">
                <p class="muted">{{ session('status') }}</p>
                @if (session('export_url'))
                    <p style="margin-top:6px;"><a class="button secondary" href="{{ session('export_url') }}" target="_blank" rel="noopener">Open exported CSV</a></p>
                @endif
            </section>
        @endif

        <section class="panel">
            <p class="eyebrow">Upload</p>
            <h2>Extract administrative organizations from country social security system documents</h2>
            <p class="muted" style="margin-top:.35rem;">The extractor looks for every table headed "Administrative organization" and stores each distinct organization, role, and related programme context. Repeated duplicate rows are ignored; different contexts remain attached to the same organization.</p>
            <form class="compact-form" method="post" action="{{ route('sls.socialSecuritySystems.import.store') }}" enctype="multipart/form-data" style="margin-top:10px;">
                @csrf
                <label>Country override
                    <select name="related_country_id">
                        <option value="">Use filename</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }} ({{ $country->iso_code }})</option>
                        @endforeach
                    </select>
                </label>
                <label>Documents
                    <input type="file" name="source_files[]" multiple required accept=".pdf,.docx,.txt,.csv,.md,.html,.htm,.xlsx">
                </label>
                <button class="button" type="submit">Upload and stage</button>
            </form>
        </section>

        <section class="panel">
            <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; flex-wrap:wrap;">
                <div>
                    <p class="eyebrow">Staged Organizations</p>
                    <h2>Review extracted administrative organizations</h2>
                </div>
                <div class="action-row">
                    <a class="button secondary" href="{{ route('sls.socialSecuritySystems.export') }}">Download CSV</a>
                    <a class="button secondary" href="{{ route('sls.socialSecuritySystems.exportFile') }}">Create file link</a>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Country</th>
                            <th>Organization</th>
                            <th>Captured contexts</th>
                            <th>Source</th>
                            <th>Evidence</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($candidates as $candidate)
                            <tr>
                                <td><span class="pill {{ $candidate->status === 'approved' ? 'good' : ($candidate->status === 'rejected' ? 'bad' : 'warn') }}">{{ $candidate->status }}</span></td>
                                <td>{{ $candidate->country?->name ?? $candidate->country_name ?? 'Unmapped' }}{{ $candidate->country_iso ? ' (' . $candidate->country_iso . ')' : '' }}</td>
                                <td>
                                    <strong>{{ $candidate->organization_name }}</strong>
                                    @if ($candidate->marketOrganization)
                                        <br><a href="{{ route('sls.organizations.show', $candidate->marketOrganization) }}">CRM #{{ $candidate->marketOrganization->id }}</a>
                                    @endif
                                </td>
                                <td class="role-cell">
                                    <strong>{{ $candidate->contexts_count ?? $candidate->contexts->count() }} context(s)</strong>
                                    <div class="context-list">
                                        @forelse ($candidate->contexts as $context)
                                            <div class="context-item">
                                                <strong>Role:</strong> {{ $context->role_in_programme ?: 'Not extracted' }}<br>
                                                <strong>Programmes:</strong> {{ $context->related_programmes ?: 'Not extracted' }}
                                                @if (($context->normalized_roles ?? []) || ($context->programme_l1 ?? []) || ($context->programme_l2 ?? []))
                                                    <div class="classification-row">
                                                        <strong>Class:</strong>
                                                        {{ collect($context->normalized_roles ?? [])->merge($context->programme_l1 ?? [])->unique()->implode(' | ') ?: 'Unclassified' }}
                                                    </div>
                                                @endif
                                                @if ($context->special_system_mentioned)
                                                    <div class="classification-row">
                                                        <strong>Special system:</strong>
                                                        {{ collect($context->special_system_employer_types ?? [])->implode(', ') ?: 'Mentioned' }}; needs enrichment
                                                    </div>
                                                @endif
                                            </div>
                                        @empty
                                            <span class="muted">No context rows stored yet.</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td>
                                    @if ($candidate->sourceDocument)
                                        <a href="{{ route('sls.knowledge.show', $candidate->sourceDocument) }}">{{ $candidate->sourceDocument->title }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="evidence-cell">{{ $candidate->evidence_excerpt }}</td>
                                <td>
                                    <div class="action-row">
                                        @if ($candidate->status !== 'approved')
                                            <form method="post" action="{{ route('sls.socialSecuritySystems.candidates.approve', $candidate) }}">
                                                @csrf
                                                <button class="button" type="submit">Approve</button>
                                            </form>
                                        @endif
                                        @if ($candidate->status !== 'rejected')
                                            <form method="post" action="{{ route('sls.socialSecuritySystems.candidates.reject', $candidate) }}">
                                                @csrf
                                                <button class="button secondary" type="submit">Reject</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">No administrative organizations have been staged yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
