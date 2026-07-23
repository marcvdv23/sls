@extends('sls.layouts.app')

@section('title')
Edit {{ $organization->name }} - 1G-SLS
@endsection
@section('eyebrow', '1G-SLS')
@section('page_title', 'Edit organization')

@push('head')
    <style>
        .legacy-page { display:grid; gap:14px; }
        .legacy-page > header {
            display:flex;
            align-items:flex-end;
            justify-content:space-between;
            gap:12px;
            background:var(--bg-secondary);
            border:1px solid var(--border-subtle);
            border-radius:var(--radius-card);
            box-shadow:var(--card-shadow);
            padding:14px;
        }
        .legacy-page > main { display:grid; gap:14px; padding:0; }
        .legacy-page .actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .edit-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
        .edit-grid .wide { grid-column:1 / -1; }
        .legacy-page label { display:grid; gap:5px; color:var(--text-secondary); font-size:12px; font-weight:800; }
        .legacy-page input,
        .legacy-page select,
        .legacy-page textarea { width:100%; box-sizing:border-box; }
        .contact-editor { display:grid; gap:10px; }
        .contact-row {
            display:grid;
            grid-template-columns:110px minmax(150px, 1fr) minmax(150px, 1fr) minmax(170px, 1fr) minmax(120px, .7fr) 78px;
            gap:8px;
            align-items:start;
            border-bottom:1px solid var(--border-subtle);
            padding-bottom:10px;
        }
        .contact-row .wide { grid-column:1 / -1; }
        .delete-check { display:flex; align-items:center; gap:6px; min-height:38px; }
        .delete-check input { width:auto; }
        .error-box {
            border:1px solid #fecaca;
            border-radius:var(--radius-card);
            background:#fef2f2;
            color:#b91c1c;
            padding:12px;
        }
        @media (max-width:1100px) {
            .edit-grid { grid-template-columns:1fr; }
            .contact-row { grid-template-columns:1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page">
        <header>
            <div>
                <p class="eyebrow">Organization CRM</p>
                <h1>{{ $organization->name }}</h1>
                <p class="muted">Edit name, URLs, status, notes, and contact details.</p>
            </div>
            <div class="actions">
                <a class="button secondary" href="{{ route('sls.organizations.show', $organization) }}">Cancel</a>
                <a class="button secondary" href="{{ route('sls.organizations.index') }}">Organization directory</a>
            </div>
        </header>

        <main>
            @if ($errors->any())
                <div class="error-box">
                    <strong>Could not save yet.</strong>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            <form method="post" action="{{ route('sls.organizations.update', $organization) }}" class="stack">
                @csrf

                <section class="panel">
                    <p class="eyebrow">Core record</p>
                    <h2>Organization details</h2>
                    <div class="edit-grid" style="margin-top:.75rem;">
                        <label class="wide">Name
                            <input name="name" required value="{{ old('name', $organization->name) }}">
                        </label>
                        <label>Website
                            <input name="website_url" value="{{ old('website_url', $organization->website_url) }}" placeholder="https://example.gov">
                        </label>
                        <label>Phone
                            <input name="organization_phone" value="{{ old('organization_phone', $organization->organization_phone) }}">
                        </label>
                        <label>Type
                            <select name="organization_type" required>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" {{ old('organization_type', $organization->organization_type) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Industry
                            <select name="industry" required>
                                @foreach ($industries as $value => $label)
                                    <option value="{{ $value }}" {{ old('industry', $organization->industry) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Subcategory
                            <select name="organization_subcategory">
                                <option value="">None</option>
                                @foreach ($subcategories as $value => $label)
                                    <option value="{{ $value }}" {{ old('organization_subcategory', $organization->organization_subcategory) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Lead status
                            <select name="lead_status" required>
                                @foreach ($leadStatuses as $value => $label)
                                    <option value="{{ $value }}" {{ old('lead_status', $organization->lead_status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Country
                            <input name="country" value="{{ old('country', $organization->country) }}">
                        </label>
                        <label>Country ISO
                            <input name="country_iso" maxlength="2" value="{{ old('country_iso', $organization->country_iso) }}">
                        </label>
                        <label>Region
                            <input name="region" value="{{ old('region', $organization->region) }}">
                        </label>
                        <label>Lead source
                            <input name="lead_source" value="{{ old('lead_source', $organization->lead_source) }}">
                        </label>
                        <label>Procurement / tenders page
                            <input name="procurement_page_url" value="{{ old('procurement_page_url', $organization->procurement_page_url) }}">
                        </label>
                        <label>Leadership page
                            <input name="leadership_page_url" value="{{ old('leadership_page_url', $organization->leadership_page_url) }}">
                        </label>
                        <label>HR page
                            <input name="hr_page_url" value="{{ old('hr_page_url', $organization->hr_page_url) }}">
                        </label>
                        <label>IT page
                            <input name="it_page_url" value="{{ old('it_page_url', $organization->it_page_url) }}">
                        </label>
                        <label>News / press page
                            <input name="news_page_url" value="{{ old('news_page_url', $organization->news_page_url) }}">
                        </label>
                        <label class="wide">Notes
                            <textarea name="notes" rows="5">{{ old('notes', $organization->notes) }}</textarea>
                        </label>
                    </div>
                </section>

                <section class="panel">
                    <p class="eyebrow">Contacts</p>
                    <h2>Update contact details</h2>
                    <div class="contact-editor" style="margin-top:.75rem;">
                        @forelse ($organization->contacts as $contact)
                            <div class="contact-row">
                                <input type="hidden" name="contacts[{{ $contact->id }}][id]" value="{{ $contact->id }}">
                                <label>Role
                                    <input name="contacts[{{ $contact->id }}][contact_type]" value="{{ old("contacts.{$contact->id}.contact_type", $contact->contact_type) }}" placeholder="general">
                                </label>
                                <label>Name
                                    <input name="contacts[{{ $contact->id }}][person_name]" value="{{ old("contacts.{$contact->id}.person_name", $contact->person_name) }}">
                                </label>
                                <label>Title
                                    <input name="contacts[{{ $contact->id }}][job_title]" value="{{ old("contacts.{$contact->id}.job_title", $contact->job_title) }}">
                                </label>
                                <label>Email
                                    <input name="contacts[{{ $contact->id }}][email]" type="email" value="{{ old("contacts.{$contact->id}.email", $contact->email) }}">
                                </label>
                                <label>Phone
                                    <input name="contacts[{{ $contact->id }}][phone]" value="{{ old("contacts.{$contact->id}.phone", $contact->phone) }}">
                                </label>
                                <label class="delete-check"><input type="checkbox" name="contacts[{{ $contact->id }}][delete]" value="1"> Delete</label>
                                <label class="wide">Source URL
                                    <input name="contacts[{{ $contact->id }}][source_url]" value="{{ old("contacts.{$contact->id}.source_url", $contact->source_url) }}">
                                </label>
                                <label class="wide">Notes
                                    <textarea name="contacts[{{ $contact->id }}][notes]" rows="2">{{ old("contacts.{$contact->id}.notes", $contact->notes) }}</textarea>
                                </label>
                            </div>
                        @empty
                            <p class="muted">No contacts linked yet. Add one below.</p>
                        @endforelse
                    </div>
                </section>

                <section class="panel">
                    <p class="eyebrow">New contacts</p>
                    <h2>Add contact rows</h2>
                    <div class="contact-editor" style="margin-top:.75rem;">
                        @for ($i = 0; $i < 3; $i++)
                            <div class="contact-row">
                                <label>Role
                                    <input name="new_contacts[{{ $i }}][contact_type]" value="{{ old("new_contacts.{$i}.contact_type") }}" placeholder="general">
                                </label>
                                <label>Name
                                    <input name="new_contacts[{{ $i }}][person_name]" value="{{ old("new_contacts.{$i}.person_name") }}">
                                </label>
                                <label>Title
                                    <input name="new_contacts[{{ $i }}][job_title]" value="{{ old("new_contacts.{$i}.job_title") }}">
                                </label>
                                <label>Email
                                    <input name="new_contacts[{{ $i }}][email]" type="email" value="{{ old("new_contacts.{$i}.email") }}">
                                </label>
                                <label>Phone
                                    <input name="new_contacts[{{ $i }}][phone]" value="{{ old("new_contacts.{$i}.phone") }}">
                                </label>
                                <span></span>
                                <label class="wide">Source URL
                                    <input name="new_contacts[{{ $i }}][source_url]" value="{{ old("new_contacts.{$i}.source_url") }}">
                                </label>
                                <label class="wide">Notes
                                    <textarea name="new_contacts[{{ $i }}][notes]" rows="2">{{ old("new_contacts.{$i}.notes") }}</textarea>
                                </label>
                            </div>
                        @endfor
                    </div>
                </section>

                <div class="actions">
                    <button class="button" type="submit">Save organization</button>
                    <a class="button secondary" href="{{ route('sls.organizations.show', $organization) }}">Cancel</a>
                </div>
            </form>
        </main>
    </div>
@endsection
