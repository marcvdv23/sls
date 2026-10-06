<!doctype html>
<html lang="en" data-theme="{{ auth()->user()->theme_preference ?? 'white' }}">
    <head>
        @php
            $slsCurrentWorkspace = \App\Support\WorkspaceContext::current();
            $slsSelectableWorkspaces = \App\Support\WorkspaceContext::selectableWorkspaces();
            $slsPlatformName = \App\Support\SlsSettings::get('platform.name', config('sls.platform.name', 'SLS'));
            $slsEntityName = \App\Support\SlsSettings::get('entity.display_name', config('sls.entity.display_name', config('sls.entity.name', '2interact')));
            $slsWorkspaceName = \App\Support\SlsSettings::get('workspace.name', config('sls.workspace.name', 'Social Security Sales'));
            $slsReviewLabel = \App\Support\SlsSettings::get('workspace.review_label', config('sls.workspace.review_label', 'Review Desk'));
            $slsUser = auth()->user();
            $slsUserGroup = $slsUser?->group?->loadMissing('permissions');
            $slsSourcePermission = $slsUserGroup?->permissions?->firstWhere('form_key', 'source_maintenance');
            $slsSourceMaintenanceOnly = $slsUserGroup
                && ! $slsUserGroup->is_admin
                && $slsSourcePermission
                && ((bool) $slsSourcePermission->can_view || (bool) $slsSourcePermission->can_update)
                && ! $slsUserGroup->permissions
                    ->reject(fn ($permission) => $permission->form_key === 'source_maintenance')
                    ->contains(fn ($permission) => collect([
                        'can_view',
                        'can_search',
                        'can_insert',
                        'can_update',
                        'can_delete',
                        'can_approve',
                        'can_print',
                        'can_export',
                        'can_import',
                        'can_run_process',
                        'can_assign',
                        'can_configure',
                    ])->contains(fn ($column) => (bool) $permission->{$column}));
            $slsBasicReviewerForms = collect(['intelligence_review', 'intelligence_keywords', 'opportunities', 'organization_tasks']);
            $slsBasicReviewerOnly = ! $slsSourceMaintenanceOnly
                && $slsUserGroup
                && ! $slsUserGroup->is_admin
                && $slsUserGroup->permissions
                    ->whereIn('form_key', $slsBasicReviewerForms->all())
                    ->contains(fn ($permission) => (bool) $permission->can_view || (bool) $permission->can_update)
                && ! $slsUserGroup->permissions
                    ->reject(fn ($permission) => $slsBasicReviewerForms->contains($permission->form_key))
                    ->contains(fn ($permission) => collect([
                        'can_view',
                        'can_search',
                        'can_insert',
                        'can_update',
                        'can_delete',
                        'can_approve',
                        'can_print',
                        'can_export',
                        'can_import',
                        'can_run_process',
                        'can_assign',
                        'can_configure',
                    ])->contains(fn ($column) => (bool) $permission->{$column}));
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @hasSection('refresh')
            <meta http-equiv="refresh" content="@yield('refresh')">
        @endif
        <title>@yield('title', $slsPlatformName . ' - ' . $slsEntityName)</title>
        <link rel="icon" type="image/png" href="{{ asset('sls-favicon.png') }}?v={{ filemtime(public_path('sls-favicon.png')) }}">
        <link rel="stylesheet" href="{{ asset('sls-ui.css') }}?v={{ filemtime(public_path('sls-ui.css')) }}">
        @stack('head')
    </head>
    <body>
        <div class="app-shell">
            <input id="sidebar-toggle" class="sidebar-toggle" type="checkbox">
            <aside class="sls-sidebar">
                <div class="toolbar" style="justify-content:space-between;">
                    <a class="sls-brand" href="{{ url('/sls') }}" style="text-decoration:none;color:inherit;">
                        <img class="brand-logo-full" src="{{ asset('sls-logo.png') }}?v={{ filemtime(public_path('sls-logo.png')) }}" alt="SLS" width="170" height="48" style="width:170px;height:48px;max-width:170px;max-height:48px;object-fit:contain;object-position:left center;display:block;">
                        <img class="brand-logo-icon" src="{{ asset('sls-favicon.png') }}?v={{ filemtime(public_path('sls-favicon.png')) }}" alt="SLS" width="30" height="30" style="width:30px;height:30px;max-width:30px;max-height:30px;object-fit:cover;border-radius:999px;">
                    </a>
                    <label class="collapse-button" for="sidebar-toggle" title="Collapse menu">Menu</label>
                </div>
                <div style="margin:10px 12px 18px;color:#536173;font-size:12px;line-height:1.35;">
                    <strong style="display:block;color:#111827;font-size:13px;">{{ $slsEntityName }}</strong>
                    <span>{{ $slsWorkspaceName }}</span>
                    @if ($slsCurrentWorkspace)
                        <form method="post" action="{{ route('sls.workspaces.current') }}" style="display:grid;gap:5px;margin-top:10px;">
                            @csrf
                            <label for="workspace-selector" style="font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#8a96a8;">Workspace</label>
                            <select id="workspace-selector" name="workspace_id" onchange="this.form.submit()" style="font-size:12px;min-height:34px;padding:5px 7px;">
                                @foreach ($slsSelectableWorkspaces as $workspace)
                                    <option value="{{ $workspace->id }}" @selected($slsCurrentWorkspace->id === $workspace->id)>
                                        {{ $workspace->entity_name }} / {{ $workspace->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>

                @php
                    $navSections = $slsSourceMaintenanceOnly ? [
                        'Work' => [
                            ['Source Maintenance', route('sls.sourceMaintenance.index'), 'sls.sourceMaintenance.*', 'SRC'],
                        ],
                    ] : ($slsBasicReviewerOnly ? [
                        'Review' => [
                            ['Regulatory Radar', route('sls.intelligence.review', ['focus' => 'legislation', 'region' => 'global', 'retrieved' => 'current', 'country_q' => 'European Union']), 'sls.intelligence.review', 'REG'],
                            ['News & Tenders', route('sls.intelligence.review', ['focus' => 'all', 'region' => 'all', 'retrieved' => 'current']), 'sls.intelligence.review', 'REV'],
                            ['Keywords', route('sls.intelligence.keywords'), 'sls.intelligence.keywords*', '#'],
                            ['Opportunities', route('sls.opportunities.index'), 'sls.opportunities.*', 'OPP'],
                            ['To Do', route('sls.tasks.index'), 'sls.tasks.*', 'TODO'],
                        ],
                    ] : [
                        'Work' => [
                            ['Dashboard', url('/sls'), 'sls.dashboard', 'DB'],
                            ['Global CRM Search', route('sls.crm.search'), 'sls.crm.search', 'CRM'],
                            ['Organizations', route('sls.organizations.index'), 'sls.organizations.*', 'ORG'],
                            ['Priority Opportunities', route('sls.priorityOpportunities.index'), 'sls.priorityOpportunities.*', 'PRI'],
                            ['Opportunities', route('sls.opportunities.index'), 'sls.opportunities.*', 'OPP'],
                            ['To Do', route('sls.tasks.index'), 'sls.tasks.*', 'TODO'],
                            ['Favorites', route('sls.intelligence.favorites'), 'sls.intelligence.favorites', 'FAV'],
                        ],
                        'Intelligence' => [
                            [$slsReviewLabel, route('sls.intelligence.review'), 'sls.intelligence.review', 'REV'],
                            ['Add Story', route('sls.intelligence.stories.create'), 'sls.intelligence.stories.*', '+'],
                            ['Opportunity Intake', route('sls.intelligence.opportunityIntake.create'), 'sls.intelligence.opportunityIntake.*', 'IN'],
                            ['Awarded Companies', route('sls.intelligence.awardedCompanies.index'), 'sls.intelligence.awardedCompanies.*', 'AWD'],
                            ['News Archive', route('sls.intelligence.newsArchive'), 'sls.intelligence.newsArchive', 'ARC'],
                            ['Intake Log', route('sls.intelligence.intakeLog'), 'sls.intelligence.intakeLog', 'LOG'],
                            ['Journalists', route('sls.intelligence.journalists.index'), 'sls.intelligence.journalists.*', 'JRN'],
                            ['World Map', route('sls.intelligence.world'), 'sls.intelligence.world', 'MAP'],
                            ['Sources', route('sls.intelligence.sources'), 'sls.intelligence.sources*', 'SRC'],
                            ['Intelligence Crawlers', route('sls.intelligence.crawlers.index'), 'sls.intelligence.crawlers.*', 'CRW'],
                            ['SerpAPI Searches', route('sls.serpapiSearches.index'), 'sls.serpapiSearches.*', 'SERP'],
                            ['Keywords', route('sls.intelligence.keywords'), 'sls.intelligence.keywords*', '#'],
                            ['Source Contacts', route('sls.intelligence.contacts'), 'sls.intelligence.contacts', '@'],
                        ],
                        'Knowledge' => [
                            ['Chat', route('sls.chat'), 'sls.chat*', 'AI'],
                            ['Domain Docs', route('sls.socialSecuritySystems.import'), 'sls.socialSecuritySystems.*', 'DOC'],
                            ['Document Intake', route('sls.documents.intake'), 'sls.documents.*', 'DOC'],
                            ['Knowledge Base', route('sls.knowledge.index'), 'sls.knowledge.*', 'KB'],
                            ['Demo Media', route('sls.demo-media.index'), 'sls.demo-media.*', 'VID'],
                            ['Image Import', route('sls.directoryImages.index'), 'sls.directoryImages.*', 'IMG'],
                        ],
                        'Setup' => [
                            ['Workspace Settings', route('sls.settings.workspace'), 'sls.settings.workspace*', 'SET'],
                            ['Source Maintenance', route('sls.sourceMaintenance.index'), 'sls.sourceMaintenance.*', 'SRC'],
                            ['Products & Services', route('sls.settings.products'), 'sls.settings.products*', 'PRD'],
                            ['Review Categories', route('sls.settings.reviewFocuses'), 'sls.settings.reviewFocuses*', 'REV'],
                            ['Priority Opportunities', route('sls.settings.priorityOpportunities'), 'sls.settings.priorityOpportunities*', 'PRI'],
                            ['Crawler Settings', route('sls.intelligence.crawlerSettings'), 'sls.intelligence.crawlerSettings', 'CRW'],
                            ['Organization Crawlers', route('sls.organizations.crawlers.index'), 'sls.organizations.crawlers.*', 'ORG'],
                            ['Organization Crawler Results', route('sls.organizations.crawlerResults'), 'sls.organizations.crawlerResults', 'RES'],
                            ['Operations', route('sls.operations.index'), 'sls.operations.*', 'OPS'],
                            ['Security', route('sls.security.index'), 'sls.security.*', 'SEC'],
                            ['Backup', url('/sls#backup'), 'sls.system.backup', 'BAK'],
                            ['Email Accounts', route('sls.emailAccounts.index'), 'sls.emailAccounts.*', 'EML'],
                        ],
                    ]);
                @endphp

                @foreach ($navSections as $section => $links)
                    @php($sectionId = 'nav-section-' . \Illuminate\Support\Str::slug($section))
                    <nav class="nav-section" aria-label="{{ $section }}">
                        <button class="nav-section-title" type="button" data-nav-section-toggle aria-expanded="true" aria-controls="{{ $sectionId }}">{{ $section }}</button>
                        <div id="{{ $sectionId }}" class="nav-section-links">
                        @foreach ($links as $link)
                            @php
                                $label = $link[0];
                                $href = $link[1];
                                $routePattern = $link[2];
                                $icon = $link[3];
                                $isReviewRoute = request()->routeIs('sls.intelligence.review');
                                $isRegulatoryReview = $isReviewRoute && request()->query('focus') === 'legislation';
                                $navActive = request()->routeIs($routePattern);

                                if ($label === 'Regulatory Radar') {
                                    $navActive = $isRegulatoryReview;
                                } elseif ($label === 'News & Tenders') {
                                    $navActive = $isReviewRoute && ! $isRegulatoryReview;
                                }
                            @endphp
                            <a class="nav-link {{ $navActive ? 'active' : '' }}" href="{{ $href }}" title="{{ $label }}">
                                <span class="nav-icon">{{ $icon }}</span>
                                <span class="nav-text">{{ $label }}</span>
                            </a>
                        @endforeach
                        </div>
                    </nav>
                @endforeach
            </aside>

            <div class="sls-page">
                <header class="sls-topbar">
                    <div>
                        <p class="eyebrow">@yield('eyebrow', $slsPlatformName . ' · ' . $slsEntityName)</p>
                        <h1>@yield('page_title', 'Dashboard')</h1>
                    </div>
                    <div class="actions">
                        @yield('topbar_actions')
                        @auth
                            <span style="color:#536173;font-size:13px;font-weight:700;margin-left:12px;">{{ auth()->user()->name }}</span>
                            <form method="post" action="{{ route('sls.logout') }}" style="display:inline;">
                                @csrf
                                <button class="button" type="submit">Logout</button>
                            </form>
                        @endauth
                    </div>
                </header>

                <main class="sls-main page-enter">
                    @if (session('error'))
                        <section class="panel flash" style="margin-bottom:14px;color:#b91c1c;border-color:#fecaca;background:#fff7f7;">{{ session('error') }}</section>
                    @endif
                    @if (session('status'))
                        <section class="panel flash" style="margin-bottom:14px;">{{ session('status') }}</section>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>
        <script>
            document.querySelectorAll('[data-nav-section-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    const target = document.getElementById(button.getAttribute('aria-controls'));
                    const expanded = button.getAttribute('aria-expanded') === 'true';

                    button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    if (target) {
                        target.hidden = expanded;
                    }
                });
            });
        </script>
        @stack('scripts')
    </body>
</html>


