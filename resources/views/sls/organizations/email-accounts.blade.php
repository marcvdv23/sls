@extends('sls.layouts.app')

@section('title', 'Email Accounts - 1G-SLS')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Email Accounts')

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
                <p class="eyebrow">Communications</p>
                <h1>Email Accounts</h1>
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
                <p class="eyebrow">Mailbox Connector Placeholder</p>
                <h2>Add email boxes to sync later</h2>
                <p class="muted" style="margin-top:.35rem;">This stores mailbox configuration targets. The actual Gmail/Microsoft/IMAP connector still needs OAuth or account settings before it can import sent and received emails.</p>
                <form class="grid" method="post" action="{{ route('sls.emailAccounts.store') }}">
                    @csrf
                    <label>Account name <input name="account_name" required placeholder="Marc Gmail, Sales inbox"></label>
                    <label>Email address <input name="email_address" type="email" required></label>
                    <label>Provider
                        <select name="provider">
                            <option value="">Unknown</option>
                            <option value="gmail">Gmail / Google Workspace</option>
                            <option value="microsoft">Microsoft 365 / Outlook</option>
                            <option value="imap">IMAP</option>
                        </select>
                    </label>
                    <button class="button" type="submit">Add mailbox</button>
                </form>
            </section>
            <section class="panel">
                <p class="eyebrow">Configured Accounts</p>
                <h2>Email sync status</h2>
                <table>
                    <thead><tr><th>Account</th><th>Email</th><th>Provider</th><th>Status</th><th>Last synced</th><th>Last issue</th></tr></thead>
                    <tbody>
                        @forelse ($accounts as $account)
                            <tr>
                                <td><strong>{{ $account->account_name }}</strong></td>
                                <td>{{ $account->email_address }}</td>
                                <td>{{ $account->provider ?: 'Unknown' }}</td>
                                <td>{{ $account->sync_status }}</td>
                                <td>{{ $account->last_synced_at?->copy()->timezone('America/Chicago')->format('Y-m-d H:i') ?? 'Not synced yet' }}</td>
                                <td>{{ $account->last_error ?: 'None' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">No email boxes configured yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </main>
    </div>
@endsection