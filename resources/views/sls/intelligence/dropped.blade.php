@extends('sls.layouts.app')

@section('title', 'Dropped Intelligence Items')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'Dropped Items Review')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.review', ['focus' => $focus === 'all' ? 'all' : $focus, 'type' => 'tenders', 'limit' => 500]) }}">Active review</a>
    <a class="button secondary" href="{{ route('sls.intelligence.search', ['status' => 'rejected']) }}">Country search</a>
@endsection

@push('head')
    <style>
        .filters { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
        .dropped-table { min-width:1380px; table-layout:fixed; }
        .serial-col { width:7rem; }
        .country-col { width:10rem; }
        .focus-col { width:8rem; }
        .title-col { width:30rem; }
        .source-col { width:15rem; }
        .reason-col { width:20rem; }
        .date-col { width:9rem; }
        .action-col { width:8rem; }
        .title-cell,
        .reason-cell { overflow-wrap:anywhere; }
        .reason-cell strong { display:block; margin-bottom:4px; }
        .reason-counts { display:flex; flex-wrap:wrap; gap:8px; }
        .focus-cell .pill { white-space:normal; line-height:1.15; border-radius:8px; }
    </style>
@endpush

@section('content')
    @php($austinTz = 'America/Chicago')

    <div class="stack">
        <section class="panel">
            <p class="eyebrow">Rejected queue</p>
            <h2>{{ $updates->count() }} dropped items in this view</h2>
            <p class="muted">These records are hidden from the active review list. Keep the reason notes direct so the search and classification rules can be tuned from real mistakes.</p>
            <div class="reason-counts">
                @foreach ($reasonCounts as $reasonCode => $count)
                    <span class="pill">{{ $reasonOptions[$reasonCode] ?? Str::headline((string) $reasonCode) }}: {{ $count }}</span>
                @endforeach
            </div>
        </section>

        <section class="panel">
            <p class="eyebrow">Filters</p>
            <h2>Review dropped patterns</h2>
            <div class="filters">
                <a class="button {{ $focus === 'all' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.dropped', ['focus' => 'all', 'reason' => $reason]) }}">All focuses</a>
                @foreach ($focuses as $focusKey => $focusConfig)
                    <a class="button {{ $focus === $focusKey ? '' : 'secondary' }}" href="{{ route('sls.intelligence.dropped', ['focus' => $focusKey, 'reason' => $reason]) }}">{{ $focusConfig['label'] }}</a>
                @endforeach
            </div>
            <div class="filters">
                <a class="button {{ $reason === 'all' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.dropped', ['focus' => $focus, 'reason' => 'all']) }}">All reasons</a>
                @foreach ($reasonOptions as $reasonCode => $reasonLabel)
                    <a class="button {{ $reason === $reasonCode ? '' : 'secondary' }}" href="{{ route('sls.intelligence.dropped', ['focus' => $focus, 'reason' => $reasonCode]) }}">{{ $reasonLabel }}</a>
                @endforeach
            </div>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Dropped Records</p>
                <h2>Items excluded from active consideration</h2>
                <p class="muted">Showing up to {{ $displayLimit }} dropped records.</p>
            </div>

            <div class="table-wrap">
                <table class="data-table dropped-table">
                    <thead>
                        <tr>
                            <th class="serial-col">Serial</th>
                            <th class="country-col">Country</th>
                            <th class="focus-col">Focus</th>
                            <th class="title-col">English title</th>
                            <th class="title-col">Original title</th>
                            <th class="source-col">Source</th>
                            <th class="reason-col">Drop reason</th>
                            <th class="date-col">Dropped</th>
                            <th class="action-col">Restore</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($updates->isNotEmpty()): ?>
                        <?php foreach ($updates as $update): ?>
                            <?php
                                $serialNumber = 'INT-' . str_pad((string) $update->id, 5, '0', STR_PAD_LEFT);
                                $englishTitle = trim((string) $update->title_english);
                                $originalTitle = trim((string) ($update->title_original ?: $update->title));
                                $englishTitleIsUsable = \App\Support\TitleLanguage::isUsableEnglishTitle($englishTitle, $originalTitle);
                                $displayEnglishTitle = $englishTitleIsUsable
                                    ? $englishTitle
                                    : ($originalTitle !== '' && ! \App\Support\TitleLanguage::looksNonEnglish($originalTitle) ? $originalTitle : 'Translation pending');
                            ?>
                            <tr>
                                <td>{{ $serialNumber }}</td>
                                <td>{{ $update->country?->name ?? 'Unknown' }}</td>
                                <td class="focus-cell"><span class="pill">{{ $update->inferred_focus && isset($focuses[$update->inferred_focus]) ? $focuses[$update->inferred_focus]['label'] : 'Unclassified' }}</span></td>
                                <td class="title-cell">{{ $displayEnglishTitle }}</td>
                                <td class="title-cell muted">{{ $originalTitle }}</td>
                                <td>
                                    @if ($update->source_url)
                                        <a href="{{ route('sls.intelligence.updates.sourcePage', $update) }}">Open source</a>
                                        <p class="muted">{{ parse_url($update->source_url, PHP_URL_HOST) ?: $update->source_url }}</p>
                                    @else
                                        {{ $update->source_name ?: 'Unknown source' }}
                                    @endif
                                </td>
                                <td class="reason-cell">
                                    <strong>{{ $reasonOptions[$update->rejection_reason_code] ?? 'Other' }}</strong>
                                    <span class="muted">{{ $update->rejection_reason ?: 'No note captured.' }}</span>
                                </td>
                                <td>{{ $update->rejected_at?->copy()->timezone($austinTz)->format('Y-m-d H:i') ?: 'Not captured' }}</td>
                                <td>
                                    <form method="post" action="{{ route('sls.intelligence.updates.restore', $update) }}">
                                        @csrf
                                        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                        <button class="secondary tiny" type="submit">Restore</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="muted">No dropped items match this view yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
