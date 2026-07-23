@extends('sls.layouts.app')

@section('title', 'Add Intelligence Story')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'Add story')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.review') }}">Review desk</a>
    <a class="button secondary" href="{{ route('sls.intelligence.world') }}">Map</a>
@endsection

@push('head')
    <style>
        .story-form { display:grid; gap:14px; max-width:980px; }
        .story-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
        .story-form label { display:grid; gap:5px; color:var(--text-secondary); font-weight:800; }
        .story-form input,
        .story-form select,
        .story-form textarea { width:100%; box-sizing:border-box; }
        .story-form textarea { min-height:120px; resize:vertical; }
        .story-help { color:var(--text-secondary); font-size:.86rem; }
        @media (max-width:900px) { .story-grid { grid-template-columns:1fr; } }
    </style>
@endpush

@section('content')
    <section class="panel">
        <form class="story-form" method="post" action="{{ route('sls.intelligence.stories.store') }}">
            @csrf

            @if ($errors->any())
                <div class="panel" style="border-color:#fecaca;color:#b91c1c;background:#fff7f7;">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <p class="eyebrow">Manual intake</p>
                <h2>Add a news story or tender</h2>
                <p class="story-help">Manual items are saved into the same Review Desk, map, favorites, and journalist workflows as crawler items.</p>
            </div>

            <div class="story-grid">
                <label>
                    Country
                    <select name="country_id" required>
                        <option value="">Choose country</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}" @selected((string) old('country_id') === (string) $country->id)>
                                {{ $country->name }} ({{ strtoupper($country->iso_code) }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Type
                    <select name="item_type" required>
                        <option value="news" @selected(old('item_type', 'news') === 'news')>News story</option>
                        <option value="tender" @selected(old('item_type') === 'tender')>Tender / RFP</option>
                    </select>
                </label>

                <label>
                    Focus
                    <select name="focus" required>
                        @foreach ($focuses as $key => $focus)
                            <option value="{{ $key }}" @selected(old('focus', 'social_security') === $key)>
                                {{ $focus['label'] ?? $key }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Publication date
                    <input type="date" name="publication_date" value="{{ old('publication_date') }}">
                </label>
            </div>

            <label>
                Story title
                <input name="title" value="{{ old('title') }}" maxlength="500" required>
            </label>

            <label>
                Original title, if different
                <input name="title_original" value="{{ old('title_original') }}" maxlength="500">
            </label>

            <div class="story-grid">
                <label>
                    Source URL
                    <input type="url" name="source_url" value="{{ old('source_url') }}" placeholder="https://..." required>
                </label>

                <label>
                    Publication / source name
                    <input name="source_name" value="{{ old('source_name') }}" placeholder="Auto-filled from URL if blank" maxlength="255">
                </label>
            </div>

            <label>
                Short summary
                <textarea name="summary" required placeholder="Paste or write the short paragraph we should keep with this item.">{{ old('summary') }}</textarea>
            </label>

            <div class="toolbar">
                <button class="button" type="submit">Add to Review Desk</button>
                <a class="button secondary" href="{{ route('sls.intelligence.review') }}">Cancel</a>
            </div>
        </form>
    </section>
@endsection
