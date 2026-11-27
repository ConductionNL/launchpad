# Tasks: versions-on-widget-edits

- [x] `Versions\EditSessionVersions::begin()` / `end()`: the state at the start of editing, kept per editor and dashboard (12 h); on the end, stored as an automatic version when the dashboard changed and the newest version does not already hold it; never throws
- [x] `DashboardPresenceApiController` (through the `DashboardEditing` holder): an admitted join begins the session, a leave ends it
- [x] Unit tests: EditSessionVersionsTest, DashboardPresenceApiControllerTest
- [x] Playwright: `tests/e2e/versions-on-widget-edits.spec.ts` (change a widget in edit mode, leave edit mode, a version appears, restoring it brings the old widget back): live 8/8 (repeat 2) on NC 34.0.6 + MariaDB, OR main 43712f61
