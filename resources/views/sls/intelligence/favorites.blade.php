@extends('sls.layouts.app')

@section('title', 'Intelligence Favorites')
@section('eyebrow', 'Country Intelligence')
@section('page_title', 'Favorites & Reminders')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.intelligence.search') }}">Country search</a>
    <a class="button secondary" href="{{ route('sls.intelligence.review') }}">Review desk</a>
@endsection

@section('content')
    <div class="stack">
        <section class="panel">
            <div class="toolbar">
                <div class="actions">
                    <a class="button {{ $status === 'open' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.favorites', ['status' => 'open']) }}">Open reminders</a>
                    <a class="button {{ $status === 'completed' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.favorites', ['status' => 'completed']) }}">Completed</a>
                    <a class="button {{ $status === 'all' ? '' : 'secondary' }}" href="{{ route('sls.intelligence.favorites', ['status' => 'all']) }}">All favorites</a>
                </div>
                <p class="muted">{{ $favorites->count() }} saved item(s)</p>
            </div>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Saved Items</p>
                <h2>Reminder queue</h2>
            </div>

            <div class="table-wrap">
                <table class="data-table" style="min-width:1220px;table-layout:fixed;">
                    <thead>
                        <tr>
                            <th style="width:10rem;">Country</th>
                            <th style="width:8.5rem;">Type</th>
                            <th style="width:31rem;">Title</th>
                            <th style="width:24rem;">Reminder note</th>
                            <th style="width:10rem;">Reminder</th>
                            <th style="width:10rem;">Saved</th>
                            <th style="width:13rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($favorites as $update)
                            <tr>
                                <td><strong>{{ $update->country?->name }}</strong></td>
                                <td>
                                    <span class="pill {{ $update->item_type === 'tender' ? 'warn' : 'good' }}">{{ $update->item_type }}</span>
                                    <p class="dim" style="margin-top:4px;">{{ $update->inferred_focus && isset($focuses[$update->inferred_focus]) ? $focuses[$update->inferred_focus]['label'] : 'Unclassified' }}</p>
                                </td>
                                <td style="overflow-wrap:anywhere;">
                                    <strong>{{ $update->title_english ?: $update->title }}</strong>
                                    @if ($update->source_name)
                                        <p class="muted">{{ $update->source_name }}</p>
                                    @endif
                                </td>
                                <td style="overflow-wrap:anywhere;">{{ $update->favorite_note ?: 'No note yet' }}</td>
                                <td>
                                    @if ($update->reminder_completed_at)
                                        <span class="pill good">Completed</span>
                                        <p class="dim">{{ $update->reminder_completed_at->copy()->timezone($austinTz)->format('Y-m-d H:i') }}</p>
                                    @elseif ($update->reminder_due_at)
                                        {{ $update->reminder_due_at->copy()->timezone($austinTz)->format('Y-m-d H:i') }}
                                    @else
                                        <span class="dim">No date</span>
                                    @endif
                                </td>
                                <td>{{ $update->favorited_at?->copy()->timezone($austinTz)->format('Y-m-d H:i') ?: 'Not captured' }}</td>
                                <td>
                                    <div class="inline-row">
                                        @if ($update->source_url)
                                            <a class="button secondary tiny" href="{{ route('sls.intelligence.updates.sourcePage', $update) }}">Open</a>
                                        @endif
                                        @if (! $update->reminder_completed_at)
                                            <form method="post" action="{{ route('sls.intelligence.updates.favorite.complete', $update) }}">
                                                @csrf
                                                <button class="tiny" type="submit">Done</button>
                                            </form>
                                        @endif
                                        <form method="post" action="{{ route('sls.intelligence.updates.favorite.remove', $update) }}">
                                            @csrf
                                            <button class="secondary tiny" type="submit">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">No favorites match this filter yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
