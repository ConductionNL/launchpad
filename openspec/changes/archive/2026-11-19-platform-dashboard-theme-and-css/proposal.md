---
kind: code
depends_on: []
---

# Give each dashboard its own look, and let administrators add their own CSS

## Why

Every LaunchPad dashboard looks the same: it follows Nextcloud's theme
(`src/views/WorkspaceApp.vue` draws with Nextcloud's variables). Each dashboard
already has its own grid columns (`lib/Migration/DashboardTableBuilder.php:173`)
and per-language content in the backend (`lib/Db/DashboardTranslation.php`),
but there is no theme, colour or background per dashboard, so a team dashboard
cannot look different from the organisation's front page. And there is no way
to restyle LaunchPad beyond Nextcloud's theming: no custom CSS at all.

This change covers two rows of the LaunchPad parity matrix
(`openspec/parity/capabilities.json`). Both change how a dashboard looks and
are applied in the same place, so they are one change.

**d-theme-per-dashboard**, "Give each dashboard its own theme, language and layout." Rated `partial`, `built.state` `built` (grid columns per dashboard). This change specifies the missing half: a theme per dashboard. Language per dashboard is row dash-languages, already `building`.

- Demand: changelog, https://github.com/Lissy93/dashy/releases/tag/4.0.0 ("Per-page config: theme, language, layout, icon size, favicon").
- Microsoft Viva, yes: https://learn.microsoft.com/en-us/sharepoint/homesites/sharepoint-app-in-teams-overview multiple home sites, each "a targeted experience" with its own dashboard, multilingual dashboards and a theme per site.
- Dashy, yes: source read at 4.7.0, `src/store.js:153-164` a page's own appConfig overrides the root's (theme, language, layout, icon size).

**p-custom-css**, "Add your own CSS to restyle the dashboard." Rated `no`, `built.state` `none`.

- Homarr, yes: source read at v1.77.2, `apps/nextjs/src/app/[locale]/boards/(content)/_custom-css.tsx:8` injects board.customCss; "Custom css for this board".
- Dashy, yes: source read at 4.7.0, `ConfigSchema.json:564-568` customCss and externalStyleSheet, edited in the config menu's Custom CSS tab.

## What changes

- **Dashboard look.** Anyone who may edit a dashboard can give it an accent colour, a background colour or a background image (from LaunchPad's resource uploads) on a new "Look" tab. The look applies to that dashboard only; everything else keeps Nextcloud's theme, including NL Design System themes.
- **Readable by default.** Text on a coloured background is black or white, whichever reads better, and an accent that buttons would be hard to see in (under 3:1 against the background) is refused with the measured ratio.
- **Custom CSS for administrators.** Administrators can add CSS for all of LaunchPad and for one dashboard. It is cleaned on save: no `@import`, no addresses to other sites, no script-like constructs, and it is scoped so it cannot restyle Nextcloud outside LaunchPad.

## Capabilities

### Modified capabilities

- `dashboards`: adds the per-dashboard look and administrator CSS.

## Impact

- `lib/Db/Dashboard.php` and a migration (`theme_accent`, `theme_background_color`, `theme_background_resource`, `custom_css`), `lib/Service/DashboardService.php`
- `lib/Db/AdminSettingKey.php` (`custom_css`), a new `CustomCssSanitiser` with a CSS parser dependency (`sabberworm/php-css-parser`)
- `src/modals/DashboardConfigModal.vue` (a "Look" tab), `src/views/WorkspaceApp.vue` (applying variables and scoped CSS), an admin section

## Out of scope

- Custom CSS for non-administrators. CSS can hide or fake parts of the page, so it stays an administrator tool; everyone else gets the safe colour and background choices.
- Language per dashboard (row dash-languages, already building).
