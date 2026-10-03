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
        .setting-card { border: 1px solid var(--border-subtle); border-radius: 8px; padding: 12px; display: grid; gap: 9px; background: var(--bg-secondary); }
        .setting-card label { color: var(--text-primary); font-weight: 800; }
        .setting-card input, .setting-card select, .setting-card textarea { width: 100%; }
        .setting-card textarea { min-height: 76px; resize: vertical; }
        .setting-card .meta { display: grid; gap: 5px; color: var(--text-secondary); font-size: 13px; line-height: 1.4; }
        .setting-card .key { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 12px; color: var(--text-muted); overflow-wrap: anywhere; }
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

                            <div class="setting-card">
                                <div class="meta">
                                    <label for="{{ $inputId }}">{{ $definition['label'] }}</label>
                                    <span>{{ $definition['description'] }}</span>
                                    <span class="key">{{ $key }}</span>
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
