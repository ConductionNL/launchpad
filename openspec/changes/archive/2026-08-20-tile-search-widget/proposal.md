---
kind: code
---

# Proposal: tile-search-widget

## Summary

LaunchPad's dashboard quick-search bar currently renders as a fixed strip in
the runtime-shell page chrome (`WorkspaceApp.vue`), always present above the
grid whenever a dashboard is active. This change turns it into a placeable
dashboard widget instead: a `search` entry in the `dashboardWidgetRegistry`
that authors add to a dashboard's grid like any other widget, with its own
config form (placeholder text, per-widget fallback override). The shell-level
search strip is removed entirely — there is no fallback rendering and no
auto-migration onto existing dashboards; a dashboard simply has no search
until someone places the widget.

## Motivation

The quick-search bar (`tile-quick-search`, REQ-QSEARCH-001..004) was built as
permanent page chrome, which forces it onto every dashboard regardless of
whether that dashboard's author wants it, and gives them no control over its
placement, size, or visibility relative to other tiles. LaunchPad's widget
model already supports exactly this kind of per-dashboard, per-placement
choice for every other capability (clock, weather, iframe, live-data tile);
search is the one remaining shell-chrome feature that has not been converted.
Converting it removes special-cased shell layout code from `WorkspaceApp.vue`
and gives dashboard authors the same placement control over search that they
already have over every other widget type.

## Affected Projects

- [x] Project: `launchpad` — quick-search becomes a placeable dashboard
  widget type (`search`) instead of fixed shell chrome; no other
  apps-extra project is touched.

## Scope

### In Scope

- Register a new `search` dashboard-widget type in
  `src/constants/widgetRegistry.js`, following the existing
  `clock`/`weather`/`livetile`/`iframe` LaunchPad-local registration pattern.
- New renderer `src/components/Widgets/Renderers/SearchWidget.vue` wrapping
  the existing `RuntimeShellSearch.vue` combobox and owning the host-side
  wiring (dim non-matching tiles, activate a result, resolve the no-match
  fallback, return focus to the grid) that currently lives in
  `WorkspaceApp.vue`.
- New composable `src/composables/useTileSearchHost.js` extracting that
  host-side wiring out of `WorkspaceApp.vue` so the renderer can own it
  without duplicating logic, preserving the two launchpad#95 regression
  fixes (string-normalised id comparison for dimming; `String(...)`-cast
  placement id before matching for activation) verbatim.
- New config form `src/components/Widgets/Renderers/SearchWidgetForm.vue`
  with two fields: placeholder text, and a per-widget fallback-behaviour
  override (inherit admin setting / none / unified-search / web-search URL
  template). An empty override inherits the existing admin
  `quicksearch_fallback_target` setting.
- Optional `placeholder` prop on `RuntimeShellSearch.vue` overriding its
  internal default placeholder text.
- Removal of the search region, its handlers/computeds, and its CSS from
  `WorkspaceApp.vue`; the shell-level search strip is deleted entirely, with
  no fallback rendering and no auto-migration of existing dashboards.
- Keyboard shortcuts (`/`, `Ctrl+K`) move with the widget: explicit
  documented behaviour for a dashboard with zero search widgets (shortcut
  does nothing) and two-or-more search widgets (first-mounted instance wins,
  guarded so a second instance does not also steal focus).
- A spec delta against `openspec/specs/tile-quick-search/spec.md`:
  REQ-QSEARCH-001 (bar is a placed widget, not shell chrome) and
  REQ-QSEARCH-004 (fallback resolution gains a per-widget override layer)
  MODIFIED; new ADDED requirements for widget placement/configuration and
  the zero/multiple-instance keyboard-shortcut behaviour; REQ-QSEARCH-002
  and REQ-QSEARCH-003 survive with unchanged behaviour.
- Two updated code comments in `src/views/Views.vue` that currently describe
  the bar as shell-mounted.

### Out of Scope

- No backend/PHP change of any kind. The admin `quicksearch_fallback_target`
  setting (`lib/Service/AdminSettingsService.php`,
  `lib/Db/AdminSetting.php`, `lib/Controller/PageController.php`,
  `lib/Service/InitialStateBuilder.php`) is unchanged and becomes the
  inherited default for the new per-widget override.
- No automatic migration of existing dashboards onto the new widget — a
  dashboard that previously showed the shell search bar shows nothing until
  an author explicitly adds the `search` widget. This is an accepted,
  intentional behaviour change, not a gap to close later.
- No change to the filter/rank/fallback-decision logic in
  `src/composables/useTileSearch.js` — it is reused as-is by the new
  renderer.
- No OpenRegister schema, manifest, or catalog metadata changes beyond the
  widget-type registration itself (this is a client-side registry entry,
  not an `x-openregister-widgets` schema concern — see design.md).

## Approach

`RuntimeShellSearch.vue` already isolates all combobox/keyboard/DOM logic
behind a narrow `items`/`fallbackTarget` prop and `open`/`filter`/`fallback`/
`clear` event contract, and `useTileSearch.js` already isolates all filter/
rank/fallback-decision logic — neither needs to change in substance. The work
is almost entirely about relocating the *host* responsibilities
(`WorkspaceApp.vue`'s `tileSearchLabel`, `onSearchOpen`, `onSearchFilter`,
`onSearchFallback`, `onSearchClear`, `applySearchDimming`,
`activateSearchResult`, `focusGrid`, `searchableTiles`,
`quicksearchFallbackTarget`) into a new widget renderer that follows the
existing `widgetRegistry.js` registration pattern used by `clock`/`weather`/
`iframe`. The renderer reads `widgetPlacements` from the Pinia
`useDashboardStore`, `availableWidgets` from `useWidgetStore`, and the admin
fallback default via the existing `quicksearchFallbackTarget` Vue `inject`,
so no new data plumbing is required. Full technical detail (dimming-query
rescoping from `WorkspaceApp`'s `$el` to `#launchpad-main-content`, the
first-mounted-instance keyboard-shortcut guard, the config-form field shapes)
is in design.md.

## New Dependencies

None.

## Impact

- **Frontend widget registry**: `src/constants/widgetRegistry.js` gains one
  new registration (`search`).
- **Frontend components**: new `SearchWidget.vue` renderer, new
  `SearchWidgetForm.vue` config form, new `useTileSearchHost.js` composable;
  `RuntimeShellSearch.vue` gains an optional prop; `WorkspaceApp.vue` loses
  the search region and its supporting code; `Views.vue` gets two comment
  updates.
- **No backend/API impact** — no controller, service, mapper, route, or
  migration changes.
- **No dashboard data-shape change** — placements of the new `search` type
  use the same `WidgetPlacement` entity and `content` JSON blob every other
  widget type already uses.

## Cross-Project Dependencies

None. This is a self-contained LaunchPad frontend change.

## Risks

### Risk 1: The two launchpad#95 regression fixes are lost during extraction
**Severity:** High — **Mitigation:** Both fixes (the `String(id)` dimming
comparison and the `String(placement.id ?? '')` activation cast) are called
out explicitly in design.md as named regression risks, carried into the
extracted `useTileSearchHost.js` verbatim, and covered by named test cases in
tasks.md / test-plan.md rather than left to incidental coverage.

### Risk 2: Removing the shell search bar with no fallback breaks existing
dashboards' muscle memory
**Severity:** Medium — **Mitigation:** This is an accepted, deliberate
product decision (see Scope → Out of Scope) rather than an oversight; no
mitigation beyond the spec explicitly documenting the new zero-instance
behaviour ("shortcut does nothing") so it is not mistaken for a bug during
review or QA.

### Risk 3: Two search widgets placed on one dashboard both try to own the
`/` and `Ctrl+K` shortcuts
**Severity:** Low — **Mitigation:** An explicit first-mounted-instance-wins
guard is specified (design.md) and covered by a dedicated ADDED requirement
scenario in the spec delta.

## Rollback Strategy

The change is additive at the registry level (a new widget type) and
subtractive at the shell level (removing the fixed bar). Reverting is a
straight `git revert` of the change's commits: `WorkspaceApp.vue` regains its
search region/handlers, the new renderer/form/composable files are removed,
and the `search` registry entry is deregistered. No data migration exists to
unwind — no placement rows are created or altered by the revert since
existing dashboards carry no `search`-type placements until an author
explicitly adds one.

## Open Questions

None — the shape of this change (widget type, removal of shell chrome, two
config fields, per-instance keyboard-shortcut guard) was agreed with the
requester before this proposal was drafted.
