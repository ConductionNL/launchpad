# Tasks: own-look-fields

- [x] `DashboardConfigModal.vue`: colour pickers beside their inputs on one row; Background image label, hint, styled upload, preview, Remove (launchpad#2214)
- [x] Same row layout in `TenantThemeManager.vue`; other uploads already styled (launchpad#2214)
- [x] Unit tests in `src/modals/__tests__/DashboardConfigModal.spec.js` (launchpad#2214)
- [x] Live check on a sandbox, 27 Nov 2026 (REVIEW-LAUNCHPAD item 17)
- [x] Playwright `tests/e2e/dashboard-own-look.spec.ts`: picker beside the accent field on one row at 1440 px; upload a background image, see the preview, Remove, the dashboard follows the theme (live 4/4 with --repeat-each 2, 27 Nov, NC 34.0.6 + PostgreSQL sandbox)
- [x] Pixel pass of the Details tab against LpDashboardInstellingen (27 Nov, help-launchpad-3, compdiff design-system cf1799b on an NC 34 + PostgreSQL sandbox, reached read-only through More > Delen > Details: Own look card 4 flags -> font only (thematiq not in the sandbox); Own look drawn as the board: inset card, labels above, swatch left of the hex field, fields side by side, readable-contrast note. Left for the Details tab outside Own look: the dialog's two-column field grid, the compact icon row and NcDialog's title inset (+3.5 px), named in the pixel report)
