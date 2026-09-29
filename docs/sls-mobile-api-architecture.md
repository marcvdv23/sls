# SLS Mobile and Voice API Architecture

## Current SLS Architecture

SLS is a Laravel application used as a Sales CRM, sales intelligence monitor, crawler runner, review desk, task/follow-up tracker, knowledge base, and opportunity pipeline.

- Framework: Laravel `^13.8`
- PHP: `^8.3`
- Routes today: primarily `routes/web.php` and `routes/console.php`
- API routes today: no dedicated `routes/api.php` application surface yet
- Auth today: session-based Laravel authentication for `/sls` web UI
- Existing worker token: `SLS_WORKER_TOKEN` for selected worker routes, not suitable as the mobile/voice user API auth model
- Database: MySQL in production, Laravel migrations in `database/migrations`
- Queue/scheduler: Laravel queue/scheduler plus console commands for crawlers, sweeps, health checks, cleanup, SerpAPI, and backup

## Generic Platform Direction

SLS is the generic Sales platform. Entity/workspace differences should be configuration and data, not code forks.

Current default deployment:

- Entity: `2interact`
- Workspace: `Social Security Sales`
- Products: `SSAS`, `HRMS`, `ERMS`, `EBPC`

Future deployment example:

- Entity: `rckgrp`
- Workspace: `Sustainability Consulting Sales`
- Products/services: sustainability consulting, ESG, climate, environmental policy, green finance

## Relevant Database Areas

The mobile/voice API should reuse these existing domains rather than bypassing them:

- Users/access: `users`, user groups, permissions, country/product access, audit logs
- CRM accounts: `market_organizations`
- Contacts: `market_organization_contacts`
- CRM activity: `market_organization_activities`, `market_organization_communications`
- Tasks/follow-ups: `sls_tasks`, `market_organization_tasks`
- Intelligence/news/tenders: `country_updates`, `country_update_opportunities`, `country_update_organizations`
- Countries/products: `countries`, `products`
- Sources/crawlers: `intelligence_sources`, `intelligence_keywords`, `crawler_settings`, `market_crawlers`, `market_crawler_runs`
- SerpAPI: `serp_api_search_templates`, `sls_operation_runs`
- Priority opportunities: `priority_opportunities`, `market_organization_priority_opportunity`
- Knowledge/documents: `source_documents`, `knowledge_chunks`, demo media tables

## Existing Business Logic To Reuse

Important existing services/support classes:

- `CountryIntelligenceMonitor`
- `SerpApiSearchService`
- `CountryStoryOpportunityService`
- `CountryUpdateDedupeRules`
- `CountryUpdateNoiseRules`
- `CountryUpdateClassifier`
- `TenderDocumentProcessor`
- `OpportunityEmailDraftService`
- `DocumentContactExtractionService`
- `SourceContactExtractionService`
- `SocialSecurityAdminDocumentService`
- `UniversityMarketCrawlerService`

The API should call services or extract route logic into services where needed. It should not duplicate crawler, review, tagging, dedupe, opportunity, or contact extraction logic.

## Security Constraints

The iPhone app and AI layer must never connect directly to MySQL.

Recommended API security model:

- Add Laravel Sanctum or a first-party token model for mobile API tokens.
- Use HTTPS only.
- Version the API under `/api/v1`.
- Require authenticated user context for all CRM/intelligence operations.
- Apply authorization policies per entity/workspace, country, product, and record type.
- Add rate limits by user/token/client.
- Validate every request with Form Requests or explicit validators.
- Never expose arbitrary SQL or generic table mutation endpoints.
- Log API writes to an audit table with actor, client, action, record, old/new values when practical.
- Separate read endpoints from write endpoints.
- Require confirmation tokens for destructive or consequential operations.

## Proposed API Shape

Base path: `/api/v1`

Authentication:

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/auth/token` | Exchange user credentials or approved device auth for a mobile token. |
| POST | `/auth/revoke` | Revoke current token/device. |
| GET | `/me` | Current user, permissions, entity/workspace, default filters. |

### Accounts / Organizations

| Method | Path | Purpose | Read/Write | Voice use |
| --- | --- | --- | --- | --- |
| GET | `/accounts` | Search/list accounts with filters for country, region, sector, status, product, stale activity. | Read | “What accounts do we have in Saudi Arabia?” |
| GET | `/accounts/{id}` | Account detail with contacts, opportunities, tasks, last activity, news. | Read | “What is happening with Barbados?” |
| POST | `/accounts` | Create controlled account. | Write | “Create an account for X.” |
| PATCH | `/accounts/{id}` | Update allowed account fields. | Write | “Change this account status to active pursuit.” |
| GET | `/accounts/{id}/activity` | Account activity timeline. | Read | “When did we last speak to them?” |
| POST | `/accounts/{id}/notes` | Add note/activity. | Write | “Add a note that I spoke to John today.” |

### Contacts

| Method | Path | Purpose | Read/Write | Voice use |
| --- | --- | --- | --- | --- |
| GET | `/contacts` | Search/list contacts by name, organization, country, role. | Read | “Who is our contact at this organization?” |
| GET | `/contacts/{id}` | Contact detail and interaction history. | Read | “Read me the contact details.” |
| POST | `/contacts` | Create contact. | Write | “Add John as procurement contact.” |
| PATCH | `/contacts/{id}` | Update controlled contact fields. | Write | “Update John’s title.” |

### Opportunities

SLS currently has multiple opportunity-like records: `country_update_opportunities`, `priority_opportunities`, tender/product mappings, and pipeline/review items. The API should present a unified opportunity resource while preserving source-specific IDs in metadata.

| Method | Path | Purpose | Read/Write | Voice use |
| --- | --- | --- | --- | --- |
| GET | `/opportunities` | Search/list/filter opportunities by country, account, product, stage, value, date, staleness. | Read | “Show me opportunities above $500,000.” |
| GET | `/opportunities/{type}/{id}` | Detail for priority/review/pipeline opportunity. | Read | “Tell me more about number three.” |
| PATCH | `/opportunities/{type}/{id}` | Update controlled stage/status/value/date/priority fields where supported. | Write | “Move this to proposal stage.” |
| GET | `/opportunities/summary` | Pipeline summary by country, product, stage, owner, value/date. | Read | “What deals close this quarter?” |
| GET | `/opportunities/stale` | Opportunities with no recent activity. | Read | “Which opportunities haven’t been updated in 30 days?” |

### Tasks / Follow-ups

| Method | Path | Purpose | Read/Write | Voice use |
| --- | --- | --- | --- | --- |
| GET | `/tasks` | List tasks/follow-ups by status, due date, owner, account, opportunity. | Read | “What do I need to follow up on?” |
| POST | `/tasks` | Create follow-up task. | Write | “Remind me next Tuesday.” |
| PATCH | `/tasks/{id}` | Update task status/due date/notes. | Write | “Mark this follow-up done.” |

### Activities / Notes

| Method | Path | Purpose | Read/Write | Voice use |
| --- | --- | --- | --- | --- |
| GET | `/activity` | Recent activity across CRM with filters. | Read | “Read me the latest notes.” |
| POST | `/activity` | Log call/meeting/email/note with related account/contact/opportunity. | Write | “Log that I spoke to John today.” |

### News / Intelligence

| Method | Path | Purpose | Read/Write | Voice use |
| --- | --- | --- | --- | --- |
| GET | `/intelligence/items` | News/tenders/RFP/review desk items by status, country, product, date, source, read state. | Read | “Read me the new news stories.” |
| GET | `/intelligence/items/{id}` | Story/detail/source metadata and related CRM links. | Read | “Show me more information about this one.” |
| PATCH | `/intelligence/items/{id}/review` | Mark relevant/not relevant, read/unread, category/product/country mapping. | Write | “Mark that as relevant.” |
| POST | `/intelligence/items/{id}/organizations` | Link story to account. | Write | “Tag this to the Barbados account.” |

### Context for Voice + Visual UI

The API should return stable record references:

```json
{
  "items": [
    {
      "ref": {"type": "opportunity", "subtype": "priority", "id": 184},
      "display_title": "Saudi Arabia | Pension modernization",
      "summary": "...",
      "links": {"self": "/api/v1/opportunities/priority/184"}
    }
  ],
  "context": {
    "result_set_id": "uuid",
    "numbered_refs": [
      {"number": 1, "type": "opportunity", "subtype": "priority", "id": 184}
    ]
  }
}
```

The iPhone app should maintain current context:

- current screen
- active record reference
- most recent result set
- numbered list mapping

The LLM should use that context to resolve “this one,” “number three,” or “the Barbados deal.”

## AI Tool Definitions

Initial tool layer should map directly to controlled API operations:

- `search_accounts(query, country, status, limit)`
- `get_account(account_id)`
- `search_contacts(query, account_id, country, limit)`
- `get_contact(contact_id)`
- `search_opportunities(query, country, account_id, product, stage, min_value, close_date_range, stale_days, limit)`
- `get_opportunity(type, id)`
- `get_pipeline_summary(group_by, filters)`
- `get_recent_activity(account_id, opportunity_ref, days, limit)`
- `get_followups(status, due_before, account_id, opportunity_ref)`
- `create_followup(title, due_at, account_id, opportunity_ref, notes, confirmation_token)`
- `add_note(body, account_id, contact_id, opportunity_ref, confirmation_token)`
- `log_interaction(type, body, occurred_at, account_id, contact_id, opportunity_ref, confirmation_token)`
- `get_news(country, product, status, unread_only, limit)`
- `get_news_item(id)`
- `review_news_item(id, relevance, read_state, product_ids, account_ids, confirmation_token)`

The LLM must call these tools. It must not generate SQL.

## Phased Implementation Plan

### Phase 1: Platform genericization

- Add platform/entity/workspace config.
- Replace high-visibility hardcoded labels with config.
- Keep 2interact defaults unchanged.
- Prepare rckgrp deployment values.

### Phase 2: API foundation

- Add API route file/versioning.
- Add API auth/token model.
- Add request/response envelope conventions.
- Add authorization and audit primitives.
- Add read-only health/me endpoints.

### Phase 3: Small mobile/voice vertical slice

Demonstrate:

Voice/query -> API -> SLS -> structured result -> iPhone display -> follow-up query about selected record.

Minimum endpoints:

- `GET /api/v1/me`
- `GET /api/v1/opportunities`
- `GET /api/v1/opportunities/{type}/{id}`
- `GET /api/v1/accounts/{id}/activity`
- `POST /api/v1/activity`
- `GET /api/v1/tasks`
- `POST /api/v1/tasks`

### Phase 4: News/review desk API

- List new stories.
- Read story details.
- Mark read/unread.
- Mark relevant/not relevant.
- Link story to country/account/product.

### Phase 5: Broader CRM API

- Contacts.
- Account create/update.
- Opportunity updates.
- Pipeline summaries.
- Staleness reports.
- Document/source links.

### Phase 6: iPhone app prototype

- Visual lists/detail screens.
- Shared current item context.
- Voice command layer calling API tools.
- Confirmation flow for writes.

## Open Decisions

- API auth choice: Laravel Sanctum vs custom personal access tokens.
- Whether deployments remain separate databases or evolve toward true multi-tenant tables.
- Unified opportunity abstraction details across `priority_opportunities`, `country_update_opportunities`, and other pipeline records.
- Which writes require explicit confirmation in the mobile UI.
- Audit table shape for API/voice actions.
