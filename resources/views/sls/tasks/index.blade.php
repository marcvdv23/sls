@extends('sls.layouts.app')

@section('title', 'To Do - 1G-SLS')
@section('eyebrow', 'Work Queue')
@section('page_title', 'To Do')

@section('topbar_actions')
    <a class="button secondary" href="{{ route('sls.crm.search') }}">Global CRM search</a>
    <a class="button secondary" href="{{ route('sls.organizations.index') }}">Organizations</a>
@endsection

@push('head')
    <style>
        .task-form-grid { display:grid; grid-template-columns:2fr 150px 150px 140px 140px; gap:8px; align-items:end; }
        .task-form-grid .wide { grid-column:1 / span 2; }
        .task-filter-grid { display:grid; grid-template-columns:2fr 150px 160px 110px 110px; gap:8px; align-items:end; }
        .task-table { min-width:1180px; }
        .task-table th,
        .task-table td { padding:8px 10px; vertical-align:top; }
        .task-title { min-width:300px; }
        .task-notes { color:var(--text-secondary); font-size:12px; margin-top:3px; max-width:520px; }
        .task-actions { display:flex; gap:6px; flex-wrap:wrap; }
        .task-actions .button { padding:6px 10px; font-size:12px; }
        @media (max-width:1100px) {
            .task-form-grid,
            .task-filter-grid { grid-template-columns:1fr 1fr; }
            .task-form-grid .wide { grid-column:auto; }
        }
    </style>
@endpush

@section('content')
    <div class="stack">
        <section class="panel stack">
            <div>
                <p class="eyebrow">Create Task</p>
                <h2>Reminder or implementation item</h2>
            </div>
            <form method="post" action="{{ route('sls.tasks.store') }}" class="task-form-grid">
                @csrf
                <label class="wide">Task title
                    <input name="title" placeholder="What needs to be done?" required>
                </label>
                <label>Type
                    <select name="task_type">
                        @foreach ($taskTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Priority
                    <select name="priority">
                        @foreach ($priorities as $key => $label)
                            <option value="{{ $key }}" @selected($key === 'normal')>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Product
                    <select name="product_focus">
                        <option value="">Any</option>
                        <option value="SSAS">SSAS</option>
                        <option value="HRMS">HRMS</option>
                        <option value="ERMS">ERMS</option>
                        <option value="EBPC">EBPC</option>
                    </select>
                </label>
                <label>Country ISO
                    <input name="country_iso" placeholder="Optional">
                </label>
                <label>Due date/time
                    <input name="due_at" type="datetime-local">
                </label>
                <label class="wide">Related URL
                    <input name="related_url" placeholder="Optional URL">
                </label>
                <label class="wide">Notes
                    <textarea name="notes" rows="2" placeholder="Details, next step, or acceptance criteria"></textarea>
                </label>
                <button class="button" type="submit">Add task</button>
            </form>
        </section>

        <section class="panel stack">
            <div>
                <p class="eyebrow">Task List</p>
                <h2>Reminders and system implementation items</h2>
            </div>
            <form method="get" action="{{ route('sls.tasks.index') }}" class="task-filter-grid">
                <label>Search
                    <input name="q" value="{{ $query }}" placeholder="Search title, notes, product, country">
                </label>
                <label>Status
                    <select name="status">
                        <option value="all" @selected($status === 'all')>All</option>
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Type
                    <select name="type">
                        <option value="all" @selected($type === 'all')>All</option>
                        @foreach ($taskTypes as $key => $label)
                            <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button" type="submit">Apply</button>
                <a class="button secondary" href="{{ route('sls.tasks.index') }}">Clear</a>
            </form>

            <div class="table-wrap">
                <table class="task-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Product</th>
                            <th>Country</th>
                            <th>Due</th>
                            <th>Related</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tasks as $task)
                            <tr>
                                <td class="task-title">
                                    <strong>{{ $task->title }}</strong>
                                    @if ($task->notes)
                                        <div class="task-notes">{{ $task->notes }}</div>
                                    @endif
                                </td>
                                <td>{{ $taskTypes[$task->task_type] ?? str_replace('_', ' ', $task->task_type) }}</td>
                                <td><span class="pill {{ $task->status === 'completed' ? 'good' : ($task->status === 'waiting' ? 'warn' : 'normal') }}">{{ str_replace('_', ' ', $task->status) }}</span></td>
                                <td>{{ ucfirst($task->priority) }}</td>
                                <td>{{ $task->product_focus ?: '-' }}</td>
                                <td>{{ $task->country_iso ?: '-' }}</td>
                                <td class="nowrap">{{ $task->due_at ? $task->due_at->copy()->timezone('America/Chicago')->format('Y-m-d H:i') : '-' }}</td>
                                <td>
                                    @if ($task->related_url)
                                        <a class="button secondary" href="{{ $task->related_url }}" target="_blank" rel="noopener">Open</a>
                                    @elseif ($task->organization)
                                        <a class="button secondary" href="{{ route('sls.organizations.show', $task->organization) }}">Org</a>
                                    @elseif ($task->countryUpdate)
                                        <a class="button secondary" href="{{ route('sls.intelligence.sourcePage', $task->countryUpdate) }}">Intel</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <div class="task-actions">
                                        @if ($task->status !== 'completed')
                                            <form method="post" action="{{ route('sls.tasks.status', $task) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="completed">
                                                <button class="button secondary" type="submit">Complete</button>
                                            </form>
                                        @else
                                            <form method="post" action="{{ route('sls.tasks.status', $task) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="open">
                                                <button class="button secondary" type="submit">Reopen</button>
                                            </form>
                                        @endif
                                        @if ($task->status !== 'in_progress')
                                            <form method="post" action="{{ route('sls.tasks.status', $task) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="in_progress">
                                                <button class="button secondary" type="submit">Start</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="muted">No tasks match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $tasks->links() }}
        </section>
    </div>
@endsection
