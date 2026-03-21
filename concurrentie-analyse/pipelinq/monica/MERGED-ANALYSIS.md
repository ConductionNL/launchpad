# Monica CRM -- Merged Competitive Analysis for Pipelinq

**Analysis date:** 2026-03-14
**Competitor:** Monica (monicahq.com)
**Repository:** https://github.com/monicahq/monica
**License:** AGPL-3.0

---

## 1. Sources Summary

| Source File | Type | Description |
|-------------|------|-------------|
| `overview.md` | Architecture overview | Tech stack, domain-driven design, data model, ER diagrams, strengths/weaknesses |
| `monica.md` | Product brief | Business model, target market, pricing, feature comparison table |
| `business-logic/browser-walkthrough-notes.md` | UI walkthrough | Screenshots and observations from monicahq.com public pages and source code |
| `docs/pricing-and-licensing.md` | Pricing analysis | License terms, pricing tiers, revenue model |
| `docs/product-features.md` | Feature inventory | Comprehensive listing of 19 feature categories with sub-features |
| `docs/technical-architecture.md` | Technical deep-dive | Stack details, 56 domain sub-domains, 69 models, 254 Vue components |
| `specs/contact-management/spec.md` | Core spec | Contact entity model, UI patterns, template-driven contact pages |
| `specs/relationships/spec.md` | Core spec | Bidirectional relationship system with typed groups and reverse naming |
| `specs/notes-documentation/spec.md` | Feature spec | Per-contact notes with emotions, file/photo management via Uploadcare |
| `specs/reminders-notifications/spec.md` | Feature spec | Recurring reminders, email + Telegram delivery channels |
| `specs/tasks-goals/spec.md` | Feature spec | Contact tasks with CalDAV sync, streak-based goals |
| `specs/groups-labels/spec.md` | Feature spec | Typed groups with roles, colored labels for contact categorization |
| `specs/journal-activity-tracking/spec.md` | Feature spec | Mixed manual/automatic journal timeline, mood tracking, life metrics |
| `specs/journal-timeline/spec.md` | Feature spec | Journal posts with sections, slices of life, timeline events |
| `specs/life-events/spec.md` | Feature spec | Rich life event model with costs, duration, distance, participants |
| `specs/mood-tracking/spec.md` | Feature spec | Configurable mood parameters, per-contact mood events with sleep tracking |
| `specs/search-and-discovery/spec.md` | Feature spec | Laravel Scout with Meilisearch/Typesense, vault-scoped contact search |
| `specs/template-module-system/spec.md` | Feature spec | Configurable contact page layouts with 22+ reusable modules |
| `specs/vault-system/spec.md` | Feature spec | Multi-vault isolation, three-tier permissions, template system |
| `specs/api-and-integrations/spec.md` | Feature spec | Minimal REST API (users + vaults only), CalDAV/CardDAV, no webhooks |

---

## 2. Product Overview

Monica is an open-source **Personal Relationship Management** (PRM) tool. Its core value proposition is: "Remember everything about your friends, family, and business relationships." It helps individuals track interactions, remember important details, and maintain personal relationships.

**Target market:** Individuals and small teams who want to track personal and professional relationships. Not a traditional business CRM -- it targets people who want to remember details about friends, family, and contacts (birthdays, conversation topics, family members).

**Business model:** Open-source (AGPL-3.0) with hosted SaaS subscriptions. Self-hosting is free with full features. Revenue comes from hosted plans ($9/month or $90/year) and Patreon donations. No enterprise licensing, no per-seat pricing, no team plans.

**Scale indicators (from website):** 44,123 users, 340,273 contacts, 28,321 activities, 89,918 reminders.

---

## 3. Architecture Summary

### Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12 (PHP 8.3+) |
| Frontend | Vue 3 + Inertia.js (SPA-like, no separate API) |
| CSS | Tailwind CSS 4, Ant Design Vue |
| Build | Vite |
| Database | MariaDB 10 / MySQL / PostgreSQL |
| Cache | Redis / Memcached |
| Search | Meilisearch or Typesense (via Laravel Scout) |
| Auth | Laravel Fortify + Sanctum + WebAuthn + OAuth (Socialite) |
| DAV | CardDAV + CalDAV (Sabre/DAV) |
| File storage | Uploadcare (cloud CDN) |
| Notifications | Email + Telegram |
| i18n | 27 languages (laravel-vue-i18n) |
| Docker | Laravel Sail (dev), Docker Hub image (prod) |

### Domain-Driven Architecture

Monica uses a domain-oriented structure under `app/Domains/` with three top-level domains:

- **Contact Domain** (22 sub-domains): ManageContact, ManageNotes, ManageCalls, ManageRelationships, ManageReminders, ManageTasks, ManageGoals, ManageLifeEvents, ManageLabels, ManageGroups, ManagePets, ManageLoans, ManageDocuments, ManagePhotos, ManageMoodTracking, ManageQuickFacts, ManageAvatar, ManageReligion, ManagePronouns, ManageJobInformation, ManageContactAddresses, ManageContactInformation, ManageContactImportantDates, ManageContactFeed, Dav, DavClient
- **Vault Domain** (12 sub-domains): ManageVault, ManageJournals, ManageCalendar, ManageCompanies, ManageTasks, ManageReports, ManageLifeMetrics, ManageFiles, ManageVaultSettings, Search
- **Settings Domain** (22 sub-domains): ManageUsers, ManageTemplates, ManageModules, ManageNotificationChannels, plus 18 personalization managers

Each sub-domain follows the pattern: `Services/` (business logic), `Web/Controllers/`, `Web/ViewHelpers/`.

### Multi-Tenancy Model

```
Account (billing/admin boundary, storage_limit_in_mb)
  |-- Users (multiple per account, with permissions)
  |-- Vaults (data containers, types: personal/family/community)
       |-- Contacts (central entity, full-text indexed)
       |-- Journals, Groups, Companies, Tags, Labels, etc.
```

### Permissions

Three-tier vault permissions: VIEW (300), EDIT (200), MANAGE (100). Services declare required permissions declaratively and `BaseService` validates them before execution. No fine-grained RBAC.

### Data Scale

- 69 database models
- 74 migration files
- 254 Vue 3 components
- 516 test files (PHPUnit)

---

## 4. Feature Inventory

### Core Specs

| Spec | Description |
|------|-------------|
| **contact-management** | Rich contact profiles with names, avatars, genders, pronouns, company/job, addresses, contact info, and 22+ attached data types organized via configurable template modules |
| **relationships** | Bidirectional typed relationships between contacts (family, love, work) with automatic reverse naming (e.g., "parent" creates "child") |
| **groups-labels** | Typed groups with configurable roles for contact collections, plus colored labels for flat tag-based categorization; both vault-scoped |
| **template-module-system** | Configurable contact page layouts with 22+ reusable modules (notes, calls, tasks, etc.) assignable to template pages; multiple templates per account |
| **vault-system** | Multi-vault data isolation with three-tier permissions (view/edit/manage), toggleable feature tabs, default templates, and per-vault configuration |

### Tracking Specs

| Spec | Description |
|------|-------------|
| **notes-documentation** | Per-contact notes with title, body, emotion, author tracking, and full-text search; separate document and photo management via Uploadcare CDN |
| **journal-activity-tracking** | Combined manual journaling and automatic activity logging into a unified timeline with mood ratings, post templates, and life metrics |
| **journal-timeline** | Structured journal posts with sections, tags, contact linking, and "slices of life" thematic groupings; plus timeline events with nested life events |
| **life-events** | Richly-modeled life occurrences with costs, duration, distance, places, emotions, and multiple participants; grouped into collapsible timeline events |
| **mood-tracking** | Configurable mood parameters per vault with per-contact mood events, optional sleep tracking, and vault-level mood reports |

### Productivity Specs

| Spec | Description |
|------|-------------|
| **tasks-goals** | Per-contact tasks with due dates and CalDAV sync, plus streak-based goals for habit tracking; vault-level task dashboard |
| **reminders-notifications** | One-time and recurring reminders with multi-channel delivery (email + Telegram), timezone-aware scheduling, and notification logging |
| **search-and-discovery** | Full-text contact search via Laravel Scout (Meilisearch/Typesense), vault-scoped, with "most consulted" quick access; no faceting |

### Integration Specs

| Spec | Description |
|------|-------------|
| **api-and-integrations** | Minimal REST API (only users + vaults), CardDAV/CalDAV sync, Uploadcare file storage, OAuth login; no webhooks or automation |

---

## 5. Key Strengths

1. **Modular template system** -- Contacts can have different page layouts with different module combinations. Templates are account-scoped and assignable per contact, enabling customization without code changes. 22+ built-in modules cover notes, calls, tasks, goals, addresses, relationships, and more.

2. **Rich activity feed** -- Every action (note created, label assigned, mood tracked, relationship set) generates a `ContactFeedItem`, providing a complete chronological audit trail per contact with 30+ action types.

3. **Bidirectional relationships** -- Automatic inverse relationship management with grouped types (family, love, work) and translatable names. The `name_reverse_relationship` pattern is elegant for entity linking.

4. **Journal with structured content** -- Posts with sections, metrics, tags, contact linking, and "slices of life" grouping create a rich personal documentation system. Mixing manual entries with automatic activity logs provides comprehensive history.

5. **Domain-driven architecture** -- Clean separation into 56 sub-domains with consistent service patterns. Each domain owns its business logic, controllers, and view helpers. Comprehensive test coverage (516 test files) mirrors the domain structure.

6. **Multi-vault data isolation** -- Vaults provide workspace-level data segregation with per-user permissions and configurable feature tabs. Each vault operates as an independent data container.

7. **Life events with rich metadata** -- Costs, duration, distance, places, emotions, and multiple participants per event. Timeline events group related life events into collapsible narratives with computed date ranges.

8. **CalDAV/CardDAV sync** -- Built-in DAV server enables contact and task synchronization with external tools (Apple Contacts, Thunderbird, etc.), extending the tool's reach beyond its own UI.

9. **Privacy-first design** -- Self-hostable, AGPL-licensed, no data monetization, no tracking. Aligns well with European data sovereignty expectations.

10. **Extensive customization** -- 20+ personalizable entity types (genders, pronouns, relationship types, call reasons, pet categories, religions, currencies, etc.) all configurable at the account level.

---

## 6. Key Weaknesses

1. **Minimal REST API** -- Only users and vaults are exposed via API. All contact management, notes, activities, reminders, tasks, journals, and other features are accessible only through the Inertia.js web interface. No programmatic access to core functionality.

2. **No workflow or pipeline concept** -- Purely a data recording tool. No process automation, no stage-based progression, no kanban boards, no pipeline visualization. Users manually log everything.

3. **No webhook or event-driven integrations** -- No way to trigger external actions on internal events. No n8n-style workflows. No outbound API calls on data changes.

4. **Single-user focused** -- Designed for individual use, not team collaboration. Three-tier permissions (view/edit/manage) lack fine-grained RBAC. No team task assignment, no role-based workflows, no multi-user collaboration features.

5. **No organization/company entity as first-class** -- Companies exist but are secondary to contacts. No organization hierarchy, no B2B relationship management, no account-based structures.

6. **Cloud file dependency** -- Images and files rely on Uploadcare CDN. No self-hosted file storage option. This conflicts with the privacy-first positioning.

7. **Limited search** -- Contact-only full-text search, vault-scoped. No faceted search, no cross-entity search (cannot search notes, journal entries, or activities), no saved searches.

8. **No reporting or analytics** -- Limited to address reports, date summaries, and mood reports. No pipeline metrics, no conversion tracking, no dashboard analytics, no custom reports.

9. **No mobile application** -- Web-only with responsive design. No native mobile experience despite being a tool that benefits from on-the-go use.

10. **No import/export beyond vCard** -- No CSV import/export, no bulk operations API, no data migration tools. Limited interoperability with other systems.

---

## 7. Relevance to Pipelinq

### Direct Competition: Low

Monica and Pipelinq target fundamentally different use cases. Monica is a **personal relationship management** tool for individuals tracking friends and family. Pipelinq is a **business pipeline management** tool for organizations managing leads, requests, and processes. The overlap is limited to the narrow domain of contact management and interaction logging.

### Architectural Patterns Worth Adopting

1. **Template + Module system** -- Monica's configurable contact page layouts are directly applicable to Pipelinq's pipeline stage cards. Different pipeline types could use different module configurations (e.g., a sales pipeline card shows different widgets than a support pipeline card). The Template -> TemplatePage -> Module hierarchy is a proven pattern.

2. **Activity feed per entity** -- The polymorphic `ContactFeedItem` pattern (30+ action types generating chronological audit trails) maps directly to pipeline item activity logs. Every state change, note addition, or assignment should create a feed item.

3. **Bidirectional relationships with reverse naming** -- The RelationshipGroupType -> RelationshipType pattern with `name_reverse_relationship` could enable rich entity linking in Pipelinq (e.g., "blocks" / "blocked by", "depends on" / "required by").

4. **Mixed timeline** -- Combining automatic system events with manual user notes into a single chronological feed is excellent UX. Pipelinq should adopt this for pipeline stage history views.

5. **Vault isolation as workspace pattern** -- Multi-vault with per-user permissions maps to multi-pipeline workspace isolation with team access control. The toggleable feature tabs are a lightweight way to customize workspace views.

6. **Configurable parameters per workspace** -- Monica's vault-scoped mood parameters, life event categories, quick fact templates, and label sets show a pattern of per-workspace configuration that avoids global rigidity.

### Where Pipelinq Clearly Differentiates

1. **Pipeline/process management** -- Monica has no concept of stages, progression, kanban boards, or process automation. This is Pipelinq's core value.

2. **API-first architecture** -- Monica's API exposes almost nothing. Pipelinq's OpenRegister foundation provides comprehensive API access to all entities.

3. **Automation via n8n** -- Monica has zero automation capability. Pipelinq's n8n integration enables workflow triggers on pipeline events, automated notifications, and process orchestration.

4. **Organization management** -- Monica tracks individuals only. Pipelinq manages organizations with hierarchies and B2B relationships.

5. **RBAC** -- Monica's three-tier permission model is too simple for business use. Pipelinq offers role-based access control.

6. **Nextcloud native integration** -- Monica is a standalone Laravel app. Pipelinq is a Nextcloud app with native contacts, files, and calendar integration.

7. **MCP protocol support** -- Monica has no machine-readable protocol for AI agents. Pipelinq supports MCP standard protocol.

8. **Duplicate detection** -- Monica has no deduplication. Pipelinq detects and merges duplicate contacts.

9. **Search with faceting** -- Monica's search is basic text matching on contacts only. Pipelinq's OpenRegister foundation supports faceted search across all entity types.

10. **Case management integration** -- Monica has no case/process management. Pipelinq integrates with Procest for case handling.

---

## 8. Feature Gap Analysis

Features Monica has that Pipelinq should consider:

| Monica Feature | Pipelinq Status | Priority | Notes |
|---------------|-----------------|----------|-------|
| Modular entity page templates | Not implemented | High | Configurable pipeline card layouts with reusable widget modules |
| Activity feed per entity | Partial (audit trail) | High | Comprehensive feed with 30+ event types; model for rich activity logs |
| Bidirectional typed relationships | Not implemented | Medium | Entity linking with automatic inverse relationships and grouped types |
| Streak-based goals/habits | Not implemented | Low | Unique pattern; could inspire pipeline health scoring or SLA tracking |
| Journal with structured posts | Not implemented | Low | Could inform process documentation or stage notes with structured sections |
| Life metrics (quantified tracking) | Not implemented | Medium | Per-workspace custom KPI tracking with historical values; applicable to pipeline metrics |
| Mood/sentiment per interaction | Not implemented | Low | Could translate to customer satisfaction ratings or deal confidence scoring |
| CalDAV task sync | Not implemented | Low | External task manager integration; Nextcloud Tasks may cover this natively |
| "Most consulted" quick access | Not implemented | Medium | Frequency-based quick access to recently/frequently used pipeline items |
| Slices of Life (thematic grouping) | Not implemented | Low | Could inspire pipeline phase/milestone grouping of activities |

Features Pipelinq has that Monica lacks (competitive advantages):

| Pipelinq Feature | Monica Equivalent | Advantage |
|------------------|-------------------|-----------|
| Pipeline stages (kanban) | None | Core differentiator |
| Organization management | Minimal companies | Full B2B support |
| Lead pipeline with progression | None | Sales/intake workflow |
| Request intake forms | None | Structured data capture |
| My Work queue | None | Personal task aggregation across pipelines |
| n8n workflow automation | None | Event-driven process automation |
| Full REST API | Users + vaults only | API-first architecture |
| Nextcloud integration | None | Native file, contacts, calendar |
| RBAC | Three-tier only | Fine-grained access control |
| Duplicate detection | None | Data quality management |
| Faceted search | Text search only | Advanced filtering and discovery |
| MCP protocol | None | AI agent integration |
| Case management (Procest) | None | End-to-end process handling |
| CSV import/export | vCard only | Flexible data interchange |
