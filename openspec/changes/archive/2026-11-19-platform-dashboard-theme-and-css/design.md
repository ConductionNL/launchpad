# Design: platform-dashboard-theme-and-css

Read at development `d767c282`.

## Context

- `lib/Db/Dashboard.php` holds presentation columns such as `gridColumns` (line 371) and `dashboardFooterMode` (line 539); columns are created by `lib/Migration/DashboardTableBuilder.php` (dashboard columns at lines 75-255) and later `Version*` migrations (latest `Version002010Date20260918184500`).
- `src/views/WorkspaceApp.vue` renders the shell with Nextcloud variables (`--color-main-background`, `--color-border`, `--color-main-text` around lines 470-510); `Views` draws the active dashboard (line 73).
- `src/modals/DashboardConfigModal.vue` is a tabbed dialog (tablist at lines 22-33) for a dashboard's settings.
- Resource uploads (`lib/Service/ResourceService.php`, `ResourceServeController`, REQ-RES) serve images LaunchPad stores, with SVG sanitising (`lib/Service/SvgSanitiser.php`).
- NL Design System and Nextcloud theming set the global variables; `thematiq` (formerly nldesign) themes the whole instance.
- `sabberworm/php-css-parser` is not installed (it appears in `composer.lock` only inside the security-advisories conflict list).

## Decisions

### D1: A look is four columns, applied as scoped variables

New nullable columns on `oc_launchpad_dashboards`: `theme_accent` (hex), `theme_background_color` (hex), `theme_background_resource` (resource id), `custom_css` (text, admin only). `WorkspaceApp` sets `--color-primary-element`, `--color-primary-element-text` and the background on the dashboard region element only, so the org navigation, sidebar and Nextcloud header keep the instance theme. Empty columns mean "follow the theme".

### D2: Contrast is checked on the server

Text on the dashboard background gets black or white, whichever contrasts more with the background colour, so body text always reaches at least 4.5:1. What can still fail is the accent against the background: buttons, borders and focus rings drawn in the accent must reach 3:1 against the background (WCAG 2.2 SC 1.4.11 non-text contrast), and the accent's own text colour (black or white, derived the same way) reaches 4.5:1 by construction. `DashboardService::updateDashboard()` refuses an accent under 3:1 against the background (the instance's background when none is set) with `{error: "contrast_too_low", ratio}`. The client shows the same check live.

### D3: Custom CSS is administrator-only, parsed and scoped

`custom_css` on a dashboard and the global `custom_css` setting can be written only by administrators (`#[AuthorizedAdminSetting]` routes, and `DashboardService` refuses the field from non-admins). `CustomCssSanitiser` parses with `sabberworm/php-css-parser`, drops `@import`, `@font-face` with remote sources, any `url()` not pointing at LaunchPad's own resource route, `expression(`, `behavior`, `-moz-binding` and `javascript:`, prefixes every selector with `.launchpad-workspace` (global) or `.launchpad-dashboard[data-dashboard-uuid="<uuid>"]` (per dashboard), and caps the size at 20 KB. The stored value is the sanitised output; the admin sees what was dropped.

### D4: CSS is delivered as text, never as markup

The sanitised CSS is served in the initial state and inserted with a `<style>` element whose `textContent` is set, never `innerHTML`. It is served with the page's existing CSP; no inline script is involved.

## Declarative-vs-imperative decision

Presentation settings on LaunchPad's own dashboard table; no schema register involved.

## Permissions

- Look (colours, background): anyone with full permission on the dashboard, or administrators for group and template dashboards.
- Custom CSS: administrators only.

## Test plan

- PHPUnit: migration, contrast refusal, sanitiser cases (`@import`, remote `url()`, `expression(`, selector scoping, size cap), non-admin refused on `custom_css`.
- Vitest: the "Look" tab with live contrast, variables applied to the dashboard region only.
- Playwright: set a dark green accent and a background image on "Team Groen", open it and another dashboard, only "Team Groen" changes; an admin's CSS with `@import` is saved without it.
