@extends('sls.layouts.app')

@section('title', 'Priority Opportunity Settings')
@section('eyebrow', 'Setup')
@section('page_title', 'Priority Opportunity Settings')

@php
    $groupLabels = [
        'status' => 'Pipeline statuses',
        'priority' => 'Priority levels',
        'focus_tier' => 'Focus tiers',
    ];
@endphp

@push('head')
    <style>
        .priority-settings-grid { display:grid; grid-template-columns:minmax(320px, .72fr) minmax(640px, 1.28fr); gap:14px; align-items:start; }
        .priority-config-form { display:grid; gap:12px; }
        .priority-config-grid { display:grid; grid-template-columns:repeat(12, minmax(0, 1fr)); gap:10px; align-items:end; }
        .priority-config-form label { color:var(--text-secondary); display:grid; font-size:12px; font-weight:800; gap:5px; }
        .priority-config-form .span-3 { grid-column:span 3; }
        .priority-config-form .span-4 { grid-column:span 4; }
        .priority-config-form .span-5 { grid-column:span 5; }
        .priority-config-form .span-6 { grid-column:span 6; }
        .priority-config-form .span-7 { grid-column:span 7; }
        .priority-config-form .span-8 { grid-column:span 8; }
        .priority-config-form .span-12 { grid-column:span 12; }
        .priority-options-table { min-width:1180px; }
        .priority-options-table td { vertical-align:top; }
        .option-key { color:var(--text-secondary); font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; font-weight:800; }
        .inline-check { align-items:center; display:inline-flex; gap:7px; font-weight:800; }
        @media (max-width:1180px) {
            .priority-settings-grid { grid-template-columns:1fr; }
        }
        @media (max-width:760px) {
            .priority-config-grid { grid-template-columns:1fr; }
            .priority-config-form .span-3,
            .priority-config-form .span-4,
            .priority-config-form .span-5,
            .priority-config-form .span-6,
            .priority-config-form .span-7,
            .priority-config-form .span-8,
            .priority-config-form .span-12 { grid-column:auto; }
        }
    </style>
@endpush

@section('content')
    <div class="stack">
        @if ($errors->any())
            <section class="panel" style="color:#b91c1c;border-color:#fecaca;background:#fff7f7;">{{ $errors->first() }}</section>
        @endif

        @if (session('status'))
            <section class="panel" style="color:#047857;border-color:#99f6e4;background:#ecfdf5;">{{ session('status') }}</section>
        @endif

        @if ($migrationMissing)
            <section class="panel">
                <p class="eyebrow">Setup required</p>
                <h2>Run migrations first</h2>
                <p class="muted">Priority opportunity settings need the options table. Deploy this update and run <code>php artisan migrate --force</code>, then <code>php artisan optimize:clear</code>.</p>
            </section>
        @else
            <div class="priority-settings-grid">
                <section class="panel stack">
                    <div>
                        <p class="eyebrow">Import defaults</p>
                        <h2>Configure shortlist intake</h2>
                        <p class="muted">These defaults are used when CSV shortlist rows create opportunities, tasks, and new CRM accounts.</p>
                    </div>

                    <form class="priority-config-form" method="post" action="{{ route('sls.settings.priorityOpportunities.settings') }}">
                        @csrf
                        <div class="priority-config-grid">
                            @foreach ($settings as $setting)
                                @php($fieldName = 'settings[' . $setting['key'] . ']')
                                <label class="span-6">{{ $setting['label'] }}
                                    @if ($setting['value_type'] === 'select:status')
                                        <select name="{{ $fieldName }}">
                                            @foreach ($statuses as $key => $label)
                                                <option value="{{ $key }}" @selected((string) $setting['value'] === (string) $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($setting['value_type'] === 'select:priority')
                                        <select name="{{ $fieldName }}">
                                            @foreach ($priorities as $key => $label)
                                                <option value="{{ $key }}" @selected((string) $setting['value'] === (string) $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($setting['value_type'] === 'integer')
                                        <input name="{{ $fieldName }}" type="number" value="{{ $setting['value'] }}">
                                    @else
                                        <input name="{{ $fieldName }}" value="{{ $setting['value'] }}">
                                    @endif
                                    <span class="muted">{{ $setting['description'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        <button class="button primary" type="submit">Save import defaults</button>
                    </form>

                    <div>
                        <p class="eyebrow">Create option</p>
                        <h2>Add a status, priority, or tier</h2>
                    </div>
                    <form class="priority-config-form" method="post" action="{{ route('sls.settings.priorityOpportunities.options.store') }}">
                        @csrf
                        <div class="priority-config-grid">
                            <label class="span-4">Type
                                <select name="option_group">
                                    @foreach ($groupLabels as $group => $label)
                                        <option value="{{ $group }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="span-4">Key
                                <input name="option_key" placeholder="active_pursuit" required>
                            </label>
                            <label class="span-4">Label
                                <input name="label" placeholder="Active pursuit" required>
                            </label>
                            <label class="span-3">Sort order
                                <input name="sort_order" type="number" value="100">
                            </label>
                            <div class="span-3">
                                <label class="inline-check"><input name="is_enabled" type="checkbox" value="1" checked> Enabled</label>
                            </div>
                            <div class="span-3">
                                <label class="inline-check"><input name="is_default" type="checkbox" value="1"> Default</label>
                            </div>
                            <label class="span-12">Description
                                <textarea name="description" rows="3" placeholder="Internal meaning or usage notes"></textarea>
                            </label>
                        </div>
                        <button class="button secondary" type="submit">Add option</button>
                    </form>
                </section>

                <section class="panel stack">
                    <div>
                        <p class="eyebrow">Configured options</p>
                        <h2>Pipeline vocabulary</h2>
                        <p class="muted">Status and priority keys are stored in records. Focus tier labels are used for imported shortlist tiers and filters.</p>
                    </div>

                    <div class="table-wrap">
                        <table class="priority-options-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Option</th>
                                    <th>Description</th>
                                    <th>State</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($optionGroups as $group => $options)
                                    @foreach ($options as $option)
                                        @php($formId = 'priority-option-form-' . $option->id)
                                        <tr>
                                            <td>{{ $groupLabels[$group] ?? $group }}</td>
                                            <td>
                                                <form id="{{ $formId }}" method="post" action="{{ route('sls.settings.priorityOpportunities.options.update', $option) }}">
                                                    @csrf
                                                </form>
                                                <input form="{{ $formId }}" name="label" value="{{ $option->label }}" required>
                                                <div class="option-key">{{ $option->option_key }}</div>
                                                <input form="{{ $formId }}" name="sort_order" type="number" value="{{ $option->sort_order ?? 0 }}">
                                            </td>
                                            <td><textarea form="{{ $formId }}" name="description" rows="4" placeholder="Description">{{ $option->description }}</textarea></td>
                                            <td>
                                                <label class="inline-check"><input form="{{ $formId }}" name="is_enabled" type="checkbox" value="1" @checked($option->is_enabled)> Enabled</label>
                                                <label class="inline-check"><input form="{{ $formId }}" name="is_default" type="checkbox" value="1" @checked($option->is_default)> Default</label>
                                            </td>
                                            <td><button form="{{ $formId }}" class="button secondary" type="submit">Save</button></td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr><td colspan="5" class="muted">No options configured yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        @endif
    </div>
@endsection
