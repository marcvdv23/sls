@extends('sls.layouts.app')

@section('title', 'Awarded Companies')
@section('eyebrow', 'Tender Intelligence')
@section('page_title', 'Awarded Companies')

@section('topbar_actions')
    <a class="button" href="{{ route('sls.intelligence.awardedCompanies.create') }}">Add awarded company</a>
    <a class="button secondary" href="{{ route('sls.intelligence.review') }}">Review desk</a>
@endsection

@push('head')
    <style>
        .awarded-filters { display:grid; grid-template-columns: 1.4fr 1fr 1fr 1fr auto; gap:10px; align-items:end; }
        .awarded-filters label { display:grid; gap:5px; color:var(--text-secondary); font-size:11px; font-weight:900; letter-spacing:.04em; text-transform:uppercase; }
        .awarded-filters input, .awarded-filters select { width:100%; border:1px solid var(--border-subtle); border-radius:8px; background:var(--bg-primary); color:var(--text-primary); padding:9px 10px; font:inherit; }
        .awarded-table { width:100%; border-collapse:collapse; }
        .awarded-table th, .awarded-table td { padding:10px 11px; border-bottom:1px solid var(--border-subtle); vertical-align:top; text-align:left; }
        .awarded-table th { color:var(--text-secondary); font-size:11px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; }
        .awarded-company { font-weight:900; }
        .row-actions { display:flex; gap:10px; align-items:center; white-space:nowrap; }
        .text-action { color:var(--accent); font-weight:900; text-decoration:none; border:0; background:transparent; padding:0; cursor:pointer; }
        .text-action.danger { color:#dc2626; }
        .tiny-select { max-width:155px; border:1px solid var(--border-subtle); border-radius:8px; background:var(--bg-primary); padding:6px 8px; }
        .contact-line, .contract-line { color:var(--text-secondary); }
        @media (max-width: 900px) { .awarded-filters { grid-template-columns:1fr; } .awarded-table { min-width:980px; } .table-scroll { overflow:auto; } }
    </style>
@endpush

@section('content')
    <section class="panel">
        <p class="eyebrow">Awarded contract companies</p>
        <h2>Winners to track as partner or competitor candidates</h2>
        <p class="muted">These companies are linked to tender award records, products, countries, and CRM organization records.</p>

        <form class="awarded-filters" method="get" action="{{ route('sls.intelligence.awardedCompanies.index') }}">
            <label>Search
                <input name="q" value="{{ $filters['q'] }}" placeholder="Company, contract, email, phone">
            </label>
            <label>Country
                <select name="country">
                    <option value="">All countries</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country }}" @selected($filters['country'] === $country)>{{ $country }}</option>
                    @endforeach
                </select>
            </label>
            <label>Product
                <select name="product_id">
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) $filters['product_id'] === (string) $product->id)>{{ str_replace('Interact ', '', $product->name) }}</option>
                    @endforeach
                </select>
            </label>
            <label>Status
                <select name="status">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $code => $label)
                        <option value="{{ $code }}" @selected($filters['status'] === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button" type="submit">Filter</button>
        </form>
    </section>

    <section class="panel table-scroll">
        <table class="awarded-table">
            <thead>
                <tr>
                    <th>Company</th>
                    <th>Country</th>
                    <th>Product</th>
                    <th>Contract info</th>
                    <th>Contact</th>
                    <th>Source</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($awardedCompanies as $award)
                    <tr>
                        <td>
                            <div class="awarded-company">{{ $award->company_name }}</div>
                            <div class="row-actions">
                                @if ($award->organization)
                                    <a class="text-action" href="{{ route('sls.organizations.show', $award->organization) }}">Org</a>
                                    <a class="text-action" href="{{ route('sls.organizations.edit', $award->organization) }}">Edit</a>
                                @endif
                                @if ($award->website_url)
                                    <a class="text-action" href="{{ $award->website_url }}" target="_blank" rel="noopener noreferrer">Site</a>
                                @endif
                            </div>
                        </td>
                        <td>{{ $award->country_name ?: $award->country?->name }} @if($award->country_iso)<span class="muted">({{ $award->country_iso }})</span>@endif</td>
                        <td>{{ $award->product?->name ? str_replace('Interact ', '', $award->product->name) : 'Not mapped' }}</td>
                        <td>
                            <strong>{{ $award->contract_title ?: 'Contract title not captured' }}</strong>
                            @if ($award->contract_reference)<div class="contract-line">Ref: {{ $award->contract_reference }}</div>@endif
                            @if ($award->contract_value)<div class="contract-line">Value: {{ trim(($award->currency ? $award->currency . ' ' : '') . $award->contract_value) }}</div>@endif
                            @if ($award->award_date)<div class="contract-line">Awarded {{ $award->award_date->format('Y-m-d') }}</div>@endif
                            @if ($award->contract_info)<div class="contract-line">{{ Str::limit($award->contract_info, 180) }}</div>@endif
                        </td>
                        <td>
                            @if ($award->email)<div><a href="mailto:{{ $award->email }}">{{ $award->email }}</a></div>@endif
                            @if ($award->phone)<div class="contact-line">{{ $award->phone }}</div>@endif
                            @if (! $award->email && ! $award->phone)<span class="muted">Not captured</span>@endif
                        </td>
                        <td>
                            @if ($award->countryUpdate)
                                <a class="text-action" href="{{ route('sls.intelligence.updates.sourcePage', $award->countryUpdate) }}">Tender</a>
                            @endif
                            @if ($award->award_url)
                                <a class="text-action" href="{{ $award->award_url }}" target="_blank" rel="noopener noreferrer">Award</a>
                            @endif
                            <div class="muted">{{ $award->source_name }}</div>
                        </td>
                        <td>
                            <form method="post" action="{{ route('sls.intelligence.awardedCompanies.update', $award) }}">
                                @csrf
                                <select class="tiny-select" name="relationship_status">
                                    @foreach ($statusOptions as $code => $label)
                                        <option value="{{ $code }}" @selected($award->relationship_status === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select class="tiny-select" name="product_id">
                                    <option value="">No product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" @selected((int) $award->product_id === (int) $product->id)>{{ str_replace('Interact ', '', $product->name) }}</option>
                                    @endforeach
                                </select>
                                <button class="text-action" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No awarded companies have been captured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection