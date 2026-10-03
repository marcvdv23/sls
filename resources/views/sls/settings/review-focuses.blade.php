@extends('sls.layouts.app')

@section('title', 'Review Categories')
@section('eyebrow', 'Setup')
@section('page_title', 'Review Category Settings')

@php
    $lineValue = function ($value) {
        if (is_array($value)) {
            return implode("\n", $value);
        }

        return (string) $value;
    };
@endphp

@push('head')
    <style>
        .focus-settings-grid { display: grid; grid-template-columns: minmax(320px, .75fr) minmax(620px, 1.25fr); gap: 14px; align-items: start; }
        .focus-form { display: grid; gap: 12px; }
        .focus-form-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 10px; align-items: end; }
        .focus-form label { color: var(--text-secondary); display: grid; font-size: 12px; font-weight: 800; gap: 5px; }
        .focus-form .span-2 { grid-column: span 2; }
        .focus-form .span-3 { grid-column: span 3; }
        .focus-form .span-4 { grid-column: span 4; }
        .focus-form .span-5 { grid-column: span 5; }
        .focus-form .span-6 { grid-column: span 6; }
        .focus-form .span-7 { grid-column: span 7; }
        .focus-form .span-12 { grid-column: span 12; }
        .focus-form textarea { min-height: 90px; }
        .focus-table { min-width: 1120px; }
        .focus-table td { vertical-align: top; }
        .focus-key { color: var(--text-secondary); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; font-weight: 800; }
        .inline-check { align-items: center; display: inline-flex; gap: 7px; font-weight: 800; }
        @media (max-width: 1180px) {
            .focus-settings-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .focus-form-grid { grid-template-columns: 1fr; }
            .focus-form .span-2,
            .focus-form .span-3,
            .focus-form .span-4,
            .focus-form .span-5,
            .focus-form .span-6,
            .focus-form .span-7,
            .focus-form .span-12 { grid-column: auto; }
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
                <p class="muted">Review categories need the review focus table. Deploy this update and run <code>php artisan migrate --force</code>, then <code>php artisan optimize:clear</code>.</p>
            </section>
        @else
            <div class="focus-settings-grid">
                <section class="panel stack">
                    <div>
                        <p class="eyebrow">Create category</p>
                        <h2>Add a review focus</h2>
                        <p class="muted">Categories drive Review Desk filters, country intelligence labels, and focus-specific keyword matching. Existing keys stay stable so older captured items keep mapping correctly.</p>
                    </div>

                    <form class="focus-form" method="post" action="{{ route('sls.settings.reviewFocuses.store') }}">
                        @csrf
                        <div class="focus-form-grid">
                            <label class="span-7">Label
                                <input name="label" value="{{ old('label') }}" placeholder="Sustainability Policy Intelligence" required>
                            </label>
                            <label class="span-5">Key
                                <input name="focus_key" value="{{ old('focus_key') }}" placeholder="sustainability_policy" required>
                            </label>
                            <label class="span-3">Sort order
                                <input name="sort_order" type="number" value="{{ old('sort_order', 100) }}">
                            </label>
                            <div class="span-4">
                                <label class="inline-check">
                                    <input name="is_enabled" type="checkbox" value="1" @checked(old('is_enabled', true))>
                                    Enabled
                                </label>
                            </div>
                            <div class="span-5">
                                <label class="inline-check">
                                    <input name="is_default" type="checkbox" value="1" @checked(old('is_default'))>
                                    Default category
                                </label>
                            </div>
                            <label class="span-12">Description
                                <textarea name="description" placeholder="What belongs in this review category">{{ old('description') }}</textarea>
                            </label>
                            <label class="span-12">Terms, one per line
                                <textarea name="terms" placeholder="sustainability&#10;environmental policy&#10;climate finance">{{ old('terms') }}</textarea>
                            </label>
                            <label class="span-12">Strong signals, one per line
                                <textarea name="strong_signals" placeholder="climate action plan&#10;environmental impact assessment">{{ old('strong_signals') }}</textarea>
                            </label>
                        </div>
                        <button class="button primary" type="submit">Add review category</button>
                    </form>
                </section>

                <section class="panel stack">
                    <div>
                        <p class="eyebrow">Configured categories</p>
                        <h2>Manage Review Desk focus areas</h2>
                        <p class="muted">Terms and strong signals are matched by the intelligence pipeline. Extra crawler metadata from the legacy config is preserved behind the scenes.</p>
                    </div>

                    <div class="table-wrap">
                        <table class="focus-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Terms</th>
                                    <th>Signals</th>
                                    <th>State</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reviewFocuses as $focus)
                                    @php($formId = 'review-focus-form-' . $focus->id)
                                    <tr>
                                        <td>
                                            <form id="{{ $formId }}" method="post" action="{{ route('sls.settings.reviewFocuses.update', $focus) }}">
                                                @csrf
                                            </form>
                                            <input form="{{ $formId }}" name="label" value="{{ old('label', $focus->label) }}" required>
                                            <div class="focus-key">{{ $focus->focus_key }}</div>
                                            <input form="{{ $formId }}" name="sort_order" type="number" value="{{ old('sort_order', $focus->sort_order ?? 0) }}">
                                        </td>
                                        <td><textarea form="{{ $formId }}" name="description" placeholder="Description">{{ old('description', $focus->description) }}</textarea></td>
                                        <td><textarea form="{{ $formId }}" name="terms" placeholder="One term per line">{{ old('terms', $lineValue($focus->terms ?? [])) }}</textarea></td>
                                        <td><textarea form="{{ $formId }}" name="strong_signals" placeholder="One strong signal per line">{{ old('strong_signals', $lineValue($focus->strong_signals ?? [])) }}</textarea></td>
                                        <td>
                                            <label class="inline-check">
                                                <input form="{{ $formId }}" name="is_enabled" type="checkbox" value="1" @checked(old('is_enabled', $focus->is_enabled))>
                                                Enabled
                                            </label>
                                            <label class="inline-check">
                                                <input form="{{ $formId }}" name="is_default" type="checkbox" value="1" @checked(old('is_default', $focus->is_default))>
                                                Default
                                            </label>
                                        </td>
                                        <td><button form="{{ $formId }}" class="button secondary" type="submit">Save</button></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="muted">No review categories are configured yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        @endif
    </div>
@endsection
