# Test Plan: tile-search-widget

## Test Cases

### TC-1: Search widget renders inside its grid cell, labelled and reachable
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-001-render-the-quick-search-bar-as-a-placed-widget-and-focus-it-with-the-keyboard`
- **type**: functional
- **persona**: n/a
- **preconditions**: An admin/user has placed a `search` widget on a dashboard grid
- **steps**: Load the dashboard; inspect the widget's rendered markup and tab order
- **expected result**: A quick-search bar appears inside the widget's grid cell; the input is wrapped in `role="search"`, carries an accessible label, and is reachable via Tab with a visible focus indicator
- **test command**: `/test-functional`

### TC-2: No search widget placed renders no bar anywhere
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-001-render-the-quick-search-bar-as-a-placed-widget-and-focus-it-with-the-keyboard`
- **type**: regression
- **persona**: n/a
- **preconditions**: A dashboard with zero `search` widget placements (any pre-existing dashboard, unmigrated)
- **steps**: Load the dashboard
- **expected result**: No quick-search bar renders as page chrome above the grid or anywhere else; `WorkspaceApp.vue`'s search region is fully removed
- **test command**: `/test-regression`

### TC-3: Slash focuses the placed widget's input
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-001-render-the-quick-search-bar-as-a-placed-widget-and-focus-it-with-the-keyboard`
- **type**: accessibility
- **persona**: n/a
- **preconditions**: Exactly one `search` widget placed; focus is not in a text input
- **steps**: Press `/`
- **expected result**: The widget's input receives focus; the `/` character is not inserted anywhere
- **test command**: `/test-accessibility`

### TC-4: Ctrl+K focuses the placed widget's input
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-001-render-the-quick-search-bar-as-a-placed-widget-and-focus-it-with-the-keyboard`
- **type**: accessibility
- **persona**: n/a
- **preconditions**: Exactly one `search` widget placed
- **steps**: Press `Ctrl+K` (`Cmd+K` on macOS)
- **expected result**: The widget's input receives focus; the browser default action is prevented
- **test command**: `/test-accessibility`

### TC-5: Unified-search fallback fires via the resolved target
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-004-route-a-no-match-query-to-a-configured-fallback`
- **type**: functional
- **persona**: n/a
- **preconditions**: Resolved fallback target (widget override or admin default) is `unified-search`
- **steps**: Type a query matching no tiles; press Enter
- **expected result**: The query is handed to Nextcloud unified search via `nc-unified-search-integration`; no other navigation occurs
- **test command**: `/test-functional`

### TC-6: Web-search fallback opens the templated URL
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-004-route-a-no-match-query-to-a-configured-fallback`
- **type**: functional
- **persona**: n/a
- **preconditions**: Resolved fallback target is a validated `https` URL template containing `{query}`
- **steps**: Type a query matching no tiles; press Enter
- **expected result**: The template opens with the URL-encoded query substituted for `{query}`
- **test command**: `/test-functional`

### TC-7: No-fallback shows the accessible "no results" message
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-004-route-a-no-match-query-to-a-configured-fallback`
- **type**: accessibility
- **persona**: n/a
- **preconditions**: Resolved fallback target is `none`
- **steps**: Type a query matching no tiles; press Enter
- **expected result**: No navigation occurs; the accessible "no results" message is shown (sr-only live region + visible affordance)
- **test command**: `/test-accessibility`

### TC-8: Admin-level fallback template validation is unchanged
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-004-route-a-no-match-query-to-a-configured-fallback`
- **type**: regression
- **persona**: n/a
- **preconditions**: Admin Settings → LaunchPad → quick-search fallback field
- **steps**: Enter a template that is not `https` or lacks `{query}`; attempt to save
- **expected result**: Save is rejected with a validation error (unchanged server-side behaviour)
- **test command**: `/test-regression`

### TC-9: Per-widget override takes precedence over the admin default
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-004-route-a-no-match-query-to-a-configured-fallback`
- **type**: functional
- **persona**: n/a
- **preconditions**: Admin `quicksearch_fallback_target` = `unified-search`; the placed widget's `content.fallbackTarget` = `none`
- **steps**: Type a query matching no tiles on that widget; press Enter
- **expected result**: No navigation occurs — the widget's own override wins over the admin default
- **test command**: `/test-functional`

### TC-10: Empty per-widget override inherits the admin default
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-004-route-a-no-match-query-to-a-configured-fallback`
- **type**: functional
- **persona**: n/a
- **preconditions**: Admin `quicksearch_fallback_target` = a validated web-search template; widget's `content.fallbackTarget` = `''` (unset)
- **steps**: Type a query matching no tiles; press Enter
- **expected result**: The admin-configured template resolves exactly as it would have for the old shell bar
- **test command**: `/test-functional`

### TC-11: Default content on add matches the built-in bar's behaviour
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-005-configure-the-search-widgets-placeholder-and-fallback-override`
- **type**: functional
- **persona**: n/a
- **preconditions**: Dashboard author opens Add Widget and picks "Search"
- **steps**: Add the widget without touching the config form; save
- **expected result**: Persisted content is `{placeholder: '', fallbackTarget: ''}`; the rendered input shows the built-in placeholder text; no-match Enter inherits the admin default
- **test command**: `/test-functional`

### TC-12: Configuring a custom placeholder
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-005-configure-the-search-widgets-placeholder-and-fallback-override`
- **type**: functional
- **persona**: n/a
- **preconditions**: Search widget config form open
- **steps**: Set placeholder to a custom string; save
- **expected result**: The rendered widget's input `placeholder` attribute shows the custom string
- **test command**: `/test-functional`

### TC-13: Configuring a per-widget fallback override end to end
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-005-configure-the-search-widgets-placeholder-and-fallback-override`
- **type**: functional
- **persona**: n/a
- **preconditions**: Search widget config form open
- **steps**: Select a non-inherit fallback option (e.g. `none`); save; trigger a no-match Enter on the widget
- **expected result**: `content.fallbackTarget` persists the selected value; the resulting no-match behaviour matches TC-9's per-widget-override resolution
- **test command**: `/test-functional`

### TC-14: Fallback override template validation in the widget config form
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-005-configure-the-search-widgets-placeholder-and-fallback-override`
- **type**: functional
- **persona**: n/a
- **preconditions**: Search widget config form, "web-search URL template" option selected
- **steps**: Enter a template that is not `https` or lacks `{query}`; attempt to save
- **expected result**: The form rejects it at save time with a validation error, mirroring TC-8's admin-level check
- **test command**: `/test-functional`

### TC-15: Removing the only search widget removes search from the dashboard
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-005-configure-the-search-widgets-placeholder-and-fallback-override`
- **type**: regression
- **persona**: n/a
- **preconditions**: A dashboard with exactly one `search` widget placed
- **steps**: Delete the widget placement
- **expected result**: No quick-search bar remains; `/` and `Ctrl+K` behave per TC-16 ("no search widget placed")
- **test command**: `/test-regression`

### TC-16: No search widget placed — shortcuts are inert
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-006-keyboard-shortcut-behaviour-with-zero-or-multiple-search-widgets`
- **type**: regression
- **persona**: n/a
- **preconditions**: Active dashboard has zero `search` widget placements
- **steps**: Press `/` (outside a text input) and separately `Ctrl+K`
- **expected result**: No element receives programmatic focus as a result of either press; the keypress is not intercepted (normal page behaviour proceeds)
- **test command**: `/test-regression`

### TC-17: Exactly one search widget — shortcut focuses it (baseline)
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-006-keyboard-shortcut-behaviour-with-zero-or-multiple-search-widgets`
- **type**: functional
- **persona**: n/a
- **preconditions**: Exactly one `search` widget placed
- **steps**: Press `/` then, separately, `Ctrl+K`
- **expected result**: The widget's input receives focus both times (same as TC-3/TC-4)
- **test command**: `/test-functional`

### TC-18: Two or more search widgets — only the first-mounted instance claims the shortcut
- **spec_ref**: `openspec/changes/tile-search-widget/specs/tile-quick-search/spec.md#requirement-req-qsearch-006-keyboard-shortcut-behaviour-with-zero-or-multiple-search-widgets`
- **type**: functional
- **persona**: n/a
- **preconditions**: Two `search` widgets placed on the same dashboard
- **steps**: Press `/`; observe which input received focus. Then delete the first-mounted widget and press `/` again
- **expected result**: Only the first-mounted instance's input receives focus on the first press; after the first instance is removed, the remaining instance is promoted and receives focus on the second press. No press ever focuses more than one input
- **test command**: `/test-functional`

### TC-19 (regression, unit-level): Dimming id comparison cannot regress
- **spec_ref**: `openspec/changes/tile-search-widget/design.md#3-dimming-becomes-reactive-store-state-the-dom-is-never-read-for-it` (launchpad#95 regression risk 1)
- **type**: regression
- **persona**: n/a
- **preconditions**: `src/stores/tileSearch.js` holds `matchIds`; `isDimmed(placementId)` is the only dimming decision point. No `getAttribute('data-placement-id')` read exists anywhere in the change.
- **steps**: Unit test `tileSearch.spec.js` calling `setMatches([7])` then reading `isDimmed(7)`, `isDimmed('7')` and `isDimmed(9)`; plus a grep-style assertion in review that no `getAttribute('data-` read was introduced
- **expected result**: `isDimmed(7)` and `isDimmed('7')` are both `false`, `isDimmed(9)` is `true`. The original bug (integer store id vs string DOM attribute compared with SameValueZero) is now unreachable by construction, since both sides originate in the store; the `String(...)` normalisation is retained as defence and this test fails if it is dropped.
- **test command**: `/test-regression` (unit coverage exercised by the existing `npm run test:unit` vitest run)

### TC-20 (regression, unit-level): Activation id cast survives extraction
- **spec_ref**: `openspec/changes/tile-search-widget/design.md#decisions` (launchpad#95 regression risk 1)
- **type**: regression
- **persona**: n/a
- **preconditions**: `useTileSearchHost.js`'s `activateSearchResult` called with an integer `placement.id`
- **steps**: Unit test asserting `String(item?.placement?.id ?? '')` is used (not a bare `.replace()` call on a possibly-numeric value) and that pressing Enter on a result with an integer id does not throw
- **expected result**: No `TypeError`; the correct grid item is scrolled into view and its link is clicked
- **test command**: `/test-regression` (unit coverage exercised by the existing `npm run test:unit` vitest run)

## Coverage Summary

| Requirement | Covered by | Status |
|---|---|---|
| REQ-QSEARCH-001 (MODIFIED) | TC-1, TC-2, TC-3, TC-4 | Covered |
| REQ-QSEARCH-002 (unchanged behaviour) | Existing `useTileSearch.spec.js` / `RuntimeShellSearch.spec.js` coverage, unaffected by this change | Covered (pre-existing) |
| REQ-QSEARCH-003 (unchanged behaviour) | Existing `RuntimeShellSearch.spec.js` coverage, unaffected by this change | Covered (pre-existing) |
| REQ-QSEARCH-004 (MODIFIED) | TC-5, TC-6, TC-7, TC-8, TC-9, TC-10 | Covered |
| REQ-QSEARCH-005 (ADDED) | TC-11, TC-12, TC-13, TC-14, TC-15 | Covered |
| REQ-QSEARCH-006 (ADDED) | TC-16, TC-17, TC-18 | Covered |
| launchpad#95 regression risks (design.md) | TC-19, TC-20 | Covered |

## Out of Scope

- Cross-browser/cross-device manual testing beyond the standard Playwright
  matrix already used for `tests/e2e/tile-quick-search.spec.ts` — no new
  browser targets are introduced by this change.
- Performance testing — the widget performs the same in-memory filtering as
  the shell bar did; no new network calls or heavier computation are
  introduced.
- Security testing — no new endpoint, no new auth surface (see design.md →
  Security Considerations).
