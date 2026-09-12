@extends('sls.layouts.app')

@section('title', 'Crawler Settings')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Crawler Settings')

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
        .legacy-page .actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .settings-grid { display: grid; gap: 12px; }
        .setting-row {
            display: grid;
            grid-template-columns: minmax(220px, 340px) minmax(260px, 1fr);
            gap: 14px;
            align-items: start;
            border-top: 1px solid var(--border-subtle);
            padding-top: 12px;
        }
        .setting-row:first-of-type { border-top: 0; padding-top: 0; }
        .setting-row label { color: var(--text-primary); font-size: 14px; font-weight: 800; }
        .setting-row input { width: 100%; }
        .setting-meta { color: var(--text-secondary); display: grid; gap: 4px; font-size: 13px; }
        @media (max-width: 900px) {
            .legacy-page > header, .setting-row { grid-template-columns: 1fr; }
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page">
        <header>
            <div>
                <p class="eyebrow">Country Intelligence</p>
                <h1>Crawler Settings</h1>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('sls.intelligence.sources', ['region' => 'all']) }}">Source registry</a>
                <a class="button secondary" href="{{ route('sls.intelligence.keywords') }}">Keyword directory</a>
                <a class="button secondary" href="{{ route('sls.intelligence.review', ['focus' => 'all', 'region' => 'all']) }}">Review desk</a>
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
            </div>
        </header>

        <main>
            @if (session('status'))
                <section class="panel"><p class="muted">{{ session('status') }}</p></section>
            @endif

            @if ($errors->any())
                <section class="panel"><p class="muted">{{ $errors->first() }}</p></section>
            @endif

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Runtime Configuration</p>
                    <h2>Schedule, freshness, and run limits</h2>
                    <p class="muted">These values are read by the scheduled crawler on the next scheduler tick. Times are server-time HH:MM values.</p>
                </div>

                <form class="settings-grid" method="post" action="{{ route('sls.intelligence.crawlerSettings.update') }}">
                    @csrf
                    @foreach ($definitions as $key => $definition)
                        @php($setting = $settings->get($key))
                        <div class="setting-row">
                            <div class="setting-meta">
                                <label for="setting-{{ $key }}">{{ $definition['label'] }}</label>
                                <span>{{ $definition['description'] }}</span>
                                <span>Key: <code>{{ $key }}</code></span>
                            </div>
                            <input
                                type="{{ $definition['value_type'] === 'secret' ? 'password' : 'text' }}"
                                id="setting-{{ $key }}"
                                name="settings[{{ $key }}]"
                                value="{{ old('settings.' . $key, $setting?->setting_value ?? $definition['default']) }}"
                                autocomplete="off"
                            >
                        </div>
                    @endforeach

                    <div class="actions">
                        <button type="submit">Save crawler settings</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
@endsection
