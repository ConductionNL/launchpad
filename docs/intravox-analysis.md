# IntraVox — Feature Analysis & mydash Capability Gap

> **Purpose.** Reverse-engineer the IntraVox Nextcloud app (`nextcloud/IntraVox`, v1.3.0, AGPL-3.0) for clean-room competitive analysis. Identify which capabilities mydash should adopt to reach SharePoint-style intranet parity. **No code is copied verbatim** — only behavioural descriptions, signatures, and configuration shapes. Spec proposals derived from this doc describe what *mydash* should do in mydash's own vocabulary; they never name IntraVox.
>
> **Source archive:** `mydash/intravox-source/` (gitignored, v1.3.0 tarball).
> **This doc:** `mydash/docs/intravox-analysis.md` (gitignored).
> **Pinned version:** v1.3.0 (released 2026-04-21, latest at analysis time).

---

## 1. Overview

### App identity

| Field | Value |
|---|---|
| App ID | `intravox` |
| Display name | IntraVox |
| Summary | "SharePoint-style intranet pages for Nextcloud — no code required" |
| Version | 1.3.0 |
| License | AGPL-3.0-or-later |
| Vendor | Nextcloud GmbH |
| Categories | Customization, Tools |
| NC compat | min 32, max 33 |
| PHP compat | 8.2+ |
| **Hard dep** | **GroupFolders** app (installed + enabled) |

### Scope

685 files: 104 PHP backend, 63 Vue 3 frontend components, 38 JS bundle outputs (so the JS bundle ships pre-built — Vue 3 SFCs in `src/`). 6 database tables, 18+ controllers, 30+ services, 7 migrations, 8 CLI commands, 3 background jobs, 5 showcase intranet sites.

### Categorical positioning

IntraVox is a **page-based intranet builder**. Mydash is a **dashboard tool**. They are different paradigms:

- **mydash**: each user has dashboards composed of widgets on a freeform GridStack canvas (x/y/w/h). Widgets are independent items. Distribution is per-user / per-group / default.
- **IntraVox**: an organisation has pages composed of rows. Each row has 1-5 columns. Each column has widgets. Pages have header rows and optional left/right side columns. Pages have draft/published state, locking for concurrent edit, version history, comments, reactions, and public sharing. Pages exist in a tree (navigation) and are addressable via URL.

Both share: drag-drop, widget concept, theming, NC integration. Otherwise: different products.

### What's actually in the box (from `appinfo/info.xml` `<description>`)

**Page editor:** drag-drop grid (1-5 cols), 7 templates, collapsible sections, sticky toolbar, row duplication, draft/published, page locking.

**Widgets:** Text (with tables + markdown), Image, Video, Links, Files, Dividers, Calendar (multi-cal merged + recurring), People (profiles + filters + birthdays), News (list/grid/carousel).

**Collaboration:** emoji reactions, threaded comments, version history (one-click restore), personal RSS feed, public sharing (optional password).

**Content management:** multi-language **content** (per-language folders), navigation editor (megamenu + sidebar), full export/import to ZIP including Confluence HTML import, demo content.

**Enterprise:** Department-based access via GroupFolders ACL, NC Unified Search (Ctrl+K), seamless theme/users/groups/Files integration.

---

## 2. Backend (PHP) — by area

### 2.1 Data model (6 tables)

| Table | Purpose |
|---|---|
| `intravox_page_index` | flattened tree index of pages — title, slug, parent, language, status, last-modified — for fast nav + search rendering without parsing every page file |
| `intravox_page_locks` | concurrent-edit lock per page (user, expires_at) |
| `intravox_page_stats` | analytics: views per page, by user/time |
| `intravox_feed_tokens` | per-user RSS feed tokens (so `https://nc.example.com/feed/<token>.xml` works without auth) |
| `intravox_lms_tokens` | OIDC tokens for LMS integration (learning management — out-of-scope for mydash) |
| `intravox_uv` | unique visitor counter (privacy-preserving page views?) |

**Key observation:** **page CONTENT is NOT in the DB.** Pages are JSON files inside a GroupFolder named `IntraVox`, with subdirectories for locale + page hierarchy. The `intravox_page_index` table is just an index for fast lookup. This is a deliberate "data lives in Files, metadata in DB" architecture — works well for Nextcloud's strengths (file ACL, file versions, file sharing).

### 2.2 Controllers (18+ — full route surface)

| Controller | What it does |
|---|---|
| `PageController` | CRUD + render of pages |
| `PageLockController` | acquire/release/extend page edit lock |
| `ApiController` | misc (settings, feature flags, demo flag) |
| `BulkController` | bulk-page operations (delete, move, change status, re-index) |
| `CalendarController` | merged calendar widget data — fetches from NC Calendar app + external ICS URLs |
| `CommentController` | threaded comments on pages |
| `DemoDataController` | install/remove demo content (5 showcase intranets) |
| `ExportController` | export pages/site to ZIP |
| `FeedController` | per-user RSS feed (includes private content; auth via per-user token) |
| `FeedReaderController` | external RSS/Atom URL ingest for News widget |
| `FooterController` | per-instance footer config (branding, links, legal) |
| `ImportController` | import ZIP (own format) AND Confluence HTML export |
| `LicenseController` | license key + telemetry opt-in (commercial enterprise tier?) |
| `LmsOAuthController` | LMS (Moodle/etc) OIDC bridge — out-of-scope for mydash |
| `NavigationController` | manage the site navigation tree (megamenu/sidebar config) |
| `OrphanedDataController` | find/clean orphaned widget assets, dangling locks, etc. |
| `PeopleController` | people directory data — users + filters + birthdays |
| `AnalyticsController` | aggregated page-view stats |

### 2.3 Services (30+)

Same areas as controllers, plus internal helpers:

- **`PageIndexService`** — keep `intravox_page_index` in sync with file-system page JSONs.
- **`SetupService`** — first-run wizard: create the IntraVox GroupFolder, apply ACLs, seed demo if requested.
- **`PermissionService`** — check who can read/write each page (delegates to GroupFolder ACL + per-page overrides).
- **`PublicShareService`** — issue public-share tokens for pages, optional password.
- **`PublicationSettingsService`** — per-page draft/published state + scheduled publish-at.
- **`EngagementSettingsService`** — feature flags for reactions/comments per page.
- **`SystemFileService`** — abstract over file-system access (page JSONs, demo files, exports).
- **`Import/`** subpackage — Confluence HTML parser + page-tree mapper.
- **`MetaVoxImportService`** — import from a separate "MetaVox" product (sibling tool? out-of-scope for mydash).
- **`TelemetryService`** + **`LicenseService`** + **`LmsOAuthService`** — enterprise tier features (out-of-scope for mydash).
- **`OidcTokenBridge`** — token exchange between NC OIDC and downstream services (out-of-scope unless mydash adds federated auth).

### 2.4 Background jobs

| Job | Cadence | What it does |
|---|---|---|
| `FeedRefreshJob` | likely hourly | re-fetch external RSS/Atom feeds for News widgets so users get fresh content |
| `LicenseUsageJob` | likely daily | report active-user counts to licensing service (enterprise) |
| `TelemetryJob` | likely daily | opt-in usage stats |

### 2.5 CLI commands

| Command | Purpose |
|---|---|
| `SetupCommand` | first-run via CLI (creates GroupFolder, applies ACL) |
| `ImportDemoDataCommand` | install one of the 5 showcase sites |
| `AddDemoFieldsCommand` | augment demo data with extra synthetic fields |
| `CreateLanguageHomepagesCommand` | bootstrap per-language home pages |
| `MigrateToLanguageStructureCommand` | one-time migration: flat → per-language directory structure |
| `CopyNavigationCommand` | clone navigation across language trees |
| `ImportPagesCommand` | bulk import from ZIP/Confluence (CLI alternative to ImportController) |
| `DebugShareCommand` | dump share state for a page (debugging tool) |

### 2.6 Events

- `PageDeletedEvent` (lib/Event/) — fired on page deletion; consumed by `CommentsEntityListener`, `PageDeletedListener`, `UserDeletedListener` for cascade cleanup (comments, reactions, locks, share rows).

### 2.7 Search

`lib/Search/` — implements `IProvider` for NC Unified Search. Indexes page titles + body text; results show in Ctrl+K with deep-link to the page.

---

## 3. Frontend (Vue 3) — widget catalogue + UI surfaces

### 3.1 Page-builder UI

| Component | Role |
|---|---|
| `PageEditor.vue` | main edit surface: rows, drag-drop, toolbar |
| `PageViewer.vue` | read-only render |
| `PublicPageView.vue` | public-share render |
| `Widget.vue` + `WidgetEditor.vue` + `WidgetPicker.vue` | generic widget host + editor + picker (analogous to mydash's modal pattern) |
| `SideColumn.vue` | left/right side-column container |
| `Navigation.vue` + `NavigationItem.vue` | header navigation render |
| `NavigationEditor.vue` | drag-drop nav tree editor |
| `Breadcrumb.vue` | breadcrumb trail rendering |
| `PageTreeModal.vue` + `PageTreeSelect.vue` | page-picker dialogs (move/link/etc.) |
| `Footer.vue` | per-instance footer |

### 3.2 Page lifecycle modals

| Modal | Role |
|---|---|
| `NewPageModal.vue` | create page (pick template, language, parent) |
| `PageSettingsModal.vue` | per-page settings (slug, status, ACL, comments-on, reactions-on) |
| `SaveAsTemplateModal.vue` | promote page → template |
| `TemplatePreviewCard.vue` | template gallery card |
| `WelcomeScreen.vue` | first-run helper |
| `ShareDialog.vue` + `ShareButton.vue` + `NoShareDialog.vue` | public-share flow |
| `PageDetailsSidebar.vue` | metadata panel (versions, comments, reactions in sidebar) |

### 3.3 Widgets (the meaty part)

| Widget | Renderer | Editor | Variants |
|---|---|---|---|
| Calendar | `CalendarWidget.vue` | `CalendarWidgetEditor.vue` | merged multi-source view, event list/agenda |
| Feed | `FeedWidget.vue` | `FeedWidgetEditor.vue` | layouts: `feed/FeedLayoutGrid.vue`, `feed/FeedLayoutList.vue` |
| News | `NewsWidget.vue` | `NewsWidgetEditor.vue` | layouts: `news/NewsLayoutList.vue`, `news/NewsLayoutGrid.vue`, `news/NewsLayoutCarousel.vue` |
| People | `PeopleWidget.vue` | `PeopleWidgetEditor.vue` | layouts: `people/PeopleLayoutCard.vue`, `people/PeopleLayoutGrid.vue`, `people/PeopleLayoutList.vue` |
| Links | `LinksWidget.vue` | `LinksEditor.vue` | configurable link list |
| Text | `InlineTextEditor.vue` | (inline) | with markdown + tables (per README) |
| Media | `MediaPicker.vue` (chooser) | (?) | image/video picker via NC Files |

Plus **divider** + **files** + **video** widgets (mentioned in the description; their .vue files are likely smaller stand-alone components I haven't enumerated).

### 3.4 Engagement components

| Component | Role |
|---|---|
| `reactions/ReactionBar.vue` | emoji reactions row at page bottom |
| `reactions/ReactionPicker.vue` | emoji picker popover |
| `reactions/CommentSection.vue` | threaded comments host |
| `reactions/CommentItem.vue` | single comment + reply tree node |

### 3.5 Search + admin

| Component | Role |
|---|---|
| `SearchBar.vue` + `SearchResults.vue` | page-search UX (separate from NC Unified Search; in-app) |
| `admin/AdminSettings.vue` + `admin/components/*` | admin settings panel: footer, engagement defaults, support, license, feed tokens etc. |
| `FeedSettings.vue` | per-user RSS feed token issuance |
| `SupportSettings.vue` | help/support config |

### 3.6 Showcase content

5 ready-made example intranets in `showcases/` (`de-bron`, `de-linden`, `gemeente-duin`, `horizon-labs`, `van-der-berg`). Each ships with 4 JSON files (likely: pages tree, navigation, footer, settings). Plus 7 templates in `showcases/templates/`. These are the "instant intranet" content that lets a fresh install demonstrate the product.

---

## 4. Showcases / demo data

The 5 showcases are themed example sites:
- `gemeente-duin` — Dutch municipality
- `horizon-labs` — tech company
- `van-der-berg` — professional services firm
- `de-bron` / `de-linden` — generic Dutch company sites

These give buyers a "here's what an intranet looks like" tour without needing to build content. Multi-language (de/en/fr/nl folders also present at `demo-data/`).

---

## 5. Capability inventory + mydash gap analysis

Each row is a distinct capability IntraVox ships. **Verdict** column says how it should land in mydash:
- **NEW** — net-new for mydash, write a fresh capability spec
- **EXTEND** — mydash has the foundation, add a delta to the existing capability
- **DUPLICATE** — mydash already does this, skip
- **SKIP** — out-of-scope for mydash's mission (different product category, enterprise-tier-only feature, etc.)

| # | Capability | mydash today | Verdict | Notes |
|---|---|---|---|---|
| 1 | **Page model** (rows × columns × widgets, header row, side columns) | only freeform GridStack dashboards | **NEW** | Foundational. Distinct from dashboards. |
| 2 | **Page templates** (7 ready-made, gallery picker, "save as template") | none | **NEW** | UX accelerator |
| 3 | **Page locking** (concurrent-edit guard) | none | **NEW** | Required when 2+ users edit same surface |
| 4 | **Draft / published / scheduled-publish workflow** | none | **NEW** | Editorial workflow |
| 5 | **Page versions + restore** | none | **NEW** | Audit trail; uses NC Files versioning if pages are file-backed |
| 6 | **Threaded comments on pages** | none | **NEW** | NC has CommentsManager — wrap it |
| 7 | **Emoji reactions** | none | **NEW** | Engagement |
| 8 | **Personal RSS feed** (per-user token, includes private content) | none | **NEW** | Pull-mode update notification |
| 9 | **Public share with optional password** | mydash dashboards are auth-only | **NEW** | Different model — needs anonymous-render path |
| 10 | **Per-language CONTENT** (not just UI strings) | UI i18n only | **NEW** | Major: translate page content, not just labels |
| 11 | **Navigation tree editor** (mega-menu + sidebar) | mydash sidebar lists user's dashboards; no admin-curated org-wide tree | **NEW** | Different tier of nav (org-wide vs personal) |
| 12 | **Page tree** (parent/child hierarchy, slugs, breadcrumbs) | dashboards are flat | **NEW** | Tree model |
| 13 | **Export / import to ZIP** | none | **NEW** | Backup + migration tool |
| 14 | **Confluence HTML import** | none | **NEW** | One-shot migration from a competitor |
| 15 | **Demo data / showcase sites** | none | **NEW** | Onboarding accelerator |
| 16 | **GroupFolders ACL integration** | mydash dashboards have own permission model | **EXTEND** | Could route page-content storage through GroupFolders without overriding mydash's existing ACL |
| 17 | **NC Unified Search integration** | none | **NEW** | Ctrl+K hits the IntraVox index |
| 18 | **Footer customization** | none | **NEW** | Per-instance branding |
| 19 | **Page view analytics** (per-page, per-user, time series) | none | **NEW** | Stats panel for owners |
| 20 | **Bulk page operations** (delete/move/status across many) | only single-dashboard CRUD | **EXTEND** | Add bulk-dashboard operations to existing dashboards capability |
| 21 | **Setup wizard** (first-run) | none | **NEW** | UX |
| 22 | **Orphaned data cleanup** | none | **NEW** | Maintenance tool |
| 23 | **Calendar widget** (multi-cal merged, recurring events, ICS) | none | **NEW** | Big new widget |
| 24 | **People widget** (filters, birthdays, profiles) | none | **NEW** | Big new widget |
| 25 | **News widget** (RSS aggregator with list/grid/carousel layouts) | none | **NEW** | Big new widget |
| 26 | **Files widget** (browse NC Files inline) | none | **NEW** | Embed file browser |
| 27 | **Video widget** | none | **NEW** | Media embed |
| 28 | **Divider widget** | none | **NEW** | Layout primitive |
| 29 | **Links widget** (curated link list) | only standalone link-button widget | **EXTEND** | Generalise existing link-button into a list-mode |
| 30 | **Markdown + tables in text widget** | text widget has plain text + HTML via DOMPurify | **EXTEND** | Add markdown rendering + table syntax |
| 31 | **Media picker** (NC Files chooser) | image-widget has URL/upload only | **EXTEND** | Add a third path: pick from existing NC Files |
| 32 | **Per-page comments-on / reactions-on toggle** | n/a (no comments/reactions) | **NEW** | Falls out of #6/#7 — same spec |
| 33 | **Background-job for feed refresh** | none | **NEW** | Pre-fetch news widget content |
| 34 | **CLI commands** (setup, demo, import, language migration) | none | **NEW** | Operator UX |
| 35 | **Activity feed integration** | none | **NEW** | NC Activity app integration for page changes |
| 36 | **PageDeletedEvent + cascade listeners** | n/a | **NEW** | Integrity — comes free with #1 |
| 37 | **Inline text editor** | n/a (uses external modal form) | **EXTEND** | UX upgrade — edit text widgets without opening a modal |
| 38 | LMS OIDC bridge | n/a | **SKIP** | Out-of-scope (LMS-specific tooling) |
| 39 | License + telemetry | n/a | **SKIP** | Enterprise tier; mydash isn't licensed |
| 40 | MetaVox import | n/a | **SKIP** | Importer for sibling commercial product |

### Tally

- **NEW** capabilities: **27** (genuinely new specs)
- **EXTEND** existing mydash capabilities: **5** (smaller deltas)
- **DUPLICATE**: 0 (the products don't overlap meaningfully)
- **SKIP**: 3 (out-of-scope for mydash's mission)

**Sum of work in scope: 32 capabilities** (27 new + 5 extensions). Larger than the Sendent set (25), but most NEW capabilities are well-bounded — calendar widget, people widget, comments, etc. — each can be its own focused spec change.

### Suggested sequencing (mirrors what worked for Sendent)

1. **Foundation** (the page model + locks + storage) — gates everything else
2. **Persistence + lifecycle** (draft/published, versions, public-share, language content)
3. **Widgets** (each as its own spec; can fan out in parallel since each is isolated)
4. **Engagement** (comments, reactions, RSS)
5. **Org-level UX** (navigation editor, footer, search integration, analytics)
6. **Operator tools** (CLI, setup, import/export, demo data, orphan cleanup)
7. **Glue** (events, background jobs, activity feed)
8. **Final integration** (the page editor / viewer shells that combine everything)

---

## 5b. Vendor marketing page (voxcloud.nl/intravox) — additions to inventory

Features clearer / new from voxcloud's site that weren't fully captured in §1-5:

| Feature | Detail |
|---|---|
| **H1-H6 headings in text widget** | Explicit heading-level support, not just bold/inline |
| **Comments thread depth = 1** | Threaded comments are explicitly **one level deep** (not arbitrary nesting) |
| **NC Comments API reuse** | Comments piggyback on Nextcloud's `OCP\Comments\ICommentsManager` rather than a custom table |
| **Collapsible rows with default state** | "SharePoint-style" expand/collapse for sections; admin sets default open/closed; intended for FAQ-style content |
| **Navigation up to 3 levels deep** | Megamenu OR dropdown style; supports external URL links |
| **Video embed allowed-domains list** | Admin-configurable allowlist of video sources (YouTube, Vimeo, PeerTube + local files); security setting |
| **Auto-detect user's NC language for content** | When language-content variants exist, the user's NC locale picks the variant automatically |
| **Custom page metadata fields** | Per-page custom metadata; the news widget can filter on these fields |
| **Tags + categories searchable** | Full-text + faceted search over the page corpus |
| **3 built-in roles (Admins / Editors / Users)** | IntraVox-specific role layer above NC groups |
| **Per-page OR global comments/reactions toggle** | Admins can disable engagement everywhere or page-by-page |
| **Page versions = NC Files versions** | Confirms the file-backed architecture: page versioning is just NC's file-version log |
| **OpenAPI 3.1 — 25+ documented endpoints** | API surface is documented; mydash already has OpenAPI hooks |
| **Tier limit: 50 pages per language (free)** | Pricing constraint, not a code feature; ignore for mydash |

---

## 5c. Reframe under the unified-dashboard model (decision B)

User decision: **dashboards ARE the surface. Pages don't exist as a separate concept.** All IntraVox functionality decomposes into:
- **(a) New widget types** placed on existing dashboards (header, menu, quicklinks, video, calendar, people, news, files, divider, links, table)
- **(b) New capabilities AT THE DASHBOARD LEVEL** (lock, version, comments, reactions, rss, public-share, language-variants, tree, metadata, collapsible-sections, templates, export, analytics, bulk-ops)
- **(c) Org-level UX** that surrounds dashboards (footer, search, demo data, navigation editor, admin roles)
- **(d) Operator tools + glue** (setup wizard, CLI, background jobs, cascade events, orphan cleanup)
- **(e) Foundation** (storage backend abstraction so dashboard content CAN live in a GroupFolder JSON when it makes sense)

This is the cleanest unification. No parallel data model. Existing mydash dashboards keep working. Pages-as-such don't exist; they were always just "dashboards with these specific widgets configured a certain way."

### Final capability list (38 specs) under the unified model

**Foundation (3):**
1. `groupfolder-storage-backend` — abstract dashboard CONTENT storage so it CAN live as JSON in a managed GroupFolder (auto-creates the GroupFolder, applies ACL, persists widget content as files). Decision A.
2. `dashboard-tree` — parent/child hierarchy on dashboards, slugs, breadcrumbs.
3. `dashboard-metadata-fields` — admin-defined custom fields per dashboard (used by news widget filtering, search facets, etc.).

**Lifecycle (5):**
4. `dashboard-templates` — template gallery + "save as template" + per-template preview cards.
5. `dashboard-locking` — concurrent-edit guard with auto-expiry + admin override.
6. `dashboard-versioning` — version history + one-click restore (delegates to NC Files versioning when storage backend is GroupFolder).
7. `dashboard-draft-published` — draft / published / scheduled-publish-at workflow.
8. `dashboard-language-content` — per-language content variants + auto-detect user's NC locale to pick the variant.

**Engagement (4):**
9. `dashboard-comments` — threaded one-level-deep comments via NC `ICommentsManager`; admin toggle per-dashboard OR global.
10. `dashboard-reactions` — emoji reactions; admin toggle per-dashboard OR global.
11. `dashboard-rss-feeds` — per-user RSS token; feed includes dashboards user has access to; private content respects ACL.
12. `dashboard-public-share` — anonymous render with optional password; non-authed access path through GroupFolder ACL.

**Widgets — net-new types (10):**
13. `calendar-widget` — multi-calendar merged view + recurring events + external ICS URLs.
14. `people-widget` — directory of users with profile cards + filters + birthday tracking; layouts: card / grid / list.
15. `news-widget` — RSS aggregator + filtering by dashboard metadata; layouts: list / grid / carousel.
16. `files-widget` — embed an NC Files folder browser inline.
17. `video-widget` — embed YouTube / Vimeo / PeerTube / local file; admin-configurable allowed-domains list.
18. `divider-widget` — visual separator with whitespace presets.
19. `links-widget` — multi-column link grid (distinct from existing single link-button); curated list with icons + sections.
20. `header-widget` — full-width banner with title, optional image, optional CTA button (replaces IntraVox's "header row" concept as a widget).
21. `menu-widget` — in-page hierarchical navigation up to 3 levels, dropdown or megamenu styling, supports external URL links (distinct from the dashboard sidebar).
22. `quicklinks-widget` — icon-grid of shortcuts (intranet "tile" pattern).

**Widgets — extensions to existing (4):**
23. `text-widget-markdown` — extend existing text widget with markdown rendering, H1-H6 heading shortcuts, table syntax.
24. `text-widget-tables` — full table editor with row/column manipulation (could fold into #23 or stay separate; recommend separate since the table editor UX is substantial).
25. `image-widget-media-picker` — extend existing image widget with NC Files picker as a third source (alongside URL + upload).
26. `link-button-widget-list-mode` — extend existing link-button widget with a list-mode variant (multiple links in one widget).

**Org UX (5):**
27. `footer-customization` — per-instance footer with branding, links, legal text.
28. `nc-unified-search-integration` — implement `OCP\Search\IProvider` so Ctrl+K finds dashboards + widget content.
29. `navigation-editor-org` — admin-curated org-wide nav tree (separate from each user's personal sidebar list).
30. `demo-data-showcases` — N ready-made example dashboards + "Install demo data" admin button.
31. `admin-roles` — built-in role layer (Dashboard Admins / Editors / Viewers) on top of NC groups, for finer-grained intranet roles.

**Maintenance (5):**
32. `dashboard-export-import` — ZIP export of a dashboard (or full site) + import.
33. `confluence-html-import` — one-shot importer for Confluence HTML exports → dashboards + widgets.
34. `dashboard-view-analytics` — per-dashboard view tracking (privacy-preserving) + admin stats panel.
35. `dashboard-bulk-operations` — multi-dashboard delete / move / change status / re-index.
36. `orphaned-data-cleanup` — maintenance command to find/clean orphaned widget assets, dangling locks, expired share tokens.

**Glue (5):**
37. `setup-wizard` — first-run admin wizard: create GroupFolder if needed, seed roles, optionally install demo data.
38. `cli-commands` — operator commands (setup, demo install, language migration, page import, debug share).
39. `background-job-feed-refresh` — periodic refresh of news widget RSS sources so users see fresh content without on-demand fetch.
40. `activity-feed-integration` — emit NC Activity events on dashboard / comment / reaction changes.
41. `dashboard-cascade-events` — `DashboardDeletedEvent` + listeners cleaning up comments / reactions / locks / feed tokens / share rows.

Plus the **collapsible rows** capability — folds into either the dashboard-tree spec or into a small dedicated spec; counting it under #2 dashboard-tree's "section" sub-feature → not a separate spec.

**Tally:** 41 specs. Larger than the Sendent set (25). About a third are widgets (small + parallel-friendly), about a third are dashboard-level capabilities (sequential — many depend on the storage backend), about a third are org-UX / maintenance / glue (mixed).

### Suggested wave plan (deps-aware, mirrors Sendent pattern)

| Wave | Specs | Why this order |
|---|---|---|
| 1 — Foundation | #1 storage-backend, #2 tree, #3 metadata-fields | Everything else may need to read/write through these |
| 2 — Lifecycle | #4-8 (templates, lock, version, draft-published, language) | Dashboard-level capabilities — none touch widget code |
| 3 — Engagement | #9-12 (comments, reactions, rss, public-share) | Independent of widgets; can run after lifecycle |
| 4 — Widget extensions | #23-26 (text-md, text-tables, image-mediapicker, link-list) | Touch existing widget code; need to land before new widgets to avoid registry conflicts |
| 5 — New widgets (parallel) | #13-22 (calendar, people, news, files, video, divider, links, header, menu, quicklinks) | Each widget is isolated — fan out 4-5 in parallel waves |
| 6 — Org UX | #27-31 (footer, search, nav-editor, demo, roles) | Surrounds the dashboard surface |
| 7 — Maintenance | #32-36 (export, confluence, analytics, bulk, orphans) | Operator tools |
| 8 — Glue | #37-41 (setup, cli, feed-refresh, activity, cascade) | Final polish + maintenance jobs |



Before I spawn spec-creation, three calls worth your input:

### Decision A — GroupFolders dependency

IntraVox **requires** the GroupFolders app and stores page content as JSON files in a managed GroupFolder. That gives them ACL, versioning, sharing for free.

For mydash, two options:
1. **Same model** — adopt GroupFolders as a hard dep, mirror the file-backed-page architecture. Pros: free file versions, ACL via GroupFolders, sharing via NC Files. Cons: dependency adds install friction.
2. **DB model** — store pages in mydash's own DB tables (extend `oc_mydash_*`). Pros: no extra dep, integrates with existing dashboard machinery. Cons: reimplement versioning + ACL ourselves.

Recommend **option 1** for fewest surprises (NC's file infrastructure is solid). Pick before specs go in — affects every page-related spec.

### Decision B — page ↔ dashboard relationship

mydash already has dashboards. IntraVox has pages. Both are widget-bearing surfaces. Options:
1. **Two distinct concepts** — keep dashboards (personal/group, freeform GridStack); add pages (org-wide, row-based, file-backed). Two side-by-side surfaces.
2. **One unified concept** — collapse both into "pages" with a per-page layout-mode flag (`gridstack` | `row-grid`). Migrate old dashboards.
3. **Pages = dashboards rendered differently** — keep one data model, different render modes.

Recommend **option 1** — two products in one app is fine. Existing dashboards code keeps working. Pick before specs go in.

### Decision C — scope-trim

27 NEW capabilities is a lot. Some have low value-per-effort:
- **Analytics** (#19) is a polish feature
- **Setup wizard** (#21) is nice-to-have
- **Orphaned data cleanup** (#22) is maintenance
- **Activity feed integration** (#35) is integration polish

Worth dropping these from the first spec wave? OR keep all 32 and let the implementation phase decide priority?

Recommend **keep all 32 specs** but explicitly tag scope/priority in the proposal headers so implementation can sequence sensibly.

---

## 7. Next step

Once you've answered A/B/C above, I'll spawn the spec-creation work in the same wave pattern as the Sendent set:

- One change folder per capability (`mydash/openspec/changes/<slug>/`)
- Three files per change (`proposal.md`, `tasks.md`, `specs/<capability>/spec.md`)
- Neutral capability slugs (no "intravox" or "intra" anywhere in names)
- Same deps-aware layering so the implementation phase can sweep in parallel waves

Estimated output: **27 new specs + 5 extension specs = 32 change folders, ~96 files.**
