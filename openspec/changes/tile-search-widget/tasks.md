# Tasks: tile-search-widget

## Implementation Tasks

### Task 1: Register the `search` widget type in the registry
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-005-configure-the-search-widgets-placeholder-and-fallback-override`
- **files**: `src/constants/widgetRegistry.js`
- **acceptance_criteria**:
  - GIVEN `widgetRegistry.js` loads WHEN `registerDashboardWidget('search', {...})` runs THEN `'search'` appears in `listWidgetTypes()` with `defaultContent: {placeholder: '', fallbackTarget: ''}`, `displayName: 'Search'`, `icon: 'Magnify'`
  - GIVEN an existing placement of type `search` WHEN `getWidgetTypeEntry('search')` is called THEN it resolves the `SearchWidget` renderer and `SearchWidgetForm` form (both created in later tasks; stub imports here, wired fully once Tasks 4/5 land)
- [ ] Implement
- [ ] Test

### Task 2: Extract host wiring into `useTileSearchHost.js`
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-001-render-the-quick-search-bar-as-a-placed-widget-and-focus-it-with-the-keyboard`
- **files**: `src/composables/useTileSearchHost.js`, `src/composables/__tests__/useTileSearchHost.spec.js`
- **acceptance_criteria**:
  - GIVEN a placement WHEN `tileSearchLabel(placement)` runs THEN it returns `tileTitle` for tile placements and `customTitle || widget.title || 'Widget'` otherwise, matching `WidgetWrapper.vue`'s `widgetTitle`
  - GIVEN `item.placement.id` is an integer WHEN `activateSearchResult(item)` runs THEN no `TypeError` is thrown and the correct grid item (resolved under `#launchpad-main-content` via a selector built from the STORE id, never read back off the DOM) is scrolled into view and activated (launchpad#95 fix 2: `String(... ?? '')` cast, never a raw `.replace()` on a number)
  - GIVEN Escape is pressed WHEN `focusGrid()` runs THEN `#launchpad-main-content` receives focus with `preventScroll`
  - The composable performs NO dimming and reads NO `data-*` attribute — dimming is store state (Task 7), per ADR-004
- [ ] Implement
- [ ] Test

### Task 3: Add `placeholder` prop and the keyboard-shortcut singleton guard to `RuntimeShellSearch.vue`
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-006-keyboard-shortcut-behaviour-with-zero-or-multiple-search-widgets`
- **files**: `src/components/RuntimeShellSearch.vue`, `src/components/__tests__/RuntimeShellSearch.spec.js`
- **acceptance_criteria**:
  - GIVEN a `placeholder` prop is passed WHEN the component renders THEN the input's `placeholder` attribute uses that value instead of the built-in default text
  - GIVEN two `RuntimeShellSearch` instances are mounted on the same page WHEN `/` or `Ctrl+K` is pressed THEN only the first-mounted instance's input receives focus
  - GIVEN the first-mounted instance unmounts WHEN `/` or `Ctrl+K` is pressed again THEN the remaining instance is promoted and receives focus
- [ ] Implement
- [ ] Test

### Task 4: Create the `SearchWidget.vue` renderer
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-004-route-a-no-match-query-to-a-configured-fallback`
- **files**: `src/components/Widgets/Renderers/SearchWidget.vue`, `src/components/Widgets/Renderers/__tests__/SearchWidget.spec.js`
- **acceptance_criteria**:
  - GIVEN a `search` widget placement renders WHEN mounted THEN it reads `widgetPlacements` (`useDashboardStore`) and `availableWidgets` (`useWidgetStore`) and resolves searchable items via `useTileSearchHost`
  - GIVEN the search bar emits `filter` / `clear` WHEN handled THEN the widget calls the `tileSearch` store's `setMatches(ids)` / `clear()` and touches no tile elements itself
  - GIVEN the widget unmounts (deleted from the dashboard) WHEN teardown runs THEN it calls `clear()` so no tile is left dimmed by a widget that no longer exists
  - GIVEN `content.fallbackTarget` is empty WHEN a no-match Enter is pressed THEN the injected `quicksearchFallbackTarget` admin default is used
  - GIVEN `content.fallbackTarget` is `'none'` and the admin default is `'unified-search'` WHEN a no-match Enter is pressed THEN no navigation occurs (widget override wins)
- [ ] Implement
- [ ] Test

### Task 5: Create the `SearchWidgetForm.vue` config form
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-005-configure-the-search-widgets-placeholder-and-fallback-override`
- **files**: `src/components/Widgets/Renderers/SearchWidgetForm.vue`, `src/components/Widgets/Renderers/__tests__/SearchWidgetForm.spec.js`
- **acceptance_criteria**:
  - GIVEN the author sets the placeholder field to a non-empty string WHEN saved THEN `content.placeholder` persists that string
  - GIVEN the author selects "web-search URL template" and enters a template that is not `https` or lacks `{query}` WHEN `validate()` runs THEN it returns a validation error (reusing `isValidFallbackTemplate()` from `useTileSearch.js`)
  - GIVEN the author selects "inherit admin setting" WHEN saved THEN `content.fallbackTarget` persists as `''`
- [ ] Implement
- [ ] Test

### Task 6: Remove the shell search region from `WorkspaceApp.vue`
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-001-render-the-quick-search-bar-as-a-placed-widget-and-focus-it-with-the-keyboard`
- **files**: `src/views/WorkspaceApp.vue`, `src/views/__tests__/WorkspaceApp.spec.js`
- **acceptance_criteria**:
  - GIVEN `WorkspaceApp.vue` renders an active dashboard WHEN inspected THEN no `RuntimeShellSearch` import, search region markup, handlers (`tileSearchLabel`, `onSearchOpen`, `onSearchFilter`, `onSearchFallback`, `onSearchClear`, `applySearchDimming`, `activateSearchResult`, `focusGrid`), computeds (`searchableTiles`, `quicksearchFallbackTarget`), or search-bar CSS remain
  - GIVEN the existing `tile-quick-search: RuntimeShellSearch wiring` describe block (~line 234) WHEN the test suite runs THEN it has been removed and the remaining suite passes
- [ ] Implement
- [ ] Test

### Task 7: Add the `tileSearch` store and make dimming reactive in `Views.vue`
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-002-filter-the-current-dashboards-tiles-live`
- **files**: `src/stores/tileSearch.js`, `src/stores/__tests__/tileSearch.spec.js`, `src/views/Views.vue`
- **acceptance_criteria**:
  - GIVEN `matchIds` is `null` WHEN `isDimmed(anyId)` is read THEN it returns `false` (no active query undims everything)
  - GIVEN `setMatches([7])` ran WHEN `isDimmed(7)` and `isDimmed(9)` are read THEN they return `false` and `true` respectively, and `isDimmed('7')` also returns `false` (both sides normalised via `String(...)`, so the launchpad#95 integer/string mismatch cannot recur)
  - GIVEN `setMatches([])` ran WHEN any tile is checked THEN it is dimmed (empty match set dims everything)
  - GIVEN `Views.vue` renders a grid item WHEN the store's match set changes THEN `.launchpad-grid-item--dimmed` is toggled by the reactive `:class` binding, with NO imperative `classList` call and NO `getAttribute('data-placement-id')` read anywhere (ADR-004)
  - GIVEN the `data-placement-id` comment (~lines 148-151) and the `.launchpad-grid-item--dimmed` CSS comment (~lines 2038-2041) WHEN read THEN both describe the current design: a placed widget writing store state, not shell chrome toggling classes imperatively
- [ ] Implement
- [ ] Test

### Task 8: Update the quick-search e2e suite to place the widget first
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-006-keyboard-shortcut-behaviour-with-zero-or-multiple-search-widgets`
- **files**: `tests/e2e/tile-quick-search.spec.ts`
- **acceptance_criteria**:
  - GIVEN the e2e suite runs WHEN it exercises quick-search behaviour THEN it first adds a `search` widget via the Add Widget flow before interacting with it
  - GIVEN the suite runs THEN every pre-existing REQ-QSEARCH-002/003 scenario (live filter, dimming, keyboard navigation, Esc-returns-focus) still passes with unchanged behaviour
- [ ] Implement
- [ ] Test

## Quality checklist

<!-- These are reminders for the builder, not tracked checkboxes.
     Keeping them as plain text avoids inflating the Hydra cap count. -->

- Every new/changed method (renderer, form, composable, and the touched
  `RuntimeShellSearch.vue`/`WorkspaceApp.vue`/`Views.vue` methods) carries
  an `@spec openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#...`
  annotation per the repo's spec-coverage quality gate
- All new/changed frontend logic covered by Vitest unit tests (`src/**/__tests__/`)
- No backend/PHP files touched — PHPUnit, Newman/Postman, and migration
  tasks are N/A for this change (see design.md → Nextcloud Integration /
  Seed Data)
- UI changes covered by the updated Playwright e2e suite
  (`tests/e2e/tile-quick-search.spec.ts`)
- All tests pass (`npm run test:unit`, `npm run test:e2e`)
- Feature documentation updated in `docs/features/widgets.md` to list
  `search` alongside the other LaunchPad-local widget types (ADR-010)
- All new user-facing strings (placeholder field label, fallback-override
  option labels, widget display name "Search") routed through
  `t('launchpad', …)`; Dutch (`nl_NL`) and English (`en_US`) translation
  strings added (ADR-005/ADR-007)
- `openspec validate` passes
