@extends('sls.layouts.app')

@section('title', 'Opportunity Intake')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'Opportunity Intake')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.review') }}">Review desk</a>
    <a class="button secondary" href="{{ route('sls.opportunities.index') }}">Opportunities</a>
@endsection

@push('head')
    <style>
        .intake-form { display:grid; gap:14px; max-width:1040px; }
        .intake-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
        .intake-form label { color:var(--text-secondary); display:grid; font-weight:800; gap:5px; }
        .intake-form input,
        .intake-form select,
        .intake-form textarea { box-sizing:border-box; width:100%; }
        .intake-form textarea { min-height:190px; resize:vertical; }
        .intake-help { color:var(--text-secondary); font-size:.9rem; line-height:1.45; }
        @media (max-width:900px) { .intake-grid { grid-template-columns:1fr; } }
    </style>
@endpush

@section('content')
    <section class="panel">
        <form class="intake-form" method="post" action="{{ route('sls.intelligence.opportunityIntake.store') }}">
            @csrf

            @if ($errors->any())
                <div class="panel" style="border-color:#fecaca;color:#b91c1c;background:#fff7f7;">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <p class="eyebrow">Manual alert triage</p>
                <h2>Paste an online or email-alert opportunity</h2>
                <p class="intake-help">Paste the alert text, a tracking link, or the direct source URL. SLS will decode common tracking links, infer country/title/project when it can, and send the item into Review Desk as a captured opportunity.</p>
            </div>

            <label>
                Alert text
                <textarea name="alert_text" placeholder="Paste the email alert or online snippet here. Include lines like Title, Project Country, and Project Title when available.">{{ old('alert_text') }}</textarea>
            </label>

            <div class="intake-grid">
                <label>
                    Source URL, if separate
                    <input name="source_url" value="{{ old('source_url') }}" placeholder="https://...">
                </label>
                <label>
                    Source name
                    <input name="source_name" value="{{ old('source_name') }}" placeholder="Auto-filled from URL if blank" maxlength="255">
                </label>
            </div>

            <div class="intake-grid">
                <label>
                    Title
                    <input name="title" value="{{ old('title') }}" placeholder="Auto-read from Title line if blank" maxlength="500">
                </label>
                <label>
                    Project title
                    <input name="project_title" value="{{ old('project_title') }}" placeholder="Auto-read from Project Title line if blank" maxlength="500">
                </label>
            </div>

            <div class="intake-grid">
                <label>
                    Country
                    <select name="country_id">
                        <option value="">Auto-detect from Project Country line</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}" @selected((string) old('country_id') === (string) $country->id)>{{ $country->name }} ({{ strtoupper($country->iso_code) }})</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Country name, if not in list
                    <input name="country_name" value="{{ old('country_name') }}" placeholder="Example: Iraq" maxlength="255">
                </label>
            </div>

            <div class="intake-grid">
                <label>
                    Type
                    <select name="item_type">
                        <option value="auto" @selected(old('item_type', 'auto') === 'auto')>Auto</option>
                        <option value="tender" @selected(old('item_type') === 'tender')>Tender / RFP</option>
                        <option value="news" @selected(old('item_type') === 'news')>News / intelligence</option>
                    </select>
                </label>
                <label>
                    Focus
                    <select name="focus">
                        <option value="auto" @selected(old('focus', 'auto') === 'auto')>Auto</option>
                        @foreach ($focuses as $key => $focus)
                            <option value="{{ $key }}" @selected(old('focus') === $key)>{{ $focus['label'] ?? $key }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="intake-grid">
                <label>
                    Map product now
                    <select name="product_id">
                        <option value="">Do not map yet</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Internal note
                    <input name="notes" value="{{ old('notes') }}" placeholder="Optional quick context for why this matters">
                </label>
            </div>

            <div class="toolbar">
                <button class="button" type="submit">Capture opportunity</button>
                <a class="button secondary" href="{{ route('sls.intelligence.review') }}">Cancel</a>
            </div>
        </form>
    </section>
@endsection