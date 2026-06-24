# Tasks — Deprecate the Catalog browse view

## 1. Remove the view
- [x] Delete `src/views/CatalogView.vue`
- [x] Delete `src/views/__tests__/CatalogView.spec.js`

## 2. Unwire the parent (Views.vue)
- [x] Remove the `CatalogView` import and component registration
- [x] Remove the `workspaceMode` data field and the `onWorkspaceModeChange` method
- [x] Remove the `:mode="workspaceMode"` prop and `@mode-change="onWorkspaceModeChange"` listener on `DashboardSwitcherSidebar`
- [x] Replace the `<CatalogView v-if="workspaceMode === 'catalog'">` / `<div v-else …>` pair with the unconditional dashboard canvas

## 3. Remove the sidebar mode switch (DashboardSwitcherSidebar.vue)
- [x] Remove the `__modes` mode-switch markup and its `__modes` / `__mode` / `__mode--active` CSS
- [x] Remove the `mode` prop and the `mode-change` emit
- [x] Remove the `onModeChange` method

## 4. Remove registry catalog helpers (widgetRegistry.js)
- [x] Remove `CATALOG_CATEGORIES`, `catalogCategoryFor`, and `listCatalogEntries` (verified: no remaining consumers)

## 5. Verify
- [x] `npm run build` clean
- [x] `npx vitest run` green (no orphaned catalog/mode assertions)
- [x] Live: workspace renders the dashboard canvas; sidebar has no mode switch; no console errors
