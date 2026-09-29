# Design: dashboard-tree-navigation

## Context (read at launchpad development 35e2b873)

- Service methods: `validateParent()` (`:136`), `validateSlugUnique()` (`:192`), `computePath()` (`:230`), `computeBreadcrumbs()` (`:261`), `resolvePath()` (`:301`), `getFilteredTree()` (`:410`), `deleteSubtree()` (`:484`).
- Route `GET /api/dashboards/tree` (`appinfo/routes.php:127`); `api.getDashboardTree()` at `src/services/api.js:386`; store state `dashboardTree` and `pathCache`, action `resolveDashboardPath`.
- The left-edge switcher is `DashboardSwitcher.vue` (main spec `dashboard-ui`, change `dashboard-switcher`); it groups by source, not by parent. `OrgNavigationPanel.vue` is a separate administrator-curated navigation and is not touched.
- Create and rename live in `src/dialogs/CreateGroupDashboardModal.vue` and `GroupDashboardRenameDialog.vue`.

## Decisions

### D1. The tree is a view of the switcher, not a new navigation

The switcher keeps its three source groups. Inside a group, dashboards with a parent nest under it, indented, with an expand control. With no parent set anywhere the switcher renders exactly as today, so existing installations see no change.

### D2. Deleting a parent follows the service

`deleteSubtree()` deletes children. The delete dialog for a dashboard that has children says how many will go with it and asks the same confirmation; the frontend does not invent a re-parenting option.

### D3. The breadcrumb reads `computeBreadcrumbs`

The page asks the server for the crumbs of the open dashboard rather than deriving them from the tree it holds, because the tree is filtered by visibility and a hidden ancestor must not leak its name. The service already handles that case.

### D4. Path URLs are additive

The numeric and uuid URLs keep working. A path URL is resolved with the existing endpoint and redirects to the uuid route.

## Declarative-vs-imperative decision

Parent and slug are dashboard fields edited in forms. No manifest change.

