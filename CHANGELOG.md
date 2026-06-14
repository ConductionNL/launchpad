# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

### Added

- **Dashboard quota limits** (`dashboard-quota-limits`): two
  admin-configurable numeric quotas — `maxDashboardsPerUser` and
  `maxWidgetsPerDashboard` — with server-side fail-closed enforcement on
  every user-initiated creation path (create, duplicate, fork, import,
  add-widget / add-tile) via a single `QuotaService`. Both default to `0`
  (unlimited), so behaviour is unchanged on upgrade. Exceeding a limit
  returns HTTP 409 with a structured `quota_exceeded` body; the dashboards
  list response carries an additive `quota` envelope so the UI disables the
  "Add dashboard" / "Add widget" affordances at the limit with a localised
  tooltip. Lowering a limit never deletes or hides existing data
  (grandfathering); `allowMultipleDashboards = false` is honoured as an
  effective limit of 1 (most-restrictive-wins); admin template rollout and
  compulsory-widget pushes are exempt via an explicit provisioning bypass.

### Deferred

- **Admin group-management UI** (`multi-scope-dashboards` Task 13):
  the admin-facing group-shared CRUD UI was filed as the dedicated
  follow-up change `openspec/changes/admin-group-management/` so the
  `multi-scope-dashboards` API + read endpoint slice could ship without
  UX dependencies. The follow-up adds an eighth Beheer tab
  (**Group dashboards**) wrapping the existing
  `/api/dashboards/group/...` endpoints; no new backend semantics.

### Changed

- **GridStack bumped to v12.x** (REQ-GRID-013): `gridstack` dependency
  updated from `10.3.1` to `^12.2.1`. Downstream consumers that pin an
  exact GridStack version or import internal GridStack APIs should update
  their constraints accordingly. The `columnOpts.breakpoints` / `moveScale`
  API surface used by this app is stable across v10+.

- **Responsive grid breakpoints** (REQ-GRID-007): the dashboard grid now
  reflows proportionally at four explicit viewport widths — 12 columns
  (≥ 1400 px), 8 columns (≥ 1100 px), 4 columns (≥ 768 px), 1 column
  (≥ 480 px or below). The `moveScale` algorithm preserves relative widget
  widths on breakpoint changes. Cell height is 60 px; inter-cell margin is
  8 px. All geometry constants are centralised in `useGridManager.js` and
  mirrored to the `--mydash-cell-height` CSS custom property at init time.

### Security

- **SVG upload sanitisation** (REQ-RES-009..013): all SVG uploads are now
  passed through a server-side DOM whitelist sanitiser (`SvgSanitiser`)
  before persistence. Allowed elements (24) and attributes (50) are declared
  as conservative whitelists; `<script>`, `<foreignObject>`, `<iframe>`,
  `on*` event handlers, `javascript:` / `data:` href values, and CSS
  `expression()` / `url(data:)` constructs are stripped unconditionally.
  The parser uses `LIBXML_NONET` to block external entity and DTD fetches
  (XXE protection). Unparseable SVG or fully-stripped documents return
  HTTP 400 `{error: 'invalid_svg'}` and no file is written. The 5 MB size
  cap is measured against the sanitised bytes, not the original upload.
  Existing on-disk SVGs are not retroactively re-sanitised.

### Added

- **Widget right-click context menu in edit mode** (REQ-WDG-015..017, issue #36):
  right-clicking any widget placement in edit mode now opens a small popover at
  the cursor with three actions — **Edit** (reopens `AddWidgetModal` for the
  placement), **Remove** (calls the placement-delete path of REQ-WDG-005), and
  **Cancel** (no-op close). The popover is viewport-clamped so it stays fully
  on-screen near right and bottom edges (`min-width: 150 px`, `z-index: 10000`).
  View mode is untouched — right-click falls through to the browser's native
  context menu. Auto-close on outside click via a single document-level listener
  managed by `useGridManager.js` on mount/unmount (no listener leak). i18n:
  `nl_NL` + `en_US` for Edit, Remove, Cancel. Keyboard navigation (Up/Down/Enter/Esc)
  is deferred to a follow-up change.

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
  admin-tunable `launchpad.feed_item_cap` (default 50). New
  `oc_launchpad_feed_tokens` table with `UNIQUE(user_id)` enforces the
  one-token-per-user rotation invariant.

- **Unified add/edit modal** (`widget-add-edit-modal`): a single
  `AddWidgetModal.vue` now handles both "add a widget" and "edit a widget"
  flows. Per-widget edit dialogs are removed; the unified modal is driven by
  `widgetRegistry.js` — adding a new widget type only requires a registry
  entry. The `useWidgetForm` composable owns state management
  (`resetForm`, `loadEditingWidget`, `validate`, `assembleContent`).
  Modal closes on Cancel, backdrop click, and Esc without submitting
  (REQ-WDG-013). Type-switching resets form state with no cross-type field
  leakage (REQ-WDG-010). Per-type sub-forms expose `validate(): string[]`
  gating the action button (REQ-WDG-012). The toolbar dropdown and grid
  renderer both consult the same registry (REQ-WDG-014).

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
  are mirrored to the CSS custom property `--launchpad-cell-height` at
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
  builder and direct `loadState('launchpad', ...)` calls outside the reader.

## 0.1.0 - Initial Release

- Initial app structure
- Basic Nextcloud integration
