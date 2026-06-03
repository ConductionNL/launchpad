# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

### Added

- **Nextcloud Dashboard widget proxy** (`nc-widget` placement type,
  REQ-WDG-018..021): any installed Nextcloud Dashboard widget (Mail, Calendar,
  Talk, Weather, etc.) can now be embedded as a grid cell. Two rendering modes:
  (1) **native** — the widget's own bundle registers its callback via
  `OCA.Dashboard.register`; the bridge hands the render container to it for full
  feature parity with `/dashboard`; (2) **API fallback** — items are fetched via
  `GET /api/widgets/items?widgets[]=<id>&limit=7` and rendered as a flat 7-item
  card list. A 200 ms × 15 retries (~3 s) poll detects late-loading bundles and
  upgrades the cell to native mode without flicker. Display modes: `vertical`
  (32 px icons, flex-column list) and `horizontal` (120 px cards, 44 px icons,
  flex-row wrap). Widget picker uses `NcWidgetGridPicker` (icon cards populated
  from the `widgets` initial-state list). i18n: `nl_NL` + `en_US`.

- **Dashboard-switcher sidebar** (REQ-SWITCH-001..007): a fixed-position
  slide-in left navigation panel (`DashboardSwitcherSidebar.vue`) lists
  every dashboard visible to the user, grouped into three labelled sections
  in fixed order — primary group, default group, and personal ("My
  Dashboards"). Empty sections collapse entirely. Each row renders its icon
  via the shared `IconRenderer` (no inline URL branching). Clicking a row
  emits `update:open(false)` then `switch(id, source)`, where `source` is
  load-bearing (drives the correct API endpoint in the runtime shell).
  Personal rows expose a cog menu (`DashboardRowActions`) with Edit /
  Configure / Add custom widget / Delete entries; the delete action emits
  `delete-dashboard(id)` without triggering a switch. A dedicated
  `NcButton` card below the personal list serves as the `+ New Dashboard`
  affordance when `allowUserDashboards === true`. Slide-in animation is
  CSS-only (`transform: translateX(-100%) ↔ translateX(0)` over 0.25 s
  ease). Companion `SidebarBackdrop.vue` provides a click-to-close overlay.
  A persistent `SidebarFooter` with "Powered by Sendent / Conduction" brand
  logos and a Documentation link is pinned via `position: sticky; bottom: 0`.
  Wired in `Views.vue`; Esc closes the sidebar; all string labels translated
  (`en_US` + `nl_NL`).

- **Per-user RSS / Atom dashboard feeds** (REQ-FEED-001..009): users can
  now opt-in to a personal feed of their accessible dashboards via
  `GET /api/feed/token`. New routes `POST /api/feed/token/regenerate`
  (atomic rotate), `DELETE /api/feed/token` (idempotent soft-revoke),
  and the public `GET /feed/{token}.xml` (no Nextcloud auth — gated
  only by the opaque token). Tokens are 32 random bytes encoded
  URL-safe base64 (~43 chars, 256-bit entropy, non-enumerable). Feed
  output is RSS 2.0 by default; `Accept: application/atom+xml` (or the
  `?format=atom` query fallback) switches to Atom 1.0. Visibility
  reuses `DashboardService::getVisibleToUser()` so private dashboards
  never leak to public feed consumers; item count is capped at the
  admin-tunable `mydash.feed_item_cap` (default 50). New
  `oc_mydash_feed_tokens` table with `UNIQUE(user_id)` enforces the
  one-token-per-user rotation invariant.

### Changed

- **GridStack bumped from `^10.3.1` to `^12.2.1`** (resolved 12.6.0). Major
  version bump bundled with the responsive-grid-breakpoints change. The
  `GridStack.init` signature, the `change` event payload, the
  `engine.nodes` accessor, the `removeWidget(el, removeDOM)` call, and the
  `enable()`/`disable()` lifecycle methods used by `DashboardGrid.vue` are
  unchanged across the v10 -> v12 jump, so no caller-side breakage was
  observed during the bump. Downstream forks pinning a narrower
  `gridstack` range will need to widen their dependency.

### Added

- **Responsive grid breakpoints** (REQ-GRID-007 / REQ-GRID-012 /
  REQ-GRID-013): the GridStack instance now reflows proportionally at four
  viewport widths instead of staying fixed-12-column on narrow screens.
  Breakpoints `[{w:1400,c:12},{w:1100,c:8},{w:768,c:4},{w:480,c:1}]` with
  the `moveScale` layout algorithm. Geometry constants (`CELL_HEIGHT = 60`,
  `GRID_MARGIN = 8`, `BREAKPOINTS`) live in
  `src/composables/useGridManager.js` as the single source of truth and
  are mirrored to the CSS custom property `--mydash-cell-height` at
  init time so `calc()` expressions stay in sync. Cell height moved from
  the previously documented 80 px to 60 px to better support multi-row
  info widgets; flip the `CELL_HEIGHT` constant in the composable (single
  edit) if a denser/looser default is preferred.

- **Initial-state contract** (REQ-INIT-001..006): `lib/Service/InitialStateBuilder.php`
  centralises the per-page initial-state payload pushed via Nextcloud's
  `IInitialState` service. The matching JS reader at
  `src/utils/loadInitialState.js` returns a typed default-filled object for
  the workspace and admin pages. Both sides stamp / validate a
  `_schemaVersion` constant; deploy skew between PHP and JS surfaces as a
  console warning at runtime.

  Adding, removing, or renaming a key requires four coordinated edits in
  the same commit:
  1. update the spec Data Model in
     `openspec/specs/initial-state-contract/spec.md`,
  2. bump `INITIAL_STATE_SCHEMA_VERSION` in
     `lib/Service/InitialStateBuilder.php` AND
     `src/utils/loadInitialState.js`,
  3. add (or remove) the typed setter in the PHP builder and the matching
     entry in the JS reader's `PAGE_KEYS` table,
  4. update the controller(s) that call the builder.

  CI guards (`composer lint:initial-state`, `npm run lint:initial-state`)
  forbid direct `IInitialState::provideInitialState()` calls outside the
  builder and direct `loadState('mydash', ...)` calls outside the reader.

## 0.1.0 - Initial Release

- Initial app structure
- Basic Nextcloud integration
