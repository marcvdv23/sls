@extends('sls.layouts.app')

@section('title', 'Users, Groups and Permissions')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Users, Groups and Permissions')

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
                <p class="eyebrow">Administration</p>
                <h1>Users, Groups and Permissions</h1>
                <p class="muted">Group-based form permissions plus country and product restrictions. No logged-in user means local development remains unlocked.</p>
            </div>
            <div class="toolbar">
                <a class="button secondary" href="{{ url('/sls') }}">Dashboard</a>
                <a class="button secondary" href="#users">Users</a>
                <a class="button secondary" href="#permissions">Permissions</a>
            </div>
        </header>

        <main class="stack">
            @if (session('status'))
                <div class="status">{{ session('status') }}</div>
            @endif

            <section class="summary-grid">
                <article class="panel stat"><strong>{{ $users->count() }}</strong><span class="muted">users</span></article>
                <article class="panel stat"><strong>{{ $groups->count() }}</strong><span class="muted">user groups</span></article>
                <article class="panel stat"><strong>{{ $forms->count() }}</strong><span class="muted">controlled forms</span></article>
                <article class="panel stat"><strong>{{ $recentAuditLogs->count() }}</strong><span class="muted">recent audit entries</span></article>
            </section>

            <section id="users" class="panel stack">
                <div class="toolbar">
                    <div>
                        <p class="eyebrow">Users</p>
                        <h2>User accounts</h2>
                    </div>
                </div>
                <form method="post" action="{{ route('sls.security.users.store') }}" class="form-grid">
                    @csrf
                    <label>Name<input name="name" required></label>
                    <label>Email<input name="email" type="email" required></label>
                    <label>Password<input name="password" type="password" placeholder="Optional for now"></label>
                    <label>Group
                        <select name="user_group_id">
                            <option value="">No group</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Theme
                        <select name="theme_preference">
                            <option value="white">White</option>
                            <option value="lively">Lively</option>
                            <option value="conservative">Conservative</option>
                        </select>
                    </label>
                    <label>Active
                        <select name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </label>
                    <div><button type="submit">Add user</button></div>
                </form>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>User</th><th>Email</th><th>Group</th><th>Theme</th><th>Status</th><th>New password</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse ($users as $user)
                                <tr>
                                    <form method="post" action="{{ route('sls.security.users.update', $user) }}">
                                        @csrf
                                        <td><input name="name" value="{{ $user->name }}" required></td>
                                        <td><input name="email" type="email" value="{{ $user->email }}" required></td>
                                        <td>
                                            <select name="user_group_id">
                                                <option value="">No group</option>
                                                @foreach ($groups as $group)
                                                    <option value="{{ $group->id }}" @selected($user->user_group_id === $group->id)>{{ $group->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select name="theme_preference">
                                                @foreach (['white' => 'White', 'lively' => 'Lively', 'conservative' => 'Conservative'] as $value => $label)
                                                    <option value="{{ $value }}" @selected($user->theme_preference === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select name="is_active">
                                                <option value="1" @selected($user->is_active)>Active</option>
                                                <option value="0" @selected(! $user->is_active)>Inactive</option>
                                            </select>
                                        </td>
                                        <td><input name="password" type="password" placeholder="Leave unchanged"></td>
                                        <td><button type="submit" class="secondary">Save</button></td>
                                    </form>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="muted">No users yet. Create your first admin user here before we turn on login enforcement.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel stack">
                <div>
                    <p class="eyebrow">Groups</p>
                    <h2>User groups</h2>
                </div>
                <form method="post" action="{{ route('sls.security.groups.store') }}" class="form-grid">
                    @csrf
                    <label>Group name<input name="name" required></label>
                    <label>Description<input name="description"></label>
                    <label>Admin
                        <select name="is_admin"><option value="0">No</option><option value="1">Yes</option></select>
                    </label>
                    <div><button type="submit">Add group</button></div>
                </form>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Group</th><th>Description</th><th>Admin</th><th>Users</th><th>Permissions</th><th>Country rules</th><th>Product rules</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach ($groups as $group)
                                <tr>
                                    <form method="post" action="{{ route('sls.security.groups.update', $group) }}">
                                        @csrf
                                        <td><input name="name" value="{{ $group->name }}" required><br><span class="muted">{{ $group->slug }}</span></td>
                                        <td><input name="description" value="{{ $group->description }}"></td>
                                        <td>
                                            <select name="is_admin">
                                                <option value="0" @selected(! $group->is_admin)>No</option>
                                                <option value="1" @selected($group->is_admin)>Yes</option>
                                            </select>
                                        </td>
                                        <td>{{ $group->users_count }}</td>
                                        <td>{{ $group->permissions_count }}</td>
                                        <td>{{ $group->country_access_count ?: 'Open' }}</td>
                                        <td>{{ $group->product_access_count ?: 'Open' }}</td>
                                        <td><button type="submit" class="secondary">Save</button></td>
                                    </form>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="permissions" class="panel stack">
                <div>
                    <p class="eyebrow">Form Rights</p>
                    <h2>Permission matrix</h2>
                    <p class="muted">Users inherit these rights from their group. Admin groups bypass the matrix.</p>
                </div>
                @foreach ($groups as $group)
                    <details class="panel" @if ($loop->first) open @endif>
                        <summary><strong>{{ $group->name }}</strong> <span class="muted">{{ $group->is_admin ? 'admin bypass enabled' : 'matrix controlled' }}</span></summary>
                        <form method="post" action="{{ route('sls.security.groups.permissions.update', $group) }}" class="stack" style="margin-top:.75rem;">
                            @csrf
                            <div class="table-wrap">
                                <table class="matrix">
                                    <thead>
                                        <tr>
                                            <th>Form</th>
                                            @foreach ($actions as $column => $label)
                                                <th>{{ $label }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($forms as $form)
                                            @php($permission = $group->permissions->firstWhere('form_key', $form->key))
                                            <tr>
                                                <td><strong>{{ $form->label }}</strong><br><code>{{ $form->key }}</code> <span class="muted">{{ $form->category }}</span></td>
                                                @foreach ($actions as $column => $label)
                                                    <td><input type="checkbox" name="permissions[{{ $form->key }}][{{ $column }}]" value="1" @checked((bool) ($permission?->{$column}))></td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div><button type="submit">Save permissions for {{ $group->name }}</button></div>
                        </form>
                    </details>
                @endforeach
            </section>

            <section class="split">
                <article class="panel stack">
                    <div>
                        <p class="eyebrow">Data Restrictions</p>
                        <h2>Country and region access</h2>
                    </div>
                    @foreach ($groups as $group)
                        <details class="panel">
                            <summary><strong>{{ $group->name }}</strong> <span class="muted">{{ $group->countryAccess->isEmpty() ? 'open to all countries' : $group->countryAccess->count() . ' rule(s)' }}</span></summary>
                            <form method="post" action="{{ route('sls.security.groups.countryAccess.store', $group) }}" class="form-grid" style="margin-top:.65rem;">
                                @csrf
                                <label>Country
                                    <select name="country_id">
                                        <option value="">Region only</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->id }}">{{ $country->name }} ({{ $country->iso_code }})</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Region
                                    <select name="region">
                                        <option value="">Country only</option>
                                        @foreach ($regions as $region)
                                            <option value="{{ $region }}">{{ $region }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Access
                                    <select name="can_access"><option value="1">Allow</option><option value="0">Block</option></select>
                                </label>
                                <div><button type="submit" class="secondary">Add rule</button></div>
                            </form>
                            <div class="compact-list">
                                @forelse ($group->countryAccess as $rule)
                                    <form method="post" action="{{ route('sls.security.countryAccess.remove', $rule) }}" class="small-form">
                                        @csrf
                                        <button class="danger" type="submit">{{ $rule->can_access ? 'Allow' : 'Block' }} {{ $rule->country?->name ?? $rule->region }} x</button>
                                    </form>
                                @empty
                                    <span class="muted">No rules means open access.</span>
                                @endforelse
                            </div>
                        </details>
                    @endforeach
                </article>

                <article class="panel stack">
                    <div>
                        <p class="eyebrow">Data Restrictions</p>
                        <h2>Product access</h2>
                    </div>
                    @foreach ($groups as $group)
                        <details class="panel">
                            <summary><strong>{{ $group->name }}</strong> <span class="muted">{{ $group->productAccess->isEmpty() ? 'open to all products' : $group->productAccess->count() . ' rule(s)' }}</span></summary>
                            <form method="post" action="{{ route('sls.security.groups.productAccess.store', $group) }}" class="form-grid" style="margin-top:.65rem;">
                                @csrf
                                <label>Product
                                    <select name="product_id">
                                        <option value="">Use product key</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Product key<input name="product_key" placeholder="SSAS, HRMS, ERMS, EBPC"></label>
                                <label>Access
                                    <select name="can_access"><option value="1">Allow</option><option value="0">Block</option></select>
                                </label>
                                <div><button type="submit" class="secondary">Add rule</button></div>
                            </form>
                            <div class="compact-list">
                                @forelse ($group->productAccess as $rule)
                                    <form method="post" action="{{ route('sls.security.productAccess.remove', $rule) }}" class="small-form">
                                        @csrf
                                        <button class="danger" type="submit">{{ $rule->can_access ? 'Allow' : 'Block' }} {{ $rule->product?->name ?? $rule->product_key }} x</button>
                                    </form>
                                @empty
                                    <span class="muted">No rules means open access.</span>
                                @endforelse
                            </div>
                        </details>
                    @endforeach
                </article>
            </section>

            <section class="panel">
                <p class="eyebrow">Audit</p>
                <h2>Recent access audit entries</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Form</th><th>Record</th><th>Details</th></tr></thead>
                        <tbody>
                            @forelse ($recentAuditLogs as $log)
                                <tr>
                                    <td>{{ $log->created_at?->timezone('America/Chicago')->format('Y-m-d H:i') }}</td>
                                    <td>{{ $log->user?->name ?? 'System / local' }}</td>
                                    <td>{{ $log->action }}</td>
                                    <td>{{ $log->form_key ?: 'Not set' }}</td>
                                    <td>{{ $log->model_type ? class_basename($log->model_type) . '#' . $log->model_id : 'None' }}</td>
                                    <td class="muted">{{ $log->metadata ? json_encode($log->metadata) : 'No metadata' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="muted">No audit entries yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
@endsection