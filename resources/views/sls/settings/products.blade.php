@extends('sls.layouts.app')

@section('title', 'Product Settings')
@section('eyebrow', 'Setup')
@section('page_title', 'Product & Service Settings')

@push('head')
    <style>
        .product-settings-grid { display: grid; grid-template-columns: minmax(320px, .8fr) minmax(520px, 1.2fr); gap: 14px; align-items: start; }
        .product-form { display: grid; gap: 12px; }
        .product-form-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 10px; align-items: end; }
        .product-form label { color: var(--text-secondary); display: grid; font-size: 12px; font-weight: 800; gap: 5px; }
        .product-form .span-3 { grid-column: span 3; }
        .product-form .span-4 { grid-column: span 4; }
        .product-form .span-6 { grid-column: span 6; }
        .product-form .span-8 { grid-column: span 8; }
        .product-form .span-12 { grid-column: span 12; }
        .product-form textarea { min-height: 90px; }
        .product-table { min-width: 980px; }
        .product-table td { vertical-align: top; }
        .product-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .inline-check { align-items: center; display: inline-flex; gap: 7px; font-weight: 800; }
        @media (max-width: 1100px) {
            .product-settings-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .product-form-grid { grid-template-columns: 1fr; }
            .product-form .span-3,
            .product-form .span-4,
            .product-form .span-6,
            .product-form .span-8,
            .product-form .span-12 { grid-column: auto; }
        }
    </style>
@endpush

@section('content')
    <div class="stack">
        @if ($errors->any())
            <section class="panel" style="color:#b91c1c;border-color:#fecaca;background:#fff7f7;">{{ $errors->first() }}</section>
        @endif

        @if ($migrationMissing)
            <section class="panel">
                <p class="eyebrow">Setup required</p>
                <h2>Run migrations first</h2>
                <p class="muted">Product settings need the product configuration columns. Deploy this update and run <code>php artisan migrate --force</code>, then <code>php artisan optimize:clear</code>.</p>
            </section>
        @else
            <div class="product-settings-grid">
                <section class="panel stack">
                    <div>
                        <p class="eyebrow">Create product/service</p>
                        <h2>Add a configured offering</h2>
                        <p class="muted">Use this for products, services, or solution areas that SLS should track across CRM, intelligence, documents, and future crawler/search profiles.</p>
                    </div>

                    <form class="product-form" method="post" action="{{ route('sls.settings.products.store') }}">
                        @csrf
                        <div class="product-form-grid">
                            <label class="span-8">Name
                                <input name="name" value="{{ old('name') }}" placeholder="Interact SSAS" required>
                            </label>
                            <label class="span-4">Code
                                <input name="code" value="{{ old('code') }}" placeholder="SSAS" required>
                            </label>
                            <label class="span-6">Category/focus
                                <input name="category" value="{{ old('category') }}" placeholder="Social security software">
                            </label>
                            <label class="span-3">Sort order
                                <input name="sort_order" type="number" value="{{ old('sort_order', 0) }}">
                            </label>
                            <label class="span-3">Status
                                <select name="status">
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="span-12">Description
                                <textarea name="description" placeholder="What this offering covers">{{ old('description') }}</textarea>
                            </label>
                            <div class="span-12">
                                <label class="inline-check">
                                    <input name="is_default" type="checkbox" value="1" @checked(old('is_default'))>
                                    Make this the default product
                                </label>
                            </div>
                        </div>
                        <button class="button primary" type="submit">Add product/service</button>
                    </form>
                </section>

                <section class="panel stack">
                    <div>
                        <p class="eyebrow">Configured products/services</p>
                        <h2>Manage active offerings</h2>
                        <p class="muted">Changing the default also updates the workspace default product setting used by intake and mapping screens.</p>
                    </div>

                    <div class="table-wrap">
                        <table class="product-table">
                            <thead>
                                <tr>
                                    <th>Offering</th>
                                    <th>Code</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Order</th>
                                    <th>Default</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($products as $product)
                                    @php($formId = 'product-form-' . $product->id)
                                    <tr>
                                        <td>
                                            <form id="{{ $formId }}" method="post" action="{{ route('sls.settings.products.update', $product) }}">
                                                @csrf
                                            </form>
                                            <input form="{{ $formId }}" name="name" value="{{ old('name', $product->name) }}" required>
                                            <textarea form="{{ $formId }}" name="description" placeholder="Description">{{ old('description', $product->description) }}</textarea>
                                        </td>
                                        <td><input form="{{ $formId }}" name="code" value="{{ old('code', $product->code) }}" required></td>
                                        <td><input form="{{ $formId }}" name="category" value="{{ old('category', $product->category) }}"></td>
                                        <td>
                                            <select form="{{ $formId }}" name="status">
                                                @foreach ($statusOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('status', $product->status ?: 'active') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input form="{{ $formId }}" name="sort_order" type="number" value="{{ old('sort_order', $product->sort_order ?? 0) }}"></td>
                                        <td>
                                            <label class="inline-check">
                                                <input form="{{ $formId }}" name="is_default" type="checkbox" value="1" @checked(old('is_default', $product->is_default))>
                                                Default
                                            </label>
                                        </td>
                                        <td>
                                            <div class="product-actions">
                                                <button form="{{ $formId }}" class="button secondary" type="submit">Save</button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="muted">No products are configured yet.</td>
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
