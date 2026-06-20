# Tasks — Deprecate the Catalog browse view

## 1. Remove the view
- [ ] Delete `src/views/CatalogView.vue`
- [ ] Delete `src/views/__tests__/CatalogView.spec.js`

## 2. Unwire the parent (Views.vue)
- [ ] Remove the `CatalogView` import and component registration
- [ ] Remove the `workspaceMode` data field and the `onWorkspaceModeChange` method
- [ ] Remove the `:mode="workspaceMode"` prop and `@mode-change="onWorkspaceModeChange"` listener on `DashboardSwitcherSidebar`
- [ ] Replace the `<CatalogView v-if="workspaceMode === 'catalog'">` / `<div v-else …>` pair with the unconditional dashboard canvas

## 3. Remove the sidebar mode switch (DashboardSwitcherSidebar.vue)
- [ ] Remove the `__modes` mode-switch markup and its `__modes` / `__mode` / `__mode--active` CSS
- [ ] Remove the `mode` prop and the `mode-change` emit
- [ ] Remove the `onModeChange` method

## 4. Remove registry catalog helpers (widgetRegistry.js)
- [ ] Remove `CATALOG_CATEGORIES`, `catalogCategoryFor`, and `listCatalogEntries` (verified: no remaining consumers)

## 5. Verify
- [ ] `npm run build` clean
- [ ] `npx vitest run` green (no orphaned catalog/mode assertions)
- [ ] Live: workspace renders the dashboard canvas; sidebar has no mode switch; no console errors
