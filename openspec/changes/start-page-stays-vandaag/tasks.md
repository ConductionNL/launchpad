# Tasks: start-page-stays-vandaag

- [ ] `lib/Service/DashboardService.php` (`resolveActiveDashboard()`): step 1 reads `default_dashboard_uuid`; drop the `active_dashboard_uuid` step from the page chain; clear a stale start dashboard
- [ ] Same order in the client mirror `useDashboardStore.resolveActive` and in `GET /api/dashboard` (REQ-DASH-009 path)
- [ ] Template rung (REQ-TMPL-019): store the new copy as `default_dashboard_uuid`; "already using launchpad" = owns a personal dashboard or has a start dashboard
- [ ] Repair step: a last-used template copy without a start dashboard becomes the start dashboard; idempotent
- [ ] Unit tests: `DashboardServiceActiveResolutionTest` (chosen start dashboard wins, last-used does not, stale start dashboard cleared, Vandaag when nothing is chosen), `DashboardServiceTemplateRungTest` (copy stored as start dashboard), the repair step test, the effective default marker tests
- [ ] Playwright `tests/e2e/start-page.spec.ts`: a member opens another dashboard from More, reloads `/apps/launchpad/`, lands on Vandaag; after Set as default on that dashboard, a reload lands there
- [ ] Live check on a sandbox: an existing user with a last-used dashboard opens on Vandaag after the upgrade; a template member keeps their copy
