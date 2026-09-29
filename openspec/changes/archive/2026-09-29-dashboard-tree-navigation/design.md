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


## Corrections at build (29 Sep 2026)

- D2: `DELETE /api/dashboard/{id}` refuses a dashboard with children (409 `dashboard_has_children` with `childCount`) unless `cascade=true`. The delete dialog shows the count after that answer and the next confirmation sends `cascade`.
- D3: `computeBreadcrumbs()` does not filter by visibility. `GET /api/dashboard/{id}` now returns `breadcrumbs`, and the controller blanks any ancestor outside the reader's visible set (`hidden: true`, no uuid or name). `GET /api/dashboards/by-path` still returns unfiltered crumbs; that is inherited and not changed here.
- Task 2: the parent and web address name are edited in the dashboard settings (`DashboardConfigModal`), which every owner uses, not in the administrator's group-dashboard create and rename dialogs.
- D4: path URLs already resolve (PageController `deepLinkPath`, `Views.vue` popstate through `by-path`); nothing added.
