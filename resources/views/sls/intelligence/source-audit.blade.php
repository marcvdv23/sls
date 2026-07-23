@extends('sls.layouts.app')

@section('title', 'Source Audit Response')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Source Audit Response')

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
            <p class="muted">Audit Response</p>
            <h1><span class="source-number">SRC-{{ str_pad($source->id, 4, '0', STR_PAD_LEFT) }}</span> {{ $source->name }}</h1>
            <p class="muted">{{ $source->domain ?: $source->url ?: 'No domain captured' }}</p>
        </header>

        <main class="stack">
            @forelse ($audits as $audit)
                @php
                    $requestUrl = (string) $audit->request_url;
                    $parsedUrl = $requestUrl !== '' ? parse_url($requestUrl) : [];
                    parse_str($parsedUrl['query'] ?? '', $queryParams);
                    $prettyPayload = $audit->request_payload;
                    if ($prettyPayload) {
                        $decodedPayload = json_decode($prettyPayload, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $prettyPayload = json_encode($decodedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        }
                    }
                    $prettyResponse = $audit->response_excerpt;
                    if ($prettyResponse) {
                        $decodedResponse = json_decode($prettyResponse, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $prettyResponse = json_encode($decodedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        }
                    }
                @endphp
                <section class="panel">
                    <h2>
                        {{ $audit->checked_at?->copy()->timezone($austinTz)->format('Y-m-d H:i:s') ?? 'Not timestamped' }}
                        <span class="pill {{ $audit->ok ? 'good' : 'bad' }}">{{ $audit->ok ? 'OK' : 'Error / no usable response' }}</span>
                    </h2>
                    <div class="grid">
                        <div class="label">Source</div>
                        <div>{{ $audit->source_name ?: $audit->domain }}</div>
                        <div class="label">Source #</div>
                        <div><span class="source-number">SRC-{{ str_pad($source->id, 4, '0', STR_PAD_LEFT) }}</span></div>
                        <div class="label">Focus</div>
                        <div>{{ $audit->focus ?: 'Not captured' }}</div>
                        <div class="label">Request</div>
                        <div>{{ $audit->method }} <a href="{{ $audit->request_url }}" rel="noreferrer">{{ $audit->request_url }}</a></div>
                        <div class="label">HTTP status</div>
                        <div>{{ $audit->http_status ?? 'No HTTP status captured' }}</div>
                        <div class="label">Items found</div>
                        <div>{{ $audit->items_found }}</div>
                        <div class="label">Error</div>
                        <div>{{ $audit->error_message ?: 'None' }}</div>
                    </div>

                    <h2 style="margin-top:1rem;">Query sent</h2>
                    <div class="subgrid">
                        <div class="label">Method</div>
                        <div>{{ $audit->method }}</div>
                        <div class="label">Endpoint</div>
                        <div>{{ (($parsedUrl['scheme'] ?? '') && ($parsedUrl['host'] ?? '')) ? (($parsedUrl['scheme'] ?? '') . '://' . ($parsedUrl['host'] ?? '') . ($parsedUrl['path'] ?? '')) : ($requestUrl ?: 'No request URL captured') }}</div>
                        <div class="label">URL query</div>
                        <div>
                            @if (! empty($queryParams))
                                <pre>{{ json_encode($queryParams, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            @else
                                <span class="muted">No URL query parameters captured.</span>
                            @endif
                        </div>
                    </div>

                    @if ($audit->request_payload)
                        <h2 style="margin-top:1rem;">Request body / payload</h2>
                        <pre>{{ $prettyPayload }}</pre>
                    @endif

                    <h2 style="margin-top:1rem;">Response received</h2>
                    <div class="subgrid">
                        <div class="label">HTTP status</div>
                        <div>{{ $audit->http_status ?? 'No HTTP status captured' }}</div>
                        <div class="label">Parsed items</div>
                        <div>{{ $audit->items_found }}</div>
                    </div>
                    <pre>{{ $prettyResponse ?: 'No response body captured.' }}</pre>
                </section>
            @empty
                <section class="panel">
                    <h2>No audit response captured yet</h2>
                    <p class="muted">This source has not produced an audit record yet. That usually means the source has not been reached by a monitor run since auditing was added, or it is not connected to a runnable connector yet.</p>
                    <form method="post" action="{{ route('sls.intelligence.sources.runNext', $source) }}" style="margin-top:.8rem;">
                        @csrf
                        <button class="button" type="submit">Check this source next</button>
                    </form>
                </section>
            @endforelse
        </main>
    </div>
@endsection