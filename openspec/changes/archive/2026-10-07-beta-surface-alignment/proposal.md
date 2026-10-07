# Beta surface alignment — LaunchPad

## Why

LaunchPad's four public-facing surfaces (`appinfo/info.xml`, the shipped
feature set in `src/manifest.json` / `lib/`, the `conduction.nl/apps/launchpad`
product page, and the `launchpad.conduction.nl` docs) had drifted apart and
contained fabricated claims that do not exist in the code. Before a beta
release these four surfaces must agree on vocabulary, version, license, and
— above all — every feature claim must be true against `lib/` + `src/` at
HEAD. LaunchPad was also recently rebranded from "MyDash"; stale "MyDash"
brand references remained in docs prose (the technical app id `mydash` is
intentionally unchanged — see Decisions below).

## Canonical feature list (verified against `lib/` + `src/` at HEAD)

- **Drag-and-drop grid dashboards** — GridStack-based grid, position/resize
  freely; multiple personal dashboards, switchable.
- **Widget library** — 18 registered widget types
  (`src/constants/widgetRegistry.js`): label, text, image, link, native
  Nextcloud widget bridge (`nc-widget`), header banner, divider, files,
  people, quicklinks, news, video, calendar, links, menu, container, tile,
  spend-analytics. Any app that registers a Nextcloud dashboard widget (v1
  or v2 API) is wrapped automatically via the `nc-widget` type.
- **Admin templates** — pre-configured dashboards distributed to Nextcloud
  groups, with `view_only` / `add_only` / `full` permission levels and
  compulsory (non-removable) widgets.
- **Role-based widget access** — `RoleFeaturePermissionService` resolves the
  allowed-widget set per user from Nextcloud group membership + a configured
  `group_order` priority.
- **Conditional visibility** — show/hide a widget placement by group, time of
  day, or date (`ConditionalRule`).
- **Widget styling** — per-widget colour/border/title overrides.
- **Dashboard sharing** — user/group shares with a per-share permission
  level (`DashboardShareService`), plus a brute-force-protected public
  share link (`PublicShareController`/`PublicShareService`) and a chrome-less
  kiosk playlist mode (`KioskController`/`KioskService`) for shared screens.
  No Nextcloud login required for public-share/kiosk viewers.
- **Group dashboards** — one shared dashboard per group in addition to
  personal dashboards (`multi-scope-dashboards`).
- **Import/export & migration** — dashboard export/import CLI, a Confluence
  HTML-export importer, and a GroupFolder storage-backend migration path.
- **Admin settings & quotas** — allow-user-dashboards toggle, default
  permission level, default grid columns, and optional per-user
  `maxDashboardsPerUser` / `maxWidgetsPerDashboard` quotas.
- **Activity feed integration** — a 13-event Activity provider.
- **Optional OpenRegister consumption** — LaunchPad is OR-free at install
  time; a documented pattern (`useOrFeatureDetect`) lets a widget
  feature-detect OR at runtime, but **no shipped widget currently uses it**
  (see Removed claims). The `spend-analytics` widget separately reads
  Procest and FinanceQ over a runtime GraphQL client — not OpenRegister.

## Reconciliations applied

1. **`appinfo/info.xml`**
   - `<licence>agpl</licence>` → `<licence>EUPL-1.2</licence>`, matching the
     `EUPL-1.2` text already in both description CDATA blocks and every PHP
     file's docblock license header. The top-of-file SPDX comment was also
     corrected from `AGPL-3.0-or-later` / "LaunchPad Contributors" to
     `EUPL-1.2` / "Conduction B.V. \<info@conduction.nl\>" to match.
   - Removed `<app>openregister</app>` from `<dependencies>`. This was a
     genuine bug, not a style issue: `docs/architecture.md`'s "Runtime-only
     OR consumption policy" states in ALL CAPS that
     `appinfo/info.xml` "MUST NOT list `openregister`... as dependencies"
     and that LaunchPad "MUST boot and function fully on a Nextcloud
     instance with no OR installed" — yet `info.xml` hard-declared it,
     which would have made Nextcloud refuse to enable LaunchPad without
     OpenRegister present, contradicting the app's own architecture.
   - Added three EN+NL description bullets for previously undocumented
     shipped features: role-based widget access, dashboard sharing, group
     dashboards.
   - Version left at `1.0.5-unstable.9` (info.xml is the version source of
     truth per the alignment brief); the product page's unrelated `v0.9`
     placeholder was corrected to `v1.0.5` to match.

2. **Product page** (`conduction-website/src/pages/apps/launchpad.mdx` +
   the Dutch translation) — full rewrite. See "Claims removed" below.

3. **Dutch product page file location** — the Dutch translation lived at
   the stale pre-rename path
   `i18n/nl/docusaurus-plugin-content-pages/apps/mydash.mdx` (the English
   page had already been renamed to `launchpad.mdx`, but the NL file
   never followed). Docusaurus resolves an i18n translation by matching the
   source page's file path, so the stale filename meant the NL page either
   404'd or silently fell back to English. Moved to
   `apps/launchpad.mdx` and rewritten to match the corrected EN content.

4. **Docs** — `docs/intro.md`'s feature list and description
   (`"Compose KPI widgets and live charts on top of your OpenRegister
   data"`) were fabricated; replaced with the real feature list. Five docs
   files (`docs/architecture.md`, `docs/widgets/or-data.md`,
   `docs/tutorials/user/11-sharing-dashboards-publicly.md`,
   `docs/migration/widget-library-to-ncvue.md`) used "MyDash" as the brand
   name in prose; corrected to "LaunchPad". Lowercase `mydash` occurrences
   that are literal technical identifiers (route names, `oc_mydash_*`
   table names, the `mydash` i18n domain, `localStorage` keys) were left
   untouched — those are factually accurate to the current code and are
   not a "stale naming" leak, they are the real (intentionally unchanged)
   app id.

## Claims removed (unverified / fabricated against `lib/` + `src/`)

The prior product page (EN + NL) and `docs/intro.md` asserted the
following, none of which exist in the code:

- **"Ten chart types out of the box" (bar/line/area/pie/KPI/table/
  heatmap/funnel/gauge/scatter) + "custom chart types via a small React
  plugin API"** — no chart library (`apexcharts`/`chart.js`/`d3`) is a
  dependency; `widgetRegistry.js` has no chart-type widgets; LaunchPad's
  frontend is Vue 2, not React. Removed.
- **"LaunchPad reads OpenRegister directly via REST and GraphQL", "no ETL",
  dashboards "query the register live"** — `docs/architecture.md` states
  LaunchPad is OR-free and no shipped widget calls
  `useOrFeatureDetect()`/the OpenRegister API (`grep` found zero call
  sites). The only runtime GraphQL consumer is the `spend-analytics`
  widget, and it queries Procest/FinanceQ, not OpenRegister. Removed /
  replaced with the real, narrower claim.
- **"Real-time updates... dashboards refresh over WebSocket"** — no
  WebSocket code exists anywhere in `lib/` or `src/`. Removed.
- **"Every dashboard has a signed embed URL... embed outside Nextcloud...
  on a TV in the hall"** — no generic "embed URL" concept exists. The real
  features are the public-share token link and the kiosk playlist mode;
  rewritten to describe those accurately (kiosk mode genuinely targets
  shared/hall displays).
- **OpenCatalogi "indexes your published dashboards as catalogue entries"**
  — no OpenCatalogi integration exists anywhere in LaunchPad. Removed.
- **OpenConnector "pulls data from your existing systems into
  OpenRegister so LaunchPad has fresh material"** — no OpenConnector
  integration exists. Removed.
- **"Maintained by Conduction since 2019"** — unverifiable/likely wrong
  given the app's actual history (MyDash → LaunchPad rename in 2026);
  removed the date claim.

## Icon status

`img/app.svg` — white fill (`#fff`), 24×24 viewBox, matches the
Conduction app-icon convention (white glyph on the cobalt brand tile used
by the product page's `<DetailHero>`). No mismatch found.

## Decisions needed (not resolved by this change)

- **App id / namespace inconsistency.** `appinfo/info.xml` declares
  `<id>mydash</id>` and `appinfo/routes.php` / `occ` commands use the
  `mydash.*` / `mydash:*` prefix (per commit `4d708aa3`, "app id stays
  mydash" — an intentional decision to preserve App Store update
  compatibility). However a **later** commit (`33e6ff93`, "Rename app
  MyDash → LaunchPad") renamed the PHP namespace to `OCA\LaunchPad`, all DB
  tables to `launchpad_*`, and set `Application::APP_ID = 'launchpad'` —
  which is passed as the `$appName` to the NC `App` base constructor. The
  two rename efforts were never fully reconciled: the app currently runs
  with an internal `Application::APP_ID` of `launchpad` while Nextcloud's
  actual registered app id (from `info.xml`/folder) is `mydash`. This
  surface-alignment change deliberately did **not** touch `<id>`,
  `routes.php`, or the `occ mydash:*` commands — that is a functional
  migration decision (DB/app-config/route-generation implications) outside
  the scope of "make the 4 surfaces agree on text", but it should be
  tracked and resolved explicitly rather than left implicit.
- **Version format.** `1.0.5-unstable.9` is unusual for a beta app in this
  fleet — every other beta app in the fleet (`docudesk`, `procest`,
  `pipelinq`, ...) is still in the `0.x.y[-unstable.N]` range; a `1.0.x`
  version implies GA/stable under semver while the app is labelled "Beta"
  everywhere else. Left as-is per the alignment brief ("pick the info.xml
  version as truth"), but flagging the `1.0.x` vs `0.x.y` mismatch for a
  product decision.
