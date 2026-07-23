@extends('sls.layouts.app')

@section('title', 'Story Opportunities - 1G-SLS')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Story Opportunities')

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
        .legacy-page .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
        .legacy-page .filters,
        .legacy-page .actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .legacy-page label { color: var(--text-secondary); font-size: 12px; font-weight: 800; }
        .legacy-page table { background: var(--bg-secondary); }
        .legacy-page .empty { border: 1px dashed var(--border-subtle); border-radius: var(--radius-card); padding: 14px; }
        @media (max-width: 980px) {
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page">
<header>
            <div>
                <p class="eyebrow">Sales Opportunity Mapper</p>
                <h1>Story Opportunities</h1>
            </div>
            <div>
                <a class="button secondary" href="{{ route('sls.intelligence.review', ['focus' => 'social_security', 'region' => 'all', 'published' => 'last30', 'type' => 'news']) }}">Country intelligence</a>
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
            </div>
        </header>

        <main>
            <section class="panel">
                <p class="eyebrow">Drafts</p>
                <h2>News stories mapped to Interact SSAS</h2>
                @if ($opportunities->isEmpty())
                    <p class="muted">No opportunity drafts have been created yet. Open country intelligence and choose “Map to SSAS” on a relevant story.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>Issue area</th>
                                    <th>Story</th>
                                    <th>Product</th>
                                    <th>Stage</th>
                                    <th>Open</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($opportunities as $opportunity)
                                    <tr>
                                        <td>{{ $opportunity->countryUpdate?->country?->name ?? 'Unknown' }}</td>
                                        <td>{{ $opportunity->issue_area }}</td>
                                        <td>{{ $opportunity->countryUpdate?->title_english ?: $opportunity->countryUpdate?->title }}</td>
                                        <td>{{ $opportunity->product?->name ?? 'Interact SSAS' }}</td>
                                        <td>{{ $opportunity->opportunity_stage }}</td>
                                        <td><a href="{{ route('sls.opportunities.show', $opportunity) }}">Open draft</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </main>
    </div>
@endsection