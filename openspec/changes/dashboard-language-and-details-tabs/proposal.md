---
kind: code
depends_on: []
---

# Give a dashboard language versions and descriptive details, and let people find dashboards by them

## Why

Two finished backends sit behind no screen. Dashboard language variants (`lib/Service/DashboardTranslationService.php`, main spec `dashboard-language-content`) can be created, resolved for a reader's locale and promoted to primary, and dashboard metadata fields (`lib/Service/MetadataService.php`, main spec `dashboard-metadata-fields`) can be defined by an administrator and filled per dashboard. Neither is reachable: `/api/dashboards/{uuid}/translations` and `/resolved` have no caller in `src/`, and the store actions `fetchMetadataFields` and `updateDashboardMetadata` have no component caller. Both rows are in the core area `dashboards` and share one screen, the dashboard settings dialog, so one change covers them.

This change covers 2 row(s) of the parity matrix (`openspec/parity/capabilities.json`), decided `build` on 2026-09-28.

**dash-languages** (matrix `launchpad`, area `dashboards`), "Show the same dashboard in the reader's own language." Rated `no` for us, `built.state` `building`.
- Demand: own-code. No demand row; the row comes from our own code or our own matrix.
- Microsoft Viva Connections, yes: https://learn.microsoft.com/en-us/sharepoint/homesites/create-multilingual-dashboard translators make per-language copies; 'The Dashboard web part will display in the users preferred language' (read 2026-09-26)
- Nextcloud Dashboard (built-in), yes: source read at v35.0.1: apps/dashboard/src/DashboardApp.vue:236-258 greetings and the widget titles from each app (e.g. apps/files/lib/Dashboard/FavoriteWidget.php:50) are translated with the reader's own language; apps/dashboard/l10n holds 56 languages. Note: there is no author-written dashboard content to translate
- Homarr, partial: source read at v1.77.2: packages/translation/src/lang/ holds 36 locale files and each user picks a language (en.json search.mode.command preferences locale); board content (item titles, notebook text) is stored once with no per-language variant (packages/db/schema/sqlite.ts:413 item options)

**dash-metadata** (matrix `launchpad`, area `dashboards`), "Record extra facts on a dashboard, such as the owning department." Rated `no` for us, `built.state` `building`.
- Demand: own-code. No demand row; the row comes from our own code or our own matrix.
- Workspace 365, partial: https://support.workspace365.net/en/articles/703640-using-and-organising-spaces-in-workspace-365 admins configure 'who owns the Shared space'; other facts such as department not described (read 2026-09-26)

## What changes

- The dashboard settings dialog (`DashboardConfigModal.vue`) gets two tabs: "Languages" and "Details".
- Languages: list the variants, add one (blank or copied from an existing language), edit its name and description, delete it, and make one the primary.
- The workspace page loads the variant that matches the reader's Nextcloud language through the existing resolver, and falls back to the primary.
- Details: an administrator defines fields (text, number, date, select, multi-select) in the administration settings; the Details tab shows those fields for the dashboard and saves the values.
- The switcher search can filter by a metadata value. The service method for this exists but nothing calls it, so the dashboards list endpoint passes the filter through.

## Capabilities

### Modified capabilities
- `dashboard-language-content`: adds the tab, the resolver on the workspace page and the primary switch as screens.
- `dashboard-metadata-fields`: adds the Details tab, the administration field editor and the list filter.
