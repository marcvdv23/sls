@extends('sls.layouts.app')

@section('title', '1G-SLS Chatbox')
@section('eyebrow', '1G-SLS')
@section('page_title', '1G-SLS Chatbox')

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
        .chat-page { min-width: 0; }
        .chat-page .grid { display: grid; grid-template-columns: minmax(260px, 360px) minmax(0, 1fr); gap: 14px; align-items: start; }
        .chat-page .panel,
        .chat-page .card,
        .chat-page details { min-width: 0; max-width: 100%; overflow-wrap: anywhere; }
        .chat-page .answer-text { max-width: 100%; overflow-wrap: anywhere; }
        .chat-page .answer-text p { margin: 0 0 .7rem; }
        .chat-page .visual-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; max-width: 100%; }
        .chat-page .visual-card { overflow: hidden; }
        .chat-page .visual-card img {
            display: block;
            width: 100%;
            max-width: 320px;
            max-height: 190px;
            object-fit: contain;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-input);
            background: var(--bg-primary);
        }
        .chat-page .visual-card a { display: inline-block; max-width: 100%; }
        @media (max-width: 980px) {
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
            .chat-page .grid { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page chat-page">
<header>
            <div>
                <p class="eyebrow">Approved Knowledge Chat</p>
                <h1>1G-SLS Chatbox</h1>
            </div>
            <div class="badge-row">
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
                <a class="button" href="{{ route('sls.knowledge.index') }}">Knowledge base</a>
                <a class="button" href="{{ route('sls.chat.logs') }}">Chat logs</a>
                <a class="button" href="{{ route('sls.knowledge.upload') }}">Upload source</a>
            </div>
        </header>

        <main>
            <div class="grid">
                <section class="panel">
                    <h2>Ask a Product Question</h2>
                    <form method="post" action="{{ route('sls.chat.ask') }}">
                        @csrf
                        <label>
                            Product
                            <select name="product_id">
                                <option value="">All products</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected(($selectedProductId ?? null) == $product->id)>{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Question
                            <textarea name="question" required>{{ old('question', $question) }}</textarea>
                        </label>
                        <button type="submit">Search approved knowledge</button>
                    </form>
                </section>

                <section class="panel">
                    <h2>Answer</h2>
                    <div class="badge-row" style="margin: 0 0 .75rem;">
                        <span class="badge">AI provider: {{ strtoupper($aiProvider ?? 'gemini') }}</span>
                    </div>
                    @if ($answer)
                        <div class="answer-text">
                            {!! \Illuminate\Support\Str::markdown($answer, [
                                'html_input' => 'strip',
                                'allow_unsafe_links' => false,
                            ]) !!}
                        </div>
                    @else
                        <p class="muted">Upload product manuals or emails, approve chunks, then ask a question here.</p>
                    @endif

                    @if (($visualMatches ?? collect())->isNotEmpty())
                        <details open>
                            <summary>Relevant demo screenshots and moments</summary>
                            <div class="visual-grid">
                                @foreach ($visualMatches as $match)
                                    @php
                                        $moment = $match['moment'];
                                        $frames = $match['frames'];
                                    @endphp
                                    <article class="card visual-card">
                                        <h3>{{ $moment->feature_name }}</h3>
                                        <p class="muted">{{ $match['excerpt'] }}</p>
                                        @if ($frames->isNotEmpty())
                                            <div class="visual-grid">
                                                @foreach ($frames as $frame)
                                                    <a href="{{ route('sls.demo-media.frames.show', $frame) }}" target="_blank" rel="noopener">
                                                        <img src="{{ route('sls.demo-media.frames.show', $frame) }}" alt="Demo screenshot at {{ round($frame->timestamp_ms / 1000, 1) }} seconds">
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                        <div class="badge-row">
                                            <span class="badge">{{ $moment->productForChat()?->name ?? 'All products' }}</span>
                                            <span class="badge">{{ $moment->demoSession?->title }}</span>
                                            @if ($moment->module_name)
                                                <span class="badge">{{ $moment->module_name }}</span>
                                            @endif
                                            <span class="badge">visual relevance {{ $match['score'] }}</span>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    @if ($matches->isNotEmpty())
                        <details>
                            <summary>Sources used for this answer</summary>
                            <div class="stack" style="margin-top: 1rem;">
                                @foreach ($matches as $match)
                                    @php
                                        $chunk = is_array($match) ? $match['chunk'] : $match;
                                        $excerpt = is_array($match) ? $match['excerpt'] : $match->chunk_text;
                                        $score = is_array($match) ? $match['score'] : null;
                                    @endphp
                                    <article class="card">
                                        <h3>{{ $chunk->chunk_title }}</h3>
                                        <p class="muted">{{ $excerpt }}</p>
                                        <div class="badge-row">
                                            <span class="badge">{{ $chunk->product?->name ?? 'All products' }}</span>
                                            <span class="badge">{{ $chunk->sourceDocument?->title }}</span>
                                            <span class="badge">{{ $chunk->citation_label }}</span>
                                            @if ($score)
                                                <span class="badge">relevance {{ $score }}</span>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </section>
            </div>
        </main>
    </div>
@endsection
