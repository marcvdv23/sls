<!doctype html>
<html lang="en" data-theme="{{ auth()->user()->theme_preference ?? 'white' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @hasSection('refresh')
            <meta http-equiv="refresh" content="@yield('refresh')">
        @endif
        <title>@yield('title', '1G-SLS')</title>
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

                @php
                    $navSections = [
                        'Work' => [
                            ['Dashboard', url('/sls'), 'sls.dashboard', 'DB'],
                            ['Global CRM Search', route('sls.crm.search'), 'sls.crm.search', 'CRM'],
                            ['Organizations', route('sls.organizations.index'), 'sls.organizations.*', 'ORG'],
                            ['Opportunities', route('sls.opportunities.index'), 'sls.opportunities.*', 'OPP'],
                            ['To Do', route('sls.tasks.index'), 'sls.tasks.*', 'TODO'],
                            ['Favorites', route('sls.intelligence.favorites'), 'sls.intelligence.favorites', 'FAV'],
                        ],
                        'Intelligence' => [
                            ['Review Desk', route('sls.intelligence.review'), 'sls.intelligence.review', 'REV'],
                            ['Add Story', route('sls.intelligence.stories.create'), 'sls.intelligence.stories.*', '+'],
                            ['Opportunity Intake', route('sls.intelligence.opportunityIntake.create'), 'sls.intelligence.opportunityIntake.*', 'IN'],
                            ['Awarded Companies', route('sls.intelligence.awardedCompanies.index'), 'sls.intelligence.awardedCompanies.*', 'AWD'],
                            ['News Archive', route('sls.intelligence.newsArchive'), 'sls.intelligence.newsArchive', 'ARC'],
                            ['Intake Log', route('sls.intelligence.intakeLog'), 'sls.intelligence.intakeLog', 'LOG'],
                            ['Journalists', route('sls.intelligence.journalists.index'), 'sls.intelligence.journalists.*', 'JRN'],
                            ['World Map', route('sls.intelligence.world'), 'sls.intelligence.world', 'MAP'],
                            ['Sources', route('sls.intelligence.sources'), 'sls.intelligence.sources*', 'SRC'],
                            ['SerpAPI Searches', route('sls.serpapiSearches.index'), 'sls.serpapiSearches.*', 'SERP'],
                            ['Crawlers & SerpAPI', route('sls.organizations.crawlers.show', 1), 'sls.organizations.crawlers.*', 'CRW'],
                            ['Crawler Results', route('sls.organizations.crawlerResults'), 'sls.organizations.crawlerResults', 'RES'],
                            ['Keywords', route('sls.intelligence.keywords'), 'sls.intelligence.keywords*', '#'],
                            ['Source Contacts', route('sls.intelligence.contacts'), 'sls.intelligence.contacts', '@'],
                        ],
                        'Knowledge' => [
                            ['Chat', route('sls.chat'), 'sls.chat*', 'AI'],
                            ['Social Security Docs', route('sls.socialSecuritySystems.import'), 'sls.socialSecuritySystems.*', 'SSD'],
                            ['Document Intake', route('sls.documents.intake'), 'sls.documents.*', 'DOC'],
                            ['Knowledge Base', route('sls.knowledge.index'), 'sls.knowledge.*', 'KB'],
                            ['Demo Media', route('sls.demo-media.index'), 'sls.demo-media.*', 'VID'],
                            ['Image Import', route('sls.directoryImages.index'), 'sls.directoryImages.*', 'IMG'],
                        ],
                        'Setup' => [
                            ['Operations', route('sls.operations.index'), 'sls.operations.*', 'OPS'],
                            ['Security', route('sls.security.index'), 'sls.security.*', 'SEC'],
                            ['Backup', url('/sls#backup'), 'sls.system.backup', 'BAK'],
                            ['Email Accounts', route('sls.emailAccounts.index'), 'sls.emailAccounts.*', 'EML'],
                        ],
                    ];
                @endphp

                @foreach ($navSections as $section => $links)
                    @php($sectionId = 'nav-section-' . \Illuminate\Support\Str::slug($section))
                    <nav class="nav-section" aria-label="{{ $section }}">
                        <button class="nav-section-title" type="button" data-nav-section-toggle aria-expanded="true" aria-controls="{{ $sectionId }}">{{ $section }}</button>
                        <div id="{{ $sectionId }}" class="nav-section-links">
                        @foreach ($links as [$label, $href, $routePattern, $icon])
                            <a class="nav-link {{ request()->routeIs($routePattern) ? 'active' : '' }}" href="{{ $href }}" title="{{ $label }}">
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
                        <p class="eyebrow">@yield('eyebrow', '1G-SLS')</p>
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


