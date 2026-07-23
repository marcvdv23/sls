@extends('sls.layouts.app')

@section('title', 'Source Setup Issue')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Source Setup Issue')

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
            <p class="muted">Connector Setup</p>
            <h1>{{ $source->name }}</h1>
            <p class="muted">{{ $source->domain ?: $source->url ?: 'No domain captured' }}</p>
        </header>

        <main>
            <section class="panel">
                <h2>Current setup</h2>
                <div class="grid">
                    <div class="label">Access method</div>
                    <div>{{ $accessLabel }}</div>
                    <div class="label">Source type</div>
                    <div>{{ $source->source_class }}</div>
                    <div class="label">Focus</div>
                    <div>{{ $source->focus }}</div>
                    <div class="label">URL</div>
                    <div>
                        @if ($source->url)
                            <a href="{{ $source->url }}" rel="noreferrer">{{ $source->url }}</a>
                        @else
                            Not captured
                        @endif
                    </div>
                </div>
            </section>

            <section class="panel">
                <h2>What is needed</h2>
                <ul>
                    @foreach ($needs as $need)
                        <li>{{ $need }}</li>
                    @endforeach
                </ul>
            </section>
        </main>
    </div>
@endsection