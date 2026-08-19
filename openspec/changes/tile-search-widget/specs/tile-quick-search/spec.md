---
capability: tile-quick-search
delta: true
status: draft
---

# Tile Quick Search — Delta from change `tile-search-widget`

## MODIFIED Requirements

### Requirement: REQ-QSEARCH-001 Render the quick-search bar as a placed widget and focus it with the keyboard

The system MUST render the quick-search bar as a dashboard widget placement
(widget type `search`) that a dashboard author explicitly adds to the grid,
rather than as always-present page chrome. When at least one `search`
widget is placed on the active dashboard, the system MUST let the user focus
its input with the keyboard without touching the mouse. When no `search`
widget is placed, no quick-search bar renders at all — there is no shell-
level fallback bar and no automatic migration of a dashboard onto the
widget.

This requirement previously mandated an always-rendered shell-chrome bar
above the tile grid on every dashboard; that shell-level rendering is
removed by this change. The widget-placement/configuration mechanics are
specified separately in REQ-QSEARCH-005; the zero/multiple-instance keyboard
behaviour is specified in REQ-QSEARCH-006.

#### Scenario: Search widget is present and labelled

- GIVEN a dashboard author has placed a `search` widget on the active dashboard
- WHEN the dashboard renders
- THEN a quick-search bar MUST appear inside that widget's grid cell
- AND the input MUST be wrapped in `role="search"` and carry an accessible label
- AND the bar MUST be reachable in the normal tab order with a visible focus indicator

#### Scenario: No search widget placed renders no bar

- GIVEN a dashboard has no `search` widget placement
- WHEN the dashboard renders
- THEN no quick-search bar MUST appear anywhere on the page, including as page chrome above the grid

#### Scenario: Slash focuses the bar

- GIVEN the user is viewing a dashboard with a placed `search` widget and focus is NOT in a text input
- WHEN the user presses `/`
- THEN the quick-search input MUST receive focus
- AND the `/` character MUST NOT be inserted into any other field

#### Scenario: Ctrl+K focuses the bar

- GIVEN the user is viewing a dashboard with a placed `search` widget
- WHEN the user presses `Ctrl+K` (or `Cmd+K` on macOS)
- THEN the quick-search input MUST receive focus
- AND the browser's default action for the shortcut MUST be prevented

### Requirement: REQ-QSEARCH-004 Route a no-match query to a configured fallback

The system MUST, when Enter is pressed with zero matching tiles, route the
query to a resolved fallback target. The resolved fallback target MUST be
the placed `search` widget's own per-widget fallback override
(`content.fallbackTarget`) when that value is non-empty; otherwise it MUST
be the admin-configured `quicksearch_fallback_target` setting. This layering
lets an individual widget instance opt out of, or replace, the site-wide
default without an admin-level change.

#### Scenario: Unified-search fallback

- GIVEN the resolved fallback target is `unified-search`
- WHEN the user presses Enter on a query with no matching tiles
- THEN the system MUST hand the query to Nextcloud unified search via the existing `nc-unified-search-integration`
- AND MUST NOT navigate away from the dashboard on its own beyond what the unified-search integration does

#### Scenario: Web-search fallback

- GIVEN the resolved fallback target is a validated web-search URL template containing `{query}`
- WHEN the user presses Enter on a query with no matching tiles
- THEN the system MUST open the template with the URL-encoded query substituted for `{query}`

#### Scenario: No fallback configured

- GIVEN the resolved fallback target is `none`
- WHEN the user presses Enter on a query with no matching tiles
- THEN the system MUST take no navigation action and MUST show an accessible "no results" message

#### Scenario: Fallback template validation

- GIVEN an admin sets a web-search URL template as the admin-level `quicksearch_fallback_target`
- WHEN the template is not `https` or does not contain the `{query}` placeholder
- THEN the system MUST reject it at save time with a validation error

#### Scenario: Per-widget override takes precedence over the admin default

- GIVEN the admin `quicksearch_fallback_target` is `unified-search`
- AND a placed `search` widget's `content.fallbackTarget` is set to `none`
- WHEN the user presses Enter on that widget with a query with no matching tiles
- THEN the system MUST take no navigation action (the widget's own override wins)

#### Scenario: Empty per-widget override inherits the admin default

- GIVEN the admin `quicksearch_fallback_target` is a validated web-search URL template
- AND a placed `search` widget's `content.fallbackTarget` is empty (unset)
- WHEN the user presses Enter on that widget with a query with no matching tiles
- THEN the system MUST resolve the fallback exactly as the admin-configured template dictates

## ADDED Requirements

### Requirement: REQ-QSEARCH-005 Configure the search widget's placeholder and fallback override

The system MUST let a dashboard author configure a placed `search` widget's
placeholder text and its per-widget no-match fallback override through the
widget's own configuration form, following the same add/edit-widget pattern
used by every other LaunchPad-local widget type (clock, weather, iframe,
live-data tile).

#### Scenario: Default content on add

- GIVEN a dashboard author adds a new `search` widget via the Add Widget flow
- WHEN the placement is created
- THEN its persisted `content` MUST default to `{placeholder: '', fallbackTarget: ''}`
- AND an empty `placeholder` MUST render the widget's existing built-in placeholder text
- AND an empty `fallbackTarget` MUST inherit the admin `quicksearch_fallback_target` setting (REQ-QSEARCH-004)

#### Scenario: Configuring a custom placeholder

- GIVEN a dashboard author sets the widget's placeholder field to a non-empty string in the config form
- WHEN the change is saved
- THEN the rendered widget's search input `placeholder` attribute MUST show that text instead of the built-in default

#### Scenario: Configuring a per-widget fallback override

- GIVEN a dashboard author selects a fallback override value (none / unified-search / a validated web-search URL template) other than "inherit" in the config form
- WHEN the change is saved
- THEN the widget's `content.fallbackTarget` MUST persist that value
- AND subsequent no-match Enter presses on that widget MUST resolve the fallback per REQ-QSEARCH-004's per-widget-override scenario

#### Scenario: Fallback override template validation

- GIVEN a dashboard author enters a web-search URL template as the widget's fallback override
- WHEN the template is not `https` or does not contain the `{query}` placeholder
- THEN the config form MUST reject it at save time with a validation error, mirroring the admin-level validation in REQ-QSEARCH-004

#### Scenario: Removing the widget removes search from the dashboard

- GIVEN a dashboard's only `search` widget placement is deleted
- WHEN the dashboard re-renders
- THEN no quick-search bar MUST be present
- AND the `/` and `Ctrl+K` shortcuts MUST behave per REQ-QSEARCH-006's "no search widget placed" scenario

### Requirement: REQ-QSEARCH-006 Keyboard-shortcut behaviour with zero or multiple search widgets

The system MUST take no focus action when `/` or `Ctrl+K` is pressed on a
dashboard with no `search` widget placed, and MUST focus exactly one search
widget's input — the first-mounted instance — when two or more `search`
widgets are placed on the same dashboard.

#### Scenario: No search widget placed

- GIVEN the active dashboard has no `search` widget placement
- WHEN the user presses `/` (outside a text input) or `Ctrl+K`
- THEN no element MUST receive programmatic focus as a result
- AND normal browser/page behaviour for the keypress MUST proceed unaffected (the shortcut is not intercepted)

#### Scenario: Exactly one search widget placed

- GIVEN exactly one `search` widget is placed on the active dashboard
- WHEN the user presses `/` (outside a text input) or `Ctrl+K`
- THEN that widget's search input MUST receive focus, per REQ-QSEARCH-001

#### Scenario: Multiple search widgets placed

- GIVEN two or more `search` widgets are placed on the same active dashboard
- WHEN the user presses `/` (outside a text input) or `Ctrl+K`
- THEN only the first-mounted widget instance's input MUST receive focus
- AND no other placed instance MUST also attempt to claim focus for that keypress
