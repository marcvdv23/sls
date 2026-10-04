@extends('sls.layouts.app')

@section('title', 'Crawler Settings')
@section('eyebrow', 'Setup')
@section('page_title', 'Crawler Settings')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.sources', ['region' => 'all']) }}">Sources</a>
    <a class="button secondary" href="{{ route('sls.intelligence.keywords') }}">Keywords</a>
    <a class="button secondary" href="{{ route('sls.serpapiSearches.index') }}">SerpAPI</a>
@endsection

@push('head')
    <style>
        .crawler-settings-page { display: grid; gap: 16px; }
        .crawler-hero { display: grid; gap: 12px; grid-template-columns: minmax(0, 1.3fr) minmax(280px, .7fr); align-items: stretch; }
        .crawler-summary { display: grid; gap: 8px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .crawler-summary .stat strong { display: block; font-size: 24px; line-height: 1.1; }
        .crawler-summary .stat span { color: var(--text-secondary); font-size: 13px; }
        .settings-form { display: grid; gap: 14px; }
        .settings-section { display: grid; gap: 12px; }
        .settings-section-header { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
        .settings-section-header h2 { margin: 0; }
        .settings-grid { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .setting-card { border: 1px solid var(--border-subtle); border-radius: 8px; padding: 12px; display: grid; gap: 9px; background: var(--bg-secondary); position: relative; }
        .setting-card label { color: var(--text-primary); font-weight: 800; }
        .setting-card input, .setting-card select, .setting-card textarea { width: 100%; }
        .setting-card textarea { min-height: 76px; resize: vertical; }
        .setting-card .meta { display: grid; gap: 5px; color: var(--text-secondary); font-size: 13px; line-height: 1.4; }
        .setting-title { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .setting-title label { min-width: 0; overflow-wrap: anywhere; }
        .setting-card .key { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 12px; color: var(--text-muted); overflow-wrap: anywhere; }
        .setting-help { position: relative; display: inline-flex; flex: 0 0 auto; }
        .setting-help-button {
            width: 22px;
            height: 22px;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            align-items: center;
            display: inline-flex;
            justify-content: center;
            font-size: 13px;
            font-weight: 900;
            line-height: 1;
            padding: 0;
            cursor: help;
        }
        .setting-help-button:focus { outline: 2px solid #2563eb; outline-offset: 2px; }
        .setting-help-popover {
            background: #0f172a;
            border-radius: 8px;
            bottom: calc(100% + 8px);
            box-shadow: 0 14px 35px rgba(15, 23, 42, .22);
            color: #fff;
            display: none;
            font-size: 13px;
            font-weight: 500;
            left: 50%;
            line-height: 1.45;
            max-width: min(360px, 80vw);
            min-width: 260px;
            padding: 10px 12px;
            position: absolute;
            transform: translateX(-50%);
            z-index: 30;
        }
        .setting-help-popover::after {
            border: 7px solid transparent;
            border-top-color: #0f172a;
            content: "";
            left: 50%;
            position: absolute;
            top: 100%;
            transform: translateX(-50%);
        }
        .setting-help:hover .setting-help-popover,
        .setting-help:focus-within .setting-help-popover { display: block; }
        .setting-help-popover strong { display: block; font-size: 12px; letter-spacing: .04em; margin-bottom: 4px; text-transform: uppercase; color: #bfdbfe; }
        .setting-help-popover code { background: rgba(255, 255, 255, .12); border-radius: 4px; color: #fff; padding: 1px 4px; }
        .setting-type { color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .recent-runs table { min-width: 760px; }
        .status-pill { border-radius: 999px; display: inline-flex; padding: 4px 8px; font-size: 12px; font-weight: 800; background: #e5edff; color: #1d4ed8; }
        .status-pill.completed { background: #d1fae5; color: #047857; }
        .status-pill.failed { background: #fee2e2; color: #b91c1c; }
        .status-pill.running, .status-pill.started { background: #fef3c7; color: #92400e; }
        .actions.sticky-save { position: sticky; bottom: 10px; justify-content: flex-end; padding: 10px; background: color-mix(in srgb, var(--bg-primary) 92%, transparent); border: 1px solid var(--border-subtle); border-radius: 8px; backdrop-filter: blur(8px); }
        @media (max-width: 1100px) {
            .crawler-hero, .settings-grid { grid-template-columns: 1fr; }
            .crawler-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .crawler-summary { grid-template-columns: 1fr; }
            .settings-section-header { display: grid; }
        }
    </style>
@endpush

@section('content')
    <div class="crawler-settings-page">
        @if ($errors->any())
            <section class="panel" style="color:#b91c1c;border-color:#fecaca;background:#fff7f7;">{{ $errors->first() }}</section>
        @endif

        <section class="crawler-hero">
            <div class="panel stack">
                <div>
                    <p class="eyebrow">Runtime configuration</p>
                    <h2>Country intelligence crawler control room</h2>
                    <p class="muted">These settings control the scheduled crawlers that feed Review Desk items, monitor runs, news/tender sweeps, source discovery, and background enrichment. Changes are read by the scheduler on the next minute tick.</p>
                </div>
                <div class="actions">
                    <a class="button secondary" href="{{ route('sls.intelligence.review', ['focus' => 'all', 'region' => 'all']) }}">Review Desk</a>
                    <a class="button secondary" href="{{ route('sls.intelligence.coverage') }}">Source Coverage</a>
                    <a class="button secondary" href="{{ route('sls.operations.index') }}">Operations</a>
                </div>
            </div>

            <div class="crawler-summary">
                <div class="panel stat"><strong>{{ number_format((int) ($relatedCounts['sources'] ?? 0)) }}</strong><span>registered sources</span></div>
                <div class="panel stat"><strong>{{ number_format((int) ($relatedCounts['keywords'] ?? 0)) }}</strong><span>keyword rules</span></div>
                <div class="panel stat"><strong>{{ number_format((int) ($relatedCounts['serpapi_templates'] ?? 0)) }}</strong><span>SerpAPI templates</span></div>
                <div class="panel stat"><strong>{{ number_format((int) ($relatedCounts['recent_monitor_runs'] ?? 0)) }}</strong><span>runs in 7 days</span></div>
            </div>
        </section>

        <form class="settings-form" method="post" action="{{ route('sls.intelligence.crawlerSettings.update') }}">
            @csrf

            @foreach ($groups as $groupKey => $group)
                @php($groupDefinitions = collect($group['keys'] ?? [])->filter(fn ($key) => isset($definitions[$key])))
                @continue($groupDefinitions->isEmpty())

                <section class="panel settings-section" id="crawler-settings-{{ $groupKey }}">
                    <div class="settings-section-header">
                        <div>
                            <p class="eyebrow">{{ str_replace('_', ' ', $groupKey) }}</p>
                            <h2>{{ $group['title'] }}</h2>
                            <p class="muted">{{ $group['description'] }}</p>
                        </div>
                        <span class="muted">{{ $groupDefinitions->count() }} setting{{ $groupDefinitions->count() === 1 ? '' : 's' }}</span>
                    </div>

                    <div class="settings-grid">
                        @foreach ($groupDefinitions as $key)
                            @php($definition = $definitions[$key])
                            @php($setting = $settings->get($key))
                            @php($value = old('settings.' . $key, $setting?->setting_value ?? $definition['default']))
                            @php($inputId = 'setting-' . $key)

                            @php($helpId = $inputId . '-help')
                            @php($typeLabel = str_replace('_', ' ', (string) $definition['value_type']))
                            @php($exampleText = match ($definition['value_type']) {
                                'boolean' => 'Use true or false.',
                                'integer' => 'Use a whole number. Zero usually disables or removes the limit when the setting allows it.',
                                'time' => 'Use server-time HH:MM, for example 04:50.',
                                'csv_times' => 'Use comma-separated server-time HH:MM values, for example 02:35,03:05.',
                                'secret' => 'Stored as a setting value. Treat it like a credential.',
                                'text' => 'Use one value per line or comma-separated values when the description says it accepts a list.',
                                default => 'Use the configured text value for this crawler setting.',
                            })

                            <div class="setting-card" aria-describedby="{{ $helpId }}">
                                <div class="meta">
                                    <div class="setting-title">
                                        <label for="{{ $inputId }}">{{ $definition['label'] }}</label>
                                        <span class="setting-help">
                                            <button class="setting-help-button" type="button" aria-label="Explain {{ $definition['label'] }}" aria-describedby="{{ $helpId }}">?</button>
                                            <span class="setting-help-popover" id="{{ $helpId }}" role="tooltip">
                                                <strong>Purpose</strong>
                                                {{ $definition['description'] }}
                                                <br><br>
                                                <strong>Meaning</strong>
                                                Type: {{ $typeLabel }}. {{ $exampleText }}
                                            </span>
                                        </span>
                                    </div>
                                    <span class="key">{{ $key }}</span>
                                    <span class="setting-type">{{ $typeLabel }}</span>
                                </div>

                                @if ($definition['value_type'] === 'boolean')
                                    <select id="{{ $inputId }}" name="settings[{{ $key }}]">
                                        <option value="true" @selected(filter_var($value, FILTER_VALIDATE_BOOL))>true</option>
                                        <option value="false" @selected(! filter_var($value, FILTER_VALIDATE_BOOL))>false</option>
                                    </select>
                                @elseif ($definition['value_type'] === 'integer')
                                    <input id="{{ $inputId }}" type="number" min="0" step="1" name="settings[{{ $key }}]" value="{{ $value }}" autocomplete="off">
                                @elseif ($definition['value_type'] === 'time')
                                    <input id="{{ $inputId }}" type="time" name="settings[{{ $key }}]" value="{{ $value }}" autocomplete="off">
                                @elseif ($definition['value_type'] === 'csv_times')
                                    <textarea id="{{ $inputId }}" name="settings[{{ $key }}]" autocomplete="off">{{ $value }}</textarea>
                                    <p class="muted">Comma-separated HH:MM values, for example <code>02:35,03:05</code>.</p>
                                @elseif ($definition['value_type'] === 'secret')
                                    <input id="{{ $inputId }}" type="password" name="settings[{{ $key }}]" value="{{ $value }}" autocomplete="off">
                                @elseif (str_contains($key, 'endpoint') || str_contains($key, 'scope'))
                                    <textarea id="{{ $inputId }}" name="settings[{{ $key }}]" autocomplete="off">{{ $value }}</textarea>
                                @else
                                    <input id="{{ $inputId }}" type="text" name="settings[{{ $key }}]" value="{{ $value }}" autocomplete="off">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="actions sticky-save">
                <button type="submit">Save crawler settings</button>
            </div>
        </form>

        <section class="panel stack recent-runs">
            <div>
                <p class="eyebrow">Recent monitor runs</p>
                <h2>What the scheduled crawlers have been doing</h2>
                <p class="muted">Use this as a quick sanity check after changing schedules, limits, or source settings.</p>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Started</th>
                            <th>Country</th>
                            <th>Focus</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Sources checked</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentRuns as $run)
                            @php($statusClass = \Illuminate\Support\Str::slug((string) $run->status))
                            <tr>
                                <td>{{ optional($run->started_at)->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'Not started' }}</td>
                                <td>{{ $run->country?->name ?? 'Global' }}{{ $run->country?->iso_code ? ' (' . $run->country->iso_code . ')' : '' }}</td>
                                <td>{{ str_replace('_', ' ', (string) $run->focus) }}</td>
                                <td><span class="status-pill {{ $statusClass }}">{{ $run->status }}</span></td>
                                <td>{{ number_format((int) $run->items_found) }}</td>
                                <td>{{ collect($run->sources_checked ?? [])->take(5)->implode(', ') ?: 'None recorded' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">No monitor runs have been recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
