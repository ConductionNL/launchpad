---
kind: code
depends_on: []
---

# Live tiles that show a list: several fields from a connected system, such as another intranet's latest documents

## Why

A live tile shows one value (`lib/Service/LiveTileService.php`). It can reach
a business system in two ways: a direct allow-listed URL, or an Integriq
connection that applies the source's stored authentication
(`fetchFromConnector()`, line 380, over Integriq's
`DashboardDatasourceService::resolve()`). What it cannot do is show more than
one field or more than one record: no list of tickets, no table of figures, no
"latest documents" from another intranet. Nextcloud's own files widget can
browse another intranet's library only when the server can mount it as
external storage, which SharePoint Online and Confluence spaces are not.

This change covers two rows of the LaunchPad parity matrix
(`openspec/parity/capabilities.json`). Both are partial with the built half in
the same live tile service, so they are one change.

**int-builder**, "Connect a business system and show its data as a tile without writing code." Rated `partial`, `built.state` `built` (`LiveTileService.php:22`).

- Workspace 365, yes: https://support.workspace365.net/en/articles/362230-workspace-365-integration-builder-guide "create integrations between external systems and the digital workspace without coding".
- Homarr, yes: source read at v1.77.2, `apps/nextjs/src/app/[locale]/manage/custom-widgets/_custom-widget-form.tsx` form "Define an API URL, authentication, and how to display the response data" (`en.json:700`) with JSONPath mappings and ten display types.
- The matrix note says there is "no connector, authentication handling or data mapping". The connector mode with stored authentication exists (see above); several fields per tile and a choice of display are what is missing.

**int-intranet-content**, "Show another intranet's news and documents in a tile." Corrected in this PR from `no` to `partial`, `built`: the news widget reads RSS and Atom feeds from any allowed host (`lib/Service/NewsWidgetService.php:133`) and the embed widget frames an allowed page (`lib/Service/IframeService.php:9`), the same half Homarr and Dashy are rated partial for. The documents are missing.

- Workspace 365, yes: https://support.workspace365.net/en/articles/175556-workspace-api-example-sharepoint-newsfeed-in-announcements SharePoint newsfeed into announcements; https://support.workspace365.net/en/articles/175590-rss-feed SharePoint feeds.
- Microsoft Viva, yes: https://learn.microsoft.com/en-us/sharepoint/homesites/available-dashboard-cards News card from SharePoint sites and Folder card for a SharePoint document library.

## What changes

- A live tile gets a display: single value (today), list, table or key-value pairs.
- For list and table, the author points at the array in the response (for example `$.value` for a SharePoint document library or `$.results` for Confluence) and maps up to six fields: a title, a link, a date and up to three more, each with a label.
- A "Latest documents" preset fills the mapping for a document list: title, link to open it in the other intranet, last modified and modified by.
- Everything still flows through the two existing source modes, so authentication stays in Integriq and direct URLs stay behind the fail-closed allow-list.

## Capabilities

### Modified capabilities

- `live-data-tile-widget`: adds list, table and key-value displays with field mapping.

## Impact

- `lib/Service/LiveTileService.php` (array extraction, mapping, limit, cache shape), `lib/Controller/LiveTileController.php`
- `src/components/Widgets/Renderers/LiveTileWidget.vue` and `LiveTileWidgetForm.vue` (display choice, mapping rows, preset; the form's stale "OpenConnector" labels become "Integriq")
- No Integriq change: `resolve()` already returns whatever node the expression selects.

## Out of scope

- A catalogue of ready-made connectors per product (AFAS, TOPdesk). Those are rows int-erp and int-itsm, deferred; this change gives the tool to build one.
