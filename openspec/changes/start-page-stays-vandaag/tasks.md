# Tasks: start-page-stays-vandaag

- [x] `lib/Service/DashboardService.php` (`resolveActiveDashboard()`): step 1 reads `default_dashboard_uuid`; drop the `active_dashboard_uuid` step from the page chain; clear a stale start dashboard
- [x] Same order in the client mirror `useDashboardStore.resolveActive` and in `GET /api/dashboard` (REQ-DASH-009 path)
- [x] Template rung (REQ-TMPL-019): store the new copy as `default_dashboard_uuid`; "already using launchpad" = owns a personal dashboard or has a start dashboard
- [x] Unit tests: `DashboardServiceActiveResolutionTest` (chosen start dashboard wins, last-used does not, stale start dashboard cleared, Vandaag when nothing is chosen), `DashboardServiceTemplateRungTest` (copy stored as start dashboard), the effective default marker tests
- [ ] Playwright `tests/e2e/start-page.spec.ts`: a member opens another dashboard from More, reloads `/apps/launchpad/`, lands on Vandaag; after Set as default on that dashboard, a reload lands there
- [ ] Live check on a sandbox: an existing user with a last-used dashboard opens on Vandaag after the upgrade; a new template member gets the copy as start dashboard
