# Design: tile-search-widget

## Context

`RuntimeShellSearch.vue` (558 lines) and `useTileSearch.js` already isolate
every filter/rank/keyboard-selection/fallback-decision concern behind a
narrow `items`/`fallbackTarget` prop contract and an `open`/`filter`/
`fallback`/`clear` event contract. Neither file needs to change in
substance. What currently breaks the "placeable widget" model is that the
*host* responsibilities — resolving `items` from the live store, deciding
the fallback target, and performing the DOM side effects the events ask for
(dim non-matching tiles, scroll/activate the chosen tile, return focus to
the grid on Esc) — all live inline in `WorkspaceApp.vue`, a page-level
component that is not, and cannot become, a dashboard widget.

`WorkspaceApp.vue` reaches into the grid via `this.$el.querySelector(...)`
because the grid DOM lives in a sibling component's tree (`Views.vue`,
mounted as `<Views>` inside `#launchpad-main-content`). Once the search bar
moves inside the grid as a widget, `this.$el` for the widget's own root is
a small combobox `<div>`, not a useful query root — the design has to
re-anchor those DOM queries at a stable, page-level element instead of a
component-relative one.

Two behaviours are load-bearing regression fixes from launchpad#95 and are
treated as constraints, not implementation details to be rediscovered:

1. **Dimming id comparison.** `placement.id` off the API row is an integer;
   `getAttribute('data-placement-id')` always returns a string. Comparing
   without normalising both sides to `String(...)` makes `Array.includes`
   fail for every row (`[7].includes('7') === false`), dimming every tile on
   every query.
2. **Activation id cast.** `activateSearchResult` casts
   `placement.id` with `String(... ?? '')` before using it in a selector.
   The prior code called `.replace(...)` on the raw (possibly numeric)
   value; `Number.prototype.replace` does not exist, so a bare integer threw
   a `TypeError` inside a Vue event handler — swallowed, silently breaking
   Enter-to-open.

## Goals / Non-Goals

**Goals**

- Quick-search becomes an ordinary placeable/configurable dashboard widget,
  registered the same way `clock`/`weather`/`iframe`/`livetile` already are.
- The two launchpad#95 fixes survive the extraction with identical
  behaviour, not just identical intent.
- Zero-instance and multiple-instance keyboard-shortcut behaviour is
  explicit and deterministic, not accidental.
- No backend change of any kind — the admin `quicksearch_fallback_target`
  setting is reused as-is as the inherited default.

**Non-Goals**

- No auto-migration of existing dashboards onto the widget (explicit
  product decision — see proposal.md → Out of Scope).
- No change to `useTileSearch.js`'s filter/rank/fallback-decision logic.
- No new persistence shape beyond the standard `WidgetPlacement.content`
  JSON blob every other widget type already uses.

## Decisions

### 1. Renderer wraps `RuntimeShellSearch.vue` unchanged (plus one new prop)

`SearchWidget.vue` renders `<RuntimeShellSearch>` and supplies `items`,
`fallbackTarget`, and now `placeholder`, listening for the same four events.
**Alternative considered:** fork the combobox markup directly into the
renderer. Rejected — `RuntimeShellSearch.vue`'s WCAG-AA combobox
implementation (roles, `aria-activedescendant`, live region) is exactly
reusable and has its own test suite
(`src/components/__tests__/RuntimeShellSearch.spec.js`); forking it would
duplicate 400+ lines and desynchronise from any future fix to the base
component.

### 2. Host wiring extracted into `useTileSearchHost.js`, not inlined in the renderer

The label resolution (`tileSearchLabel`), dimming (`applySearchDimming`),
activation (`activateSearchResult`), fallback dispatch (`onSearchFallback`),
and focus-return (`focusGrid`) logic moves into a new composable factory
`useTileSearchHost({ getPlacements, getAvailableWidgets, getFallbackTarget })`
that `SearchWidget.vue` calls in `data()`, mirroring the existing
`useTileSearch()` factory shape already used by `RuntimeShellSearch.vue`
(a plain object of methods over closed-over state, no `setup()` required).
**Alternative considered:** keep the methods directly on `SearchWidget.vue`.
Rejected — the two regression-risk methods (dimming, activation) are easiest
to keep byte-for-byte identical to their `WorkspaceApp.vue` originals, and
therefore easiest to unit-test in isolation, when they are pure exported
functions rather than component methods entangled with `this`.

### 3. DOM queries re-anchor from `WorkspaceApp`'s `$el` to `#launchpad-main-content`

`applySearchDimming` and `focusGrid` currently query
`this.$el.querySelectorAll(...)` from `WorkspaceApp.vue`, whose `$el` wraps
the whole shell including the grid. From inside a grid-placed widget, the
widget's own `$el` is a small subtree that does **not** contain the grid —
the grid is a *sibling* subtree, one level up and over, same as it always
was relative to `WorkspaceApp.vue`. The fix is to query from the stable,
page-level `#launchpad-main-content` element (declared once in
`WorkspaceApp.vue`, `tabindex="-1"`) via `document.getElementById(...)`
instead of a component-relative `$el` walk:

- `applySearchDimming` queries
  `document.getElementById('launchpad-main-content')?.querySelectorAll('.launchpad-grid-item[data-placement-id]')`.
- `activateSearchResult` scopes its single-item lookup the same way.
- `focusGrid` becomes `document.getElementById('launchpad-main-content')?.focus(...)`
  — the exact same element `WorkspaceApp.vue`'s old `.workspace-shell__grid`
  class selector resolved to (one DOM node, two selectors), so this is a
  like-for-like replacement, not a behaviour change.

**Alternative considered:** pass the grid container element down via
`provide`/`inject` from `WorkspaceApp.vue`. Rejected — over-engineered for
a single stable, page-unique id that already exists for exactly this
purpose (the Esc-focus-target contract), and it would require plumbing the
ref through `Views.vue` → `WidgetRenderer.vue` → `SearchWidget.vue` for no
behavioural gain over a `getElementById` lookup on an id that is guaranteed
unique per page.

### 4. Keyboard-shortcut singleton guard lives in `RuntimeShellSearch.vue`

Today `RuntimeShellSearch.vue` attaches its own `window` `keydown` listener
in `mounted()`/`beforeUnmount()` (`onWindowKeydown`), safe because exactly
one instance ever existed. Once `search` is a widget type, a dashboard can
carry two or more placements, so two or more `RuntimeShellSearch` instances
can be mounted simultaneously, each independently calling `focusInput()` on
the same keypress — without a guard, the *last*-mounted instance would win
(DOM focus is single-valued and each listener fires in mount order), not
the first, and REQ-QSEARCH-006 requires the opposite.

The fix is a small addition to the existing `instanceCounter` pattern: a
module-level ordered array (`activeInstances`) that each instance's
`mounted()` pushes itself onto and `beforeUnmount()` splices itself out of.
`onWindowKeydown` only calls `focusInput()` when `activeInstances[0] ===
this` — i.e. only the first-still-mounted instance ever acts. If the first
instance is later removed from the dashboard (widget deleted), the next
instance in the array is promoted automatically, since the guard re-checks
`activeInstances[0]` on every keypress rather than caching "am I first" at
mount time. This keeps the change inside `RuntimeShellSearch.vue` (already
in the file plan as CHANGED for the `placeholder` prop) rather than
requiring `useTileSearchHost.js` or the widget registry to coordinate
cross-instance state.

**Alternative considered:** a Pinia store slice tracking the "active search
instance". Rejected — this is transient, render-lifetime-scoped
coordination between sibling component instances, exactly what a
module-level closure variable is for; a store slice would be persisted
machinery for a problem that resets itself every time the page reloads.

### 5. Config form fields: `placeholder` (text) and `fallbackTarget` (select, empty = inherit)

`SearchWidgetForm.vue` mirrors `IframeWidgetForm.vue`'s shape: an
`NcTextField` for `placeholder`, and an `NcSelect` for the fallback override
with options `inherit admin setting` (value `''`), `none`, `unified-search`,
and `web-search URL template` (a follow-up `NcTextField` for the template
string, shown only when that option is picked, validated with the exact
`isValidFallbackTemplate()` rule `useTileSearch.js` already exports —
reused, not reimplemented). `defaultContent: { placeholder: '', fallbackTarget: '' }`
in the registry entry — both fields default to "inherit/built-in", so a
freshly-added widget behaves identically to today's shell bar out of the
box.

### 6. Fallback resolution layering happens in the renderer, not in `useTileSearch.js`

`SearchWidget.vue` computes the resolved `fallbackTarget` prop it passes to
`RuntimeShellSearch` as
`this.content.fallbackTarget || this.injectedQuicksearchFallbackTarget`
(empty-string / unset override falls through to the injected admin
default). `useTileSearch.js`'s `resolveFallbackAction()` is unchanged — it
still just receives "the" fallback target string and does not need to know
that target was itself resolved from two layers.

## Declarative-vs-imperative decision (ADR-031)

ADR-031's trigger list includes "dashboard widgets", so this section is
mandatory even though it resolves quickly: **`search` is a LaunchPad
client-side widget type**, registered via `registerDashboardWidget('search',
{...})` in `src/constants/widgetRegistry.js` against the communal
`dashboardWidgetRegistry` from `@conduction/nextcloud-vue` — the exact same
mechanism `clock`/`weather`/`livetile`/`iframe` already use. It is **not**
an OpenRegister object behaviour, so `x-openregister-widgets` in a schema
register is not the applicable mechanism here: this change touches no
OpenRegister register or schema definition at all. There is no PHP service,
no new controller, no new entity — the entire capability is a Vue
component tree plus a registry entry plus a JSON `content` shape persisted
on the existing `WidgetPlacement` entity, identical in kind to every other
LaunchPad-local widget type. `kind: code` (declared in proposal.md
frontmatter) reflects that the change is Vue/JS implementation, not a
schema/config declaration.

## Nextcloud Integration

- **Controllers:** none new; no existing controller changes.
- **Services:** none new; `AdminSettingsService` (backing
  `quicksearch_fallback_target`) is read exactly as it is read today, via
  the existing `quicksearchFallbackTarget` initial-state key and Vue
  `inject`.
- **Mappers/Entities:** none new; `search` placements use the existing
  `WidgetPlacement` entity/mapper with no schema change.
- **Events/Hooks:** none.

## Security Considerations

No security impact. No new endpoint, no new input surface beyond a widget
`content` blob validated client-side (placeholder text, fallback-target
enum/URL) the same way every other widget form already validates its
fields; the authoritative `https` + `{query}` template validation already
enforced server-side for the admin setting (REQ-QSEARCH-004 "Fallback
template validation") is unchanged and untouched by this per-widget layer,
which only ever narrows or matches the admin default, never widens it.

## NL Design System

`SearchWidgetForm.vue` uses `NcTextField`/`NcSelect`, matching every other
LaunchPad-local widget form (`ClockWidgetForm.vue`, `IframeWidgetForm.vue`).
`RuntimeShellSearch.vue`'s existing WCAG-AA combobox markup and CSS-variable
theming are reused unchanged, so no new NL Design System surface is
introduced — the widget inherits the same accessible-combobox contract the
shell bar already satisfied.

## File Structure

```
src/
  components/
    RuntimeShellSearch.vue           (CHANGED — optional `placeholder` prop;
                                       module-level keyboard-shortcut guard)
    Widgets/
      Renderers/
        SearchWidget.vue             (NEW — renderer)
        SearchWidgetForm.vue         (NEW — config form)
  composables/
    useTileSearchHost.js             (NEW — extracted host wiring)
  constants/
    widgetRegistry.js                (CHANGED — register `search`)
  views/
    WorkspaceApp.vue                 (CHANGED — remove search region,
                                       handlers, computeds, CSS)
    Views.vue                        (CHANGED — 2 comment updates only)
```

## Seed Data

Not applicable. This change introduces no OpenRegister schema, register, or
object definition of any kind — it is a client-side Vue widget type whose
only persisted shape is the existing `WidgetPlacement.content` JSON blob
(company-wide seed-data ADR requires seed objects "for EACH schema this
change introduces or modifies"; there is no such schema here). No
`_registers.json` entries are generated by this change. A dashboard author
adds the widget through the existing Add Widget flow like any other type;
there is nothing to seed at install time beyond what `WidgetPlacement`
already ships.

## Risks / Trade-offs

- **[Risk] The dimming/activation regression fixes drift during extraction**
  → **Mitigation:** both are named explicitly above (Context section) and
  in the file plan; `useTileSearchHost.js` carries them as close to
  byte-identical as the new query root (`#launchpad-main-content` vs `$el`)
  allows, and tasks.md/test-plan.md assign named test coverage to each.
- **[Risk] The keyboard-shortcut singleton guard has an off-by-one on rapid
  add/remove of search widgets** → **Mitigation:** the guard re-reads
  `activeInstances[0]` on every keypress rather than caching a decision at
  mount time, so it self-corrects on the next keypress even if a mount/
  unmount race left a stale reference for one tick.
- **[Risk] Removing the shell bar with no fallback is a visible regression
  to anyone who has not yet placed the widget** → **Mitigation:** accepted
  product decision (proposal.md → Risk 2); not mitigated further by design,
  only documented so it is not mistaken for a bug in review.

## Migration Plan

No deployment migration is required — this is a frontend-only bundle
change with no database/schema impact (confirmed above), so no
`migration.md` is generated for this change. Rollback is a plain `git
revert`; see proposal.md → Rollback Strategy for the full procedure.

## Open Questions

None outstanding — every technical decision above was resolvable from the
existing `RuntimeShellSearch.vue` / `useTileSearch.js` / `widgetRegistry.js`
patterns already shipped in this codebase.
