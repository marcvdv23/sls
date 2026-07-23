@extends('sls.layouts.app')

@section('title', 'Review Demo Media - 1G-SLS')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Review Demo Media')

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
                <p class="eyebrow">Demo Media Review</p>
                <h1>{{ $session->title }}</h1>
            </div>
            <div class="badge-row">
                <a class="button secondary" href="{{ route('sls.demo-media.index') }}">Demo media</a>
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
            </div>
        </header>

        <main class="stack">
            @if (session('status'))
                <section class="panel">
                    <p>{{ session('status') }}</p>
                </section>
            @endif

            <section class="panel">
                <p class="eyebrow">Session Status</p>
                <h2>{{ $session->processing_status }}</h2>
                <p class="muted">{{ $session->processing_notes }}</p>
                <div class="badge-row">
                    <span class="badge">{{ $session->product?->name ?? 'Not assigned' }}</span>
                    <span class="badge">{{ strtoupper($session->language_code) }}</span>
                    <span class="badge">{{ $segments->count() }} transcript segment(s)</span>
                    <span class="badge">{{ $firstFrames->count() + $lastFrames->count() }} thumbnail(s) shown</span>
                    <span class="badge">{{ $firstFrames->merge($lastFrames)->whereNotNull('screen_summary')->count() }} shown frame(s) with UI summaries</span>
                    <span class="badge">{{ $moments->where('approval_status', 'approved')->count() }} approved moment(s)</span>
                    <span class="badge warn">{{ $moments->where('approval_status', 'unreviewed')->count() }} pending moment(s)</span>
                </div>
                @if ($moments->isNotEmpty())
                    <form method="post" action="{{ route('sls.demo-media.approve-moments', $session) }}" style="margin-top: .85rem;">
                        @csrf
                        <button class="button" type="submit">Approve all moments for chat</button>
                    </form>
                @endif
            </section>

            <section class="grid">
                <article class="panel">
                    <p class="eyebrow">First 5 Frames</p>
                    <h2>Opening screenshots</h2>
                    @if ($firstFrames->isEmpty())
                        <p class="muted">No frames have been extracted yet.</p>
                    @else
                        <div class="thumb-grid">
                            @foreach ($firstFrames as $frame)
                                <a href="{{ route('sls.demo-media.frames.show', $frame) }}" target="_blank" rel="noopener">
                                    <img src="{{ route('sls.demo-media.frames.show', $frame) }}" alt="Frame at {{ round($frame->timestamp_ms / 1000, 1) }} seconds">
                                    <span class="badge">{{ round($frame->timestamp_ms / 1000, 1) }}s</span>
                                    @if ($frame->screen_summary)
                                        <span class="badge good">UI OCR</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </article>
                <article class="panel">
                    <p class="eyebrow">Last 5 Frames</p>
                    <h2>Closing screenshots</h2>
                    @if ($lastFrames->isEmpty())
                        <p class="muted">No frames have been extracted yet.</p>
                    @else
                        <div class="thumb-grid">
                            @foreach ($lastFrames as $frame)
                                <a href="{{ route('sls.demo-media.frames.show', $frame) }}" target="_blank" rel="noopener">
                                    <img src="{{ route('sls.demo-media.frames.show', $frame) }}" alt="Frame at {{ round($frame->timestamp_ms / 1000, 1) }} seconds">
                                    <span class="badge">{{ round($frame->timestamp_ms / 1000, 1) }}s</span>
                                    @if ($frame->screen_summary)
                                        <span class="badge good">UI OCR</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </article>
            </section>

            <section class="panel">
                <p class="eyebrow">Screen OCR</p>
                <h2>Structural UI evidence from screenshots</h2>
                <p class="muted" style="margin-bottom: .75rem;">OCR is treated as screen structure, not product facts from sample data. Menu labels, navigation paths, field labels, column labels, buttons, and tabs are kept; transactional example values are filtered out for chat use.</p>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Structured UI elements captured</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($allFrames->whereNotNull('screen_summary') as $frame)
                                <tr>
                                    <td>{{ round($frame->timestamp_ms / 1000, 1) }}s</td>
                                    <td>
                                        <div class="ui-block">
                                            @if (! empty($frame->ui_structure['menus'] ?? []))
                                                <div class="ui-row"><strong>Menus</strong><span>{{ implode(', ', array_slice($frame->ui_structure['menus'], 0, 12)) }}</span></div>
                                            @endif
                                            @if (! empty($frame->ui_structure['labels'] ?? []))
                                                <div class="ui-row"><strong>Labels</strong><span>{{ implode(', ', array_slice($frame->ui_structure['labels'], 0, 16)) }}</span></div>
                                            @endif
                                            @if (! empty($frame->ui_structure['columns'] ?? []))
                                                <div class="ui-row"><strong>Columns</strong><span>{{ implode(', ', array_slice($frame->ui_structure['columns'], 0, 16)) }}</span></div>
                                            @endif
                                            @if (! empty($frame->ui_structure['actions'] ?? []))
                                                <div class="ui-row"><strong>Actions</strong><span>{{ implode(', ', array_slice($frame->ui_structure['actions'], 0, 12)) }}</span></div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="muted">No useful screen OCR summaries yet. Screenshots with mostly blank pages or low-confidence OCR are ignored for chat use.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <p class="eyebrow">Transcript</p>
                <h2>Generated transcript segments</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Text</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($segments as $segment)
                                <tr>
                                    <td>{{ round($segment->start_ms / 1000, 1) }}s - {{ $segment->end_ms ? round($segment->end_ms / 1000, 1) . 's' : 'end' }}</td>
                                    <td>{{ $segment->transcript_text }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="muted">No transcript segments generated yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <p class="eyebrow">Chat Knowledge Moments</p>
                <h2>Generated moments with transcript and screen evidence</h2>
                <div class="stack">
                    @forelse ($moments as $moment)
                        <article class="card">
                            <h3>{{ $moment->feature_name }}</h3>
                            @php
                                $momentFrames = collect($moment->frame_ids ?? [])
                                    ->map(fn ($frameId) => $framesById->get((int) $frameId))
                                    ->filter()
                                    ->values();
                                $transcriptText = trim(preg_replace('/\s+Screen labels:.*$/', '', (string) $moment->summary));
                            @endphp
                            <div class="moment-layout">
                                <div>
                                    <p class="muted">{{ $transcriptText }}</p>
                                    <div class="badge-row">
                                        <span class="badge {{ $moment->approval_status === 'approved' ? 'good' : 'warn' }}">{{ $moment->approval_status }}</span>
                                        @if ($moment->module_name)
                                            <span class="badge">{{ $moment->module_name }}</span>
                                        @endif
                                        @if ($moment->business_problem)
                                            <span class="badge">{{ $moment->business_problem }}</span>
                                        @endif
                                        <span class="badge">{{ round(($moment->start_ms ?? 0) / 1000, 1) }}s</span>
                                    </div>
                                </div>
                                <div class="stack">
                                    @if ($momentFrames->isNotEmpty())
                                        <div class="mini-frames">
                                            @foreach ($momentFrames as $frame)
                                                <a href="{{ route('sls.demo-media.frames.show', $frame) }}" target="_blank" rel="noopener">
                                                    <img src="{{ route('sls.demo-media.frames.show', $frame) }}" alt="Frame at {{ round($frame->timestamp_ms / 1000, 1) }} seconds">
                                                    <span class="badge">{{ round($frame->timestamp_ms / 1000, 1) }}s</span>
                                                </a>
                                            @endforeach
                                        </div>
                                        <div class="ui-block">
                                            @foreach ($momentFrames as $frame)
                                                @if ($frame->screen_summary)
                                                    <div class="card">
                                                        <h3>{{ round($frame->timestamp_ms / 1000, 1) }}s screen evidence</h3>
                                                        @if (! empty($frame->ui_structure['menus'] ?? []))
                                                            <div class="ui-row"><strong>Menus</strong><span>{{ implode(', ', array_slice($frame->ui_structure['menus'], 0, 10)) }}</span></div>
                                                        @endif
                                                        @if (! empty($frame->ui_structure['labels'] ?? []))
                                                            <div class="ui-row"><strong>Labels</strong><span>{{ implode(', ', array_slice($frame->ui_structure['labels'], 0, 12)) }}</span></div>
                                                        @endif
                                                        @if (! empty($frame->ui_structure['columns'] ?? []))
                                                            <div class="ui-row"><strong>Columns</strong><span>{{ implode(', ', array_slice($frame->ui_structure['columns'], 0, 12)) }}</span></div>
                                                        @endif
                                                        @if (! empty($frame->ui_structure['actions'] ?? []))
                                                            <div class="ui-row"><strong>Actions</strong><span>{{ implode(', ', array_slice($frame->ui_structure['actions'], 0, 10)) }}</span></div>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="muted">No screenshots linked to this moment.</p>
                                    @endif
                                </div>
                            </div>
                            <div class="badge-row">
                                <span class="badge">{{ $momentFrames->count() }} linked screenshot(s)</span>
                            </div>
                        </article>
                    @empty
                        <p class="muted">No moments generated yet.</p>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
@endsection