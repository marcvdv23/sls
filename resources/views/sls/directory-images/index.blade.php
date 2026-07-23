@extends('sls.layouts.app')

@section('title', 'Directory Image Import - 1G-SLS')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Directory Image Import')

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
                <p class="eyebrow">Market Intelligence</p>
                <h1>Directory Image Import</h1>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('sls.organizations.index') }}">Organization directory</a>
                <a class="button" href="{{ url('/sls') }}">Dashboard</a>
            </div>
        </header>
        <main class="stack">
            @if (session('status'))
                <section class="panel"><p class="muted">{{ session('status') }}</p></section>
            @endif
            <section class="panel">
                <p class="eyebrow">Upload</p>
                <h2>Upload up to 50 screenshots of text directories</h2>
                <p class="muted" style="margin-top:.35rem;">OCR will create draft structured entries. Because directory images are inconsistent, review the extracted company name, address, country, website, email, phone, and executive text before importing into the organization directory.</p>
                <form method="post" action="{{ route('sls.directoryImages.store') }}" enctype="multipart/form-data" class="stack" style="margin-top:.8rem;">
                    @csrf
                    <div class="grid">
                        <label>Batch title <input name="title" placeholder="IADC oil company directory pages"></label>
                        <label>Type
                            <select name="organization_type" required>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" {{ $value === 'oil_gas_company' ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Industry
                            <select name="industry" required>
                                @foreach ($industries as $value => $label)
                                    <option value="{{ $value }}" {{ $value === 'oil_gas' ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Subcategory
                            <select name="organization_subcategory">
                                <option value="">None</option>
                                @foreach ($subcategories as $value => $label)
                                    <option value="{{ $value }}" {{ $value === 'drilling_contractors' ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Crawler
                            <select name="market_crawler_id">
                                <option value="">Unassigned</option>
                                @foreach ($crawlers as $crawler)
                                    <option value="{{ $crawler->id }}">{{ $crawler->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Images
                            <input name="images[]" type="file" multiple accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                        </label>
                    </div>
                    <label>Notes <textarea name="notes" placeholder="Source, directory name, edition, or special parsing instructions"></textarea></label>
                    <button class="button" type="submit">Upload image batch</button>
                </form>
            </section>
            <section class="panel">
                <p class="eyebrow">Batches</p>
                <h2>Recent image imports</h2>
                <table>
                    <thead><tr><th>Batch</th><th>Type</th><th>Status</th><th>Images</th><th>Entries</th><th>Created</th></tr></thead>
                    <tbody>
                        @forelse ($batches as $batch)
                            <tr>
                                <td><strong><a href="{{ route('sls.directoryImages.show', $batch) }}">{{ $batch->title }}</a></strong><br><span class="muted">{{ $batch->notes }}</span></td>
                                <td>{{ $batch->organization_type }} / {{ $batch->industry }}{{ $batch->organization_subcategory ? ' / ' . $batch->organization_subcategory : '' }}</td>
                                <td><span class="pill">{{ $batch->status }}</span></td>
                                <td>{{ $batch->pages_count }}</td>
                                <td>{{ $batch->entries_count }}</td>
                                <td>{{ $batch->created_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">No image batches yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </main>
    </div>
@endsection