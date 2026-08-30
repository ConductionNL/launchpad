## ADDED Requirements

### Requirement: REQ-QSEARCH-001 Focus the quick-search bar with the keyboard

The system MUST render a quick-search bar in the dashboard runtime shell and MUST let the user focus it with the keyboard without touching the mouse.

#### Scenario: Search bar is present and labelled
- GIVEN the user is viewing a dashboard in the LaunchPad runtime shell
- WHEN the runtime shell renders
- THEN a quick-search bar MUST appear above the tile grid
- AND the input MUST be wrapped in `role="search"` and carry an accessible label
- AND the bar MUST be reachable in the normal tab order with a visible focus indicator

#### Scenario: Slash focuses the bar
- GIVEN the user is viewing a dashboard and focus is NOT in a text input
- WHEN the user presses `/`
- THEN the quick-search input MUST receive focus
- AND the `/` character MUST NOT be inserted into any other field

#### Scenario: Ctrl+K focuses the bar
- GIVEN the user is viewing a dashboard
- WHEN the user presses `Ctrl+K` (or `Cmd+K` on macOS)
- THEN the quick-search input MUST receive focus
- AND the browser's default action for the shortcut MUST be prevented

### Requirement: REQ-QSEARCH-002 Filter the current dashboard's tiles live

The system MUST filter the current dashboard's tiles/widgets by label as the user types, entirely client-side, with no backend request and no stored query.

#### Scenario: Typing filters tiles by label
- GIVEN a dashboard with tiles labelled "Zaaksysteem", "Zaakbrowser" and "Verlof"
- WHEN the user types `zaak` into the quick-search bar
- THEN only tiles whose label matches `zaak` (case-insensitive) MUST remain highlighted as matches
- AND non-matching tiles MUST be visually de-emphasised, not removed from the grid layout
- AND no backend/API request MUST be made to perform the filter

#### Scenario: Ranking order
- GIVEN tiles labelled "Verlof aanvragen" and "Overzicht verlof"
- WHEN the user types `verlof`
- THEN a tile whose label starts with the query MUST rank above one that matches mid-string
- AND a substring match MUST rank above a non-contiguous subsequence match

#### Scenario: No query stored
- GIVEN the user has typed a query
- WHEN the query is processed
- THEN the query MUST NOT be persisted to any backend store, cache, or history

### Requirement: REQ-QSEARCH-003 Navigate and open results with the keyboard

The system MUST let the user move a selection cursor over the matching tiles and open the selected tile, all from the keyboard, WCAG AA compliant.

#### Scenario: Arrow keys move the selection
- GIVEN the quick-search bar is focused with two or more matching tiles
- WHEN the user presses ArrowDown then ArrowUp
- THEN the active-selection cursor MUST move to the next then the previous match
- AND the active match MUST be exposed via `aria-activedescendant` on the input and be visibly indicated by more than colour alone

#### Scenario: Enter opens the selected tile
- GIVEN a match is selected in the quick-search results
- WHEN the user presses Enter
- THEN the runtime shell MUST activate that tile, honouring its configured link target (same-tab / new-tab)

#### Scenario: Escape clears and returns focus
- GIVEN the quick-search bar has a query and focus
- WHEN the user presses Esc
- THEN the query MUST be cleared, all tiles MUST return to their normal (undimmed) state
- AND focus MUST return to the tile grid

### Requirement: REQ-QSEARCH-004 Route a no-match query to a configured fallback

The system MUST, when Enter is pressed with zero matching tiles, route the query to the fallback target configured in `quicksearch_fallback_target`.

#### Scenario: Unified-search fallback
- GIVEN `quicksearch_fallback_target` is set to `unified-search`
- WHEN the user presses Enter on a query with no matching tiles
- THEN the system MUST hand the query to Nextcloud unified search via the existing `nc-unified-search-integration`
- AND MUST NOT navigate away from the dashboard on its own beyond what the unified-search integration does

#### Scenario: Web-search fallback
- GIVEN `quicksearch_fallback_target` is a validated web-search URL template containing `{query}`
- WHEN the user presses Enter on a query with no matching tiles
- THEN the system MUST open the template with the URL-encoded query substituted for `{query}`

#### Scenario: No fallback configured
- GIVEN `quicksearch_fallback_target` is `none`
- WHEN the user presses Enter on a query with no matching tiles
- THEN the system MUST take no navigation action and MUST show an accessible "no results" message

#### Scenario: Fallback template validation
- GIVEN an admin sets a web-search URL template
- WHEN the template is not `https` or does not contain the `{query}` placeholder
- THEN the system MUST reject it at save time with a validation error
