# Tasks: versions-on-widget-edits

- [ ] `DashboardVersionService::beginEditSession()` / `endEditSession()`: the state at the start of editing, kept per editor and dashboard (12 h); on the end, stored as an automatic version when the dashboard changed and the newest version does not already hold it; never throws
- [ ] `DashboardPresenceApiController`: an admitted join begins the session, a leave ends it
- [ ] Unit tests: DashboardVersionServiceEditSessionTest, DashboardPresenceApiControllerTest
- [ ] Playwright: `tests/e2e/versions-on-widget-edits.spec.ts` (change a widget in edit mode, leave edit mode, a version appears, restoring it brings the old widget back)
