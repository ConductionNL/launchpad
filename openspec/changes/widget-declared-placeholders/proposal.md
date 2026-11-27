---
kind: code
depends_on: [first-paint-skeleton]
chain: []
---

# Proposal: widget-declared-placeholders

## Why

Ruben's decision of 27 November 2026: every dashboard widget declares its own placeholder shape, so launchpad's
server-rendered first paint and nextcloud-vue's widget wrapper draw the same shape for the same widget. Conduction
widgets all declare one; widgets of other vendors fall back to rows.

`first-paint-skeleton` (D5) keeps the shape in launchpad's own table, `lib/Service/first-paint-kinds.json`, from widget
type to kind. That table only launchpad reads, so nextcloud-vue's `CnDashboardPage` in every other app still shows a
spinner, and every new widget type is drawn as rows until someone edits launchpad. The shape belongs with the widget.

## What changes

- `first-paint-shell`, REQ-FPS-002 modified and REQ-FPS-009 added: the placeholder shape of a frame comes from the
  widget's own declaration, in this order:
  1. the widget's declaration in an app manifest (`widgetDef.placeholder`), when the placement carries one;
  2. the widget type's declaration in nextcloud-vue's widget registry, read on the server from the package's
     `widget-placeholders.json` (nextcloud-vue change `widget-placeholder-shape`);
  3. for a Nextcloud dashboard widget (`nc-widget` placement): the value its PHP widget class declares through a
     public `getPlaceholder()` method (hydra ADR-036 amendment of 27 November 2026), carried in launchpad's widget
     catalogue;
  4. otherwise the fallback, `rows`.
- `first-paint-kinds.json` stays, as the fallback only: it holds the default (`rows`) and no entry for a type that
  declares its own shape. Other vendors' widgets land there.
- The mounted page draws the same declared shape through nextcloud-vue's `CnWidgetWrapper` (`loading`, `placeholder`)
  and `CnWidgetPlaceholder`, and the shell prints nextcloud-vue's placeholder markup with its standalone CSS inlined.
  Launchpad's own `WidgetPlaceholder.vue` (first-paint-skeleton task 5) is not built.
- Launchpad's own Nextcloud dashboard widgets (`launchpad_links`, `launchpad_news`) declare `rows`.

## Out of scope

- The library side (the `placeholder` property, `CnWidgetPlaceholder`, the wrapper, the JSON export): nextcloud-vue
  change `widget-placeholder-shape`.
- Declaring shapes in the other fleet apps: the fleet task list in the hydra ADR-036 amendment.
- Everything else in `first-paint-skeleton` (geometry, hand-over, status line, measurement) is unchanged.

## Impact

- `lib/Service/WidgetFormatter.php` (catalogue descriptor gains `placeholder`), `lib/Service/FirstPaintShell.php`
  (shape resolution), `lib/Service/first-paint-kinds.json` (fallback only), a new committed copy
  `lib/Service/widget-placeholders.json` with a prebuild step that refreshes it from `@conduction/nextcloud-vue`.
- `src/components/WidgetWrapper.vue`, `src/components/LazyMount.vue` (pass `loading` and the resolved placeholder to
  `CnWidgetWrapper`), `templates/index.php` (nextcloud-vue placeholder markup and inlined CSS).
- `lib/Dashboard/LinksWidget.php`, `lib/Dashboard/NewsWidget.php` (`getPlaceholder()`).
- Needs the nextcloud-vue release that ships `widget-placeholder-shape`.
- No new initial-state key: `placeholder` rides in the catalogue descriptor the page already carries. No new strings.

## Boards

LpLaden, LpLadenDeels (unchanged; the shapes they draw are what the declarations produce).
