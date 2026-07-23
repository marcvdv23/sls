@extends('sls.layouts.app')

@section('title', 'Add Awarded Company')
@section('eyebrow', 'Tender Intelligence')
@section('page_title', 'Add Awarded Company')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.awardedCompanies.index') }}">Awarded companies</a>
@endsection

@push('head')
    <style>
        .award-form { display:grid; grid-template-columns: repeat(2, minmax(240px, 1fr)); gap:12px; }
        .award-form label { display:grid; gap:5px; color:var(--text-secondary); font-size:11px; font-weight:900; letter-spacing:.04em; text-transform:uppercase; }
        .award-form input, .award-form select, .award-form textarea { width:100%; border:1px solid var(--border-subtle); border-radius:8px; background:var(--bg-primary); color:var(--text-primary); padding:10px 11px; font:inherit; }
        .award-form textarea { min-height:96px; resize:vertical; }
        .span-2 { grid-column: 1 / -1; }
        @media (max-width: 820px) { .award-form { grid-template-columns:1fr; } }
    </style>
@endpush

@section('content')
    <section class="panel">
        <p class="eyebrow">Manual capture</p>
        <h2>Company awarded a tender contract</h2>
        <p class="muted">Save winning contractors as CRM organizations and track whether they are partner candidates, competitors, or simply awarded vendors to monitor.</p>

        <form class="award-form" method="post" action="{{ route('sls.intelligence.awardedCompanies.store') }}">
            @csrf
            @if ($update)
                <input type="hidden" name="country_update_id" value="{{ $update->id }}">
            @endif

            <label>Company
                <input name="company_name" value="{{ old('company_name') }}" required placeholder="Awarded company name">
            </label>
            <label>Country
                <select name="country_id">
                    <option value="">Choose country</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected((string) old('country_id', $update?->country_id) === (string) $country->id)>{{ $country->name }} ({{ $country->iso_code }})</option>
                    @endforeach
                </select>
            </label>
            <label>Product tag
                <select name="product_id">
                    <option value="">No product yet</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) old('product_id', $update?->opportunities?->first()?->product_id) === (string) $product->id)>{{ str_replace('Interact ', '', $product->name) }}</option>
                    @endforeach
                </select>
            </label>
            <label>Status
                <select name="relationship_status">
                    @foreach ($statusOptions as $code => $label)
                        <option value="{{ $code }}" @selected(old('relationship_status', 'awarded_contractor') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Email
                <input name="email" value="{{ old('email') }}" placeholder="company or award contact email">
            </label>
            <label>Phone
                <input name="phone" value="{{ old('phone') }}" placeholder="company or award contact phone">
            </label>
            <label>Website
                <input name="website_url" value="{{ old('website_url') }}" placeholder="https://example.com">
            </label>
            <label>Award date
                <input type="date" name="award_date" value="{{ old('award_date', $update?->award_date?->format('Y-m-d')) }}">
            </label>
            <label class="span-2">Contract title
                <input name="contract_title" value="{{ old('contract_title', $update?->award_title ?: ($update?->title_english ?: $update?->title)) }}" placeholder="Contract or tender title">
            </label>
            <label>Contract reference
                <input name="contract_reference" value="{{ old('contract_reference') }}" placeholder="Tender / contract reference">
            </label>
            <label>Contract value
                <input name="contract_value" value="{{ old('contract_value') }}" placeholder="Amount if known">
            </label>
            <label>Currency
                <input name="currency" value="{{ old('currency') }}" placeholder="USD, EUR, etc.">
            </label>
            <label>Source name
                <input name="source_name" value="{{ old('source_name', $update?->award_source_name ?: $update?->source_name) }}" placeholder="World Bank, IDB, etc.">
            </label>
            <label class="span-2">Award/source URL
                <input name="award_url" value="{{ old('award_url', $update?->award_url ?: $update?->source_url) }}" placeholder="https://...">
            </label>
            <label class="span-2">Contract info
                <textarea name="contract_info" placeholder="Short details about what was awarded, scope, buyer, project, or why it matters">{{ old('contract_info', $update?->award_context ?: $update?->summary_english) }}</textarea>
            </label>
            <label class="span-2">Notes
                <textarea name="notes" placeholder="Internal partner / competitor notes">{{ old('notes') }}</textarea>
            </label>
            <div class="span-2">
                <button class="button" type="submit">Save awarded company</button>
            </div>
        </form>
    </section>
@endsection