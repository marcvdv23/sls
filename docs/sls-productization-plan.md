# SLS Productization Plan

Last updated: 2026-10-03

## Goal

Turn SLS into a configurable sales platform that can be used by different organizations, products, markets, countries, crawlers, keyword sets, donor searches, SerpAPI campaigns, and review workflows without forking the codebase.

The current live setup remains the 2interact social security software sales workspace. The next expected workspace is rckgrp for sustainability consulting, environmental policy, ESG, climate, donor-funded projects, and related media intelligence.

## Core Principles

- Keep one shared GitHub repository and one shared feature set.
- Treat entity, workspace, product, crawler, keyword, donor, and source differences as configuration and data.
- Keep the current 2interact setup working while generalizing the app.
- Build in phases so every deployment stays usable.
- Put user-facing configuration in the UI instead of `.env` wherever non-technical users will need to change it.
- Do not expose direct MySQL access to external apps, mobile apps, or AI agents.
- Use a secure API layer for future mobile, voice, and external automation clients.
- Scope crawler/search/review data carefully before supporting multiple entities in one database.

## Terms

- Platform: the shared SLS application and codebase.
- Entity or tenant: the organization using SLS, such as 2interact or rckgrp.
- Workspace: a configured sales/intelligence domain inside an entity, such as social security software or sustainability consulting.
- Product or service: an offering being sold or tracked, such as SSAS, HRMS, ESG consulting, climate finance advisory, or environmental policy support.
- Source: a website, feed, search provider, donor portal, procurement portal, news source, or other input channel.
- Crawler profile: a configured recurring monitor for one or more sources.
- SerpAPI campaign: a configurable search run with country, region, language, keyword, and query-template settings.
- Review Desk: the human review queue for captured intelligence, tenders, opportunities, and news.

## Current State

- SLS is a Laravel application deployed at `https://sls.waffleshark.com`.
- The codebase is in GitHub at `marcvdv23/sls`.
- The production server runs from `/var/www/1g-sls`.
- Identity and product label configuration has started in `config/sls.php`.
- `.env.example` includes SLS platform/entity/workspace defaults.
- The visible shell and several screens now use configurable app/entity labels.
- The mobile and voice API direction is documented in `docs/sls-mobile-api-architecture.md`.
- SerpAPI timeout errors are now sanitized so API keys are not shown in stored/displayed error output.

## Phase 0: Foundation Started

- [x] Add generic SLS identity/workspace/product config in `config/sls.php`.
- [x] Add `.env.example` settings for platform/entity/workspace defaults.
- [x] Update visible shell, login, and priority opportunity labels to use configuration.
- [x] Draft the mobile and voice API architecture.
- [x] Sanitize SerpAPI errors and remove API keys from displayable error details.
- [x] Add this productization plan as a tracked document.
- [x] Add database-backed workspace settings foundation and Setup UI.

## Phase 1: Configurable Single-Entity Workspace

Objective: make the current deployment generic and configurable while still running as one primary workspace.

- [x] Add a Workspace Settings UI for entity name, workspace name, domain focus, review label, and default product labels.
- [x] Add Product/Service configuration UI for product names, short codes, categories, sort order, default marker, and active/inactive state.
- [x] Replace hardcoded dashboard product cards with configured products/services.
- [x] Make Review Desk focus/category labels, terms, and strong signals configurable.
- [x] Make priority opportunity fields and status categories configurable.
- [x] Move default SerpAPI keywords into editable system configuration.
- [x] Make SerpAPI query templates, result filters, and reruns configurable from the UI.
- [ ] Add or improve crawler/search profile admin screens so sources, schedules, keywords, and targets are clear.
- [ ] Preserve the current 2interact social security setup as seeded/default configuration.

## Phase 2: Workspace-Scoped Data

Objective: support multiple workspaces for one entity without mixing data.

- [ ] Add a `workspaces` table.
- [ ] Add `workspace_id` to configurable products/services.
- [ ] Add `workspace_id` to crawler settings, SerpAPI templates, source definitions, keywords, and operation runs.
- [ ] Add `workspace_id` to priority opportunities and any future imported shortlist records.
- [ ] Decide whether Review Desk items and `country_updates` should be workspace-scoped directly or through source/campaign ownership.
- [ ] Add an active workspace selector.
- [ ] Scope queries by active workspace.
- [ ] Backfill existing production data to the default 2interact social security workspace.

## Phase 3: Entity/Tenant Model

Objective: support multiple organizations safely from the same codebase.

- [ ] Add a `tenants` or `entities` table.
- [ ] Add tenant membership and roles.
- [ ] Add `tenant_id` to workspaces and tenant-owned business tables.
- [ ] Make authentication tenant-aware.
- [ ] Add tenant-aware authorization policies.
- [ ] Add tenant admin UI for settings, users, products, sources, crawlers, SerpAPI configuration, and imports.
- [ ] Ensure imports, crawlers, operation runs, and API tokens cannot cross tenant boundaries.

## Phase 4: API, Mobile, and Voice

Objective: add a secure API that can support mobile, voice, and external AI workflows.

- [ ] Add `/api/v1` endpoints.
- [ ] Use token auth, likely Laravel Sanctum unless a better local fit appears.
- [ ] Add API token management for users and service accounts.
- [ ] Add audit logging for API writes.
- [ ] Start with a vertical slice: current user, list opportunities, view opportunity, create activity, create/update task.
- [ ] Add Review Desk/news endpoints after the first CRM vertical slice works.
- [ ] Add voice tool definitions that map to API operations instead of direct database access.

## RCKGRP Sustainability Workspace Starter

Expected entity: rckgrp

Expected workspace: sustainability consulting sales.

Initial product/service areas:

- ESG reporting and advisory
- Sustainability strategy consulting
- Environmental policy advisory
- Climate adaptation and resilience
- Climate finance and green finance
- Carbon markets and emissions reporting
- Renewable energy transition
- Circular economy, waste, and water advisory
- Donor-funded environmental project support

Initial source/search areas:

- Environment ministries and agencies
- Procurement portals
- World Bank, AfDB, ADB, IDB, EU, UN, GIZ, FCDO, USAID, MCC, and climate fund opportunities
- Sustainability and ESG news
- Environmental policy announcements
- Climate finance and donor project pipelines

Initial review categories:

- Consulting opportunity
- Policy initiative
- Donor-funded project
- Tender/RFP/RFI
- Partnership lead
- Background intelligence

## Next Immediate Coding Pass

1. Add or improve crawler/search profile admin screens so sources, schedules, keywords, and targets are clear.
2. Start workspace scoping only after the single-workspace configuration screens are stable.
3. Add workspace ownership to SerpAPI templates and operation runs.
4. Add workspace ownership to crawler settings and source definitions.
5. Add a workspace selector after the first workspace-scoped tables are in place.

## Open Decisions

- Whether the first rckgrp deployment should use a separate database and `.env` before full tenant scoping is ready.
- Whether the tenant table should be named `tenants`, `entities`, or `organizations`.
- Which API authentication approach to use for mobile and AI clients.
- Whether country intelligence, priority opportunities, SerpAPI results, and CRM opportunities should converge into one shared opportunity model or remain related but separate records.
- Which operations should require explicit user confirmation in mobile/voice workflows.

## Deployment Notes

- Documentation-only changes do not require migrations.
- Feature changes continue to deploy with:

```bash
cd /var/www/1g-sls
git pull origin main
php artisan migrate --force
php artisan optimize:clear
```

- Run `php artisan migrate --force` only when migrations were added or changed.
