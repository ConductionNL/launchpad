# Deprecate the Catalog browse view

## Why

The "Catalog" was a second workspace region (a sidebar `Dashboards ⇄ Catalog` mode toggle) that rendered a **read-only** list of every registered widget, grouped by category (Built-in / Custom Tiles / Bridge), with a filter strip and per-group collapse state. It hits no backend and draws purely from the static widget registry.

It carries no user value that isn't already served better elsewhere:

- The cards are **non-interactive** — clicking one does nothing. A user cannot add a widget from the catalog.
- Adding widgets is owned entirely by the separate `WidgetPickerModal` (the "Add widget" flow in edit mode), which already lists every available widget **and** lets the user place it.
- The catalog therefore splits the workspace UI into two modes for a look-but-don't-touch screen, adding navigation cost and conceptual surface for no payoff.

It was also never backed by a formal requirement — it was implemented ad hoc with `@spec` tags loosely pointing at the `widgets` capability. This change records its removal.

## What Changes

- Remove the Catalog region entirely: the `CatalogView` view, the sidebar `Dashboards ⇄ Catalog` mode switch, the parent's `workspaceMode` region state, and the registry's catalog enumeration helpers.
- The workspace always renders the dashboard canvas; widget discovery + add stays in `WidgetPickerModal`.

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `widgets`: removes the (previously unspecified) Catalog SUB_PAGE browse region. Widget discovery and placement remain fully covered by the existing Add-widget flow (`WidgetPickerModal`). No widget type, renderer, or placement behaviour changes.

## Impact

**Affected code (all removals):**

- `src/views/CatalogView.vue` (delete) + `src/views/__tests__/CatalogView.spec.js` (delete)
- `src/views/Views.vue` — drop the `CatalogView` import/registration, the `workspaceMode` data + `onWorkspaceModeChange` handler, the `:mode` / `@mode-change` sidebar bindings, and the `v-if="workspaceMode === 'catalog'"` branch (the dashboard canvas becomes unconditional)
- `src/components/Workspace/DashboardSwitcherSidebar.vue` — drop the `__modes` mode-switch markup + CSS, the `mode` prop, the `mode-change` emit, and the `onModeChange` method
- `src/constants/widgetRegistry.js` — drop `CATALOG_CATEGORIES`, `catalogCategoryFor`, and `listCatalogEntries` (no remaining consumers)

**Affected APIs:** none — the catalog was entirely client-side.

**Dependencies:** none. The Add-widget flow (`WidgetPickerModal`) is unaffected and remains the single widget-discovery surface.
