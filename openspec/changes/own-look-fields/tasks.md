# Tasks: own-look-fields

- [x] `DashboardConfigModal.vue`: colour pickers beside their inputs on one row; Background image label, hint, styled upload, preview, Remove (launchpad#2214)
- [x] Same row layout in `TenantThemeManager.vue`; other uploads already styled (launchpad#2214)
- [x] Unit tests in `src/modals/__tests__/DashboardConfigModal.spec.js` (launchpad#2214)
- [x] Live check on a sandbox, 27 Nov 2026 (REVIEW-LAUNCHPAD item 17)
- [x] Playwright `tests/e2e/dashboard-own-look.spec.ts`: picker beside the accent field on one row at 1440 px; upload a background image, see the preview, Remove, the dashboard follows the theme (live 4/4 with --repeat-each 2, 27 Nov, NC 34.0.6 + PostgreSQL sandbox)
- [ ] Pixel pass of the Details tab against LpDashboardInstellingen (27 Nov done-launchpad: compdiff cannot open the dialog yet: its read-only run times out entering edit mode on demo-havenpark, as pieter and as admin; needs a compdiff step that allows the edit-mode request or a settings route)
