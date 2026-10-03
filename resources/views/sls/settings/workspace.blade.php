@extends('sls.layouts.app')

@section('title', 'Workspace Settings')
@section('eyebrow', 'Setup')
@section('page_title', 'Workspace Settings')

@push('head')
    <style>
        .settings-page { display: grid; gap: 14px; }
        .settings-intro { max-width: 920px; }
        .settings-group { display: grid; gap: 12px; }
        .settings-row {
            align-items: start;
            border-top: 1px solid var(--border-subtle);
            display: grid;
            gap: 14px;
            grid-template-columns: minmax(240px, 360px) minmax(260px, 1fr);
            padding-top: 12px;
        }
        .settings-row:first-of-type { border-top: 0; padding-top: 0; }
        .settings-meta { color: var(--text-secondary); display: grid; font-size: 13px; gap: 4px; }
        .settings-meta label { color: var(--text-primary); font-size: 14px; font-weight: 800; }
        .settings-control input,
        .settings-control textarea { width: 100%; }
        .settings-control textarea { min-height: 96px; }
        @media (max-width: 900px) {
            .settings-row { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="settings-page">
        <section class="panel settings-intro">
            <p class="eyebrow">Productization</p>
            <h2>Configure this SLS workspace</h2>
            <p class="muted">These values control the visible platform, entity, workspace, and default product labels for this deployment. They keep this instance configurable while the broader tenant and workspace separation is built in later phases.</p>
        </section>

        <section class="panel settings-intro">
            <p class="eyebrow">Current workspace record</p>
            @if ($workspacesTableReady && $currentWorkspace)
                <h2>{{ $currentWorkspace->entity_name }} / {{ $currentWorkspace->name }}</h2>
                <p class="muted">Key: <code>{{ $currentWorkspace->workspace_key }}</code> · Entity: <code>{{ $currentWorkspace->entity_key }}</code> · Status: {{ $currentWorkspace->status }}</p>
                <p class="muted">Configuration tables now store rows against this selected workspace. Use the sidebar selector to switch workspaces.</p>
                @if ($workspaces->count() > 1)
                    <form method="post" action="{{ route('sls.workspaces.current') }}" style="display:flex;gap:10px;align-items:end;margin-top:12px;max-width:520px;">
                        @csrf
                        <label style="display:grid;gap:5px;flex:1;">
                            <span class="muted" style="font-weight:800;">Active workspace</span>
                            <select name="workspace_id">
                                @foreach ($workspaces as $workspace)
                                    <option value="{{ $workspace->id }}" @selected($currentWorkspace->id === $workspace->id)>
                                        {{ $workspace->entity_name }} / {{ $workspace->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <button class="button secondary" type="submit">Switch</button>
                    </form>
                @endif
                @if (($workspaceMemberships ?? collect())->isNotEmpty())
                    <div style="margin-top:14px;">
                        <p class="muted" style="font-weight:800;margin-bottom:6px;">Workspace members</p>
                        <div style="display:grid;gap:6px;max-width:720px;">
                            @foreach ($workspaceMemberships as $membership)
                                <div style="display:flex;gap:10px;align-items:center;justify-content:space-between;border-top:1px solid var(--border-subtle);padding-top:6px;">
                                    <span>{{ $membership->user?->name ?: $membership->user?->email ?: 'User #' . $membership->user_id }}</span>
                                    <span class="muted">{{ $membership->role }} · {{ $membership->status }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @elseif ($workspacesTableReady)
                <h2>No workspace record found</h2>
                <p class="muted">The workspace table exists, but the default workspace has not been seeded yet.</p>
            @else
                <h2>Workspace table pending</h2>
                <p class="muted">Run the latest migration to create the workspace record and backfill existing configuration rows.</p>
            @endif
        </section>

        @if ($errors->any())
            <section class="panel" style="color:#b91c1c;border-color:#fecaca;background:#fff7f7;">{{ $errors->first() }}</section>
        @endif

        @if ($migrationMissing)
            <section class="panel">
                <p class="eyebrow">Setup required</p>
                <h2>Run migrations first</h2>
                <p class="muted">Workspace settings need the <code>sls_settings</code> table. Deploy this update and run <code>php artisan migrate --force</code>, then <code>php artisan optimize:clear</code>.</p>
            </section>
        @else
            <form class="stack" method="post" action="{{ route('sls.settings.workspace.update') }}">
                @csrf

                @foreach ($settingsByGroup as $group => $settings)
                    <section class="panel settings-group">
                        <div>
                            <p class="eyebrow">{{ $group }}</p>
                            <h2>{{ $group }} settings</h2>
                        </div>

                        @foreach ($settings as $setting)
                            <div class="settings-row">
                                <div class="settings-meta">
                                    <label for="setting-{{ \Illuminate\Support\Str::slug($setting->setting_key) }}">{{ $setting->label ?? $setting->setting_key }}</label>
                                    @if ($setting->description)
                                        <span>{{ $setting->description }}</span>
                                    @endif
                                    <span>Key: <code>{{ $setting->setting_key }}</code></span>
                                </div>
                                <div class="settings-control">
                                    @if ($setting->value_type === 'text')
                                        <textarea id="setting-{{ \Illuminate\Support\Str::slug($setting->setting_key) }}" name="settings[{{ $setting->setting_key }}]">{{ old('settings.' . $setting->setting_key, $setting->setting_value) }}</textarea>
                                    @else
                                        <input id="setting-{{ \Illuminate\Support\Str::slug($setting->setting_key) }}" name="settings[{{ $setting->setting_key }}]" type="text" value="{{ old('settings.' . $setting->setting_key, $setting->setting_value) }}" autocomplete="off">
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endforeach

                <div class="actions">
                    <button class="button primary" type="submit">Save workspace settings</button>
                    <a class="button secondary" href="{{ url('/sls') }}">Dashboard</a>
                    <a class="button secondary" href="{{ url('/sls/serpapi-searches') }}">SerpAPI searches</a>
                </div>
            </form>
        @endif
    </div>
@endsection
