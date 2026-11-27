# Design: widget-declared-placeholders

## Context

`first-paint-skeleton` D5 draws each frame's placeholder by widget kind from `lib/Service/first-paint-kinds.json`,
which both the PHP view model and `LazyMount` read. From 27 November 2026 the widget declares its shape, and launchpad
reads the declaration. nextcloud-vue change `widget-placeholder-shape` adds the declaration to the library (registry
entry and manifest `widgetDef`), the `CnWidgetPlaceholder` component and its markup contract, and publishes the
registry's declarations as `widget-placeholders.json`.

## Decisions

### D1. One resolution order, on both sides

A frame's shape is the first of:

1. the manifest declaration a placement carries (`widgetDef.placeholder`), for a widget offered from an app manifest;
2. the registry declaration for the placement's type, from `widget-placeholders.json`;
3. for an `nc-widget` placement, the `placeholder` in the catalogue descriptor of the Nextcloud widget it shows (D2);
4. the fallback in `first-paint-kinds.json`: `rows`.

PHP (`FirstPaintShell`) and JS (`WidgetWrapper` through nextcloud-vue's `resolveWidgetPlaceholder`) apply the same
order to the same inputs. A shared fixture of placements with their expected shapes runs in PHPUnit and in Vitest, as
the layout fixture does in first-paint-skeleton D3.

### D2. A Nextcloud widget declares its shape in its PHP class

Nextcloud's `IWidget` has no placeholder. A Conduction app's widget class declares it with a public method:

```php
public function getPlaceholder(): string|array
{
    return 'rows';
}
```

returning a shape name or `['shape' => 'rows', 'count' => 5]`. `WidgetFormatter::buildBaseData()` reads it when the
method exists, validates it against the six shapes and adds `placeholder` to the descriptor; an invalid value is
dropped with a debug log naming the widget id, and the widget falls back to `rows`. The descriptor already reaches the
page through the widget catalogue (`getWidgetCatalogue()`, read without running any widget's `load()`, REQ-RFP-010), so
the shell and the mounted page read the same value and no request is added.

Why a method and not a capability or the app manifest: the catalogue is already built from the widget objects on every
workspace request, so a method costs one call per widget and nothing else. A capability would load every app's
capability providers on the page request. An app's manifest is a frontend asset that the server of another app has no
reliable way to read. The method is duck-typed, so no shared PHP package is needed; hydra's ADR-036 amendment makes it
the fleet contract and a gate can check that every class in an app's `lib/Dashboard` declares it.

### D3. The registry's declarations on the server

`widget-placeholders.json` is copied from `@conduction/nextcloud-vue/widget-placeholders.json` to
`lib/Service/widget-placeholders.json` by an npm `prebuild` step and committed, because the PHP side cannot read
`node_modules` in a release. A Vitest test compares the committed copy with the installed package, so a library
upgrade that changes a shape fails until the copy is refreshed.

### D4. The fallback table

`first-paint-kinds.json` keeps its name and its readers and becomes `{ "default": "rows" }` plus entries only for a
launchpad placement type that has no registry declaration. A test fails when the file names a type that
`widget-placeholders.json` declares, so a declaration is never shadowed by a stale entry. Nextcloud widgets of other
vendors (Talk, Calendar, Mail, Deck, weather) declare nothing and draw `rows`.

### D5. One look, from the library

The shell prints nextcloud-vue's placeholder markup contract (`cn-widget-placeholder`, design D4 of
`widget-placeholder-shape`) and inlines `css/widget-placeholder.css` from the package into its style block; the
6 KB budget of first-paint-skeleton D8 includes it. The mounted page passes `loading` and the resolved `placeholder` to
`CnWidgetWrapper`, which draws `CnWidgetPlaceholder`. So first-paint-skeleton task 5 no longer builds launchpad's own
`WidgetPlaceholder.vue`, and the frame-rect e2e compares library markup with library markup.

The count follows first-paint-skeleton D5: a declared count is clipped to the frame, otherwise the frame's height
decides.

## Risks

1. A Conduction widget without `getPlaceholder()` silently draws rows. Accepted by the decision; the hydra fleet task
   list and gate close it per app.
2. The committed JSON copy goes stale: the Vitest comparison fails the build.
3. Ordering with first-paint-skeleton: this change depends on it and archives after it, so REQ-FPS-002 exists when the
   modification is applied.
