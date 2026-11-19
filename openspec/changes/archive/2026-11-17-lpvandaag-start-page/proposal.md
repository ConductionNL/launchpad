---
kind: code
depends_on: [shipped-template-update]
---

# LpVandaag: the start page in one screen

## Why

The board `launchpad/LpVandaag` draws the employee's start page in one screen. On 1440×900 and 1366×768 the page does
not scroll. The rows divide the height under the top bar, and the agenda, recent work and tiles scroll in their own
card. The apps are tiles, not a list of links.

Beside "Startpagina aanpassen" the page had four more controls: a cog with the dashboard's actions, a ☰ dashboard
switcher, a … menu with the app's destinations and a Share button. Share was in the cog too, and the admin settings
were in the … menu twice. The board keeps one "Meer" menu.

## What changes

- `DashboardMoreMenu` replaces the four controls. Left: Dashboards (the switcher, the open one checked, "Alle
  dashboards" for the full sidebar) and Instellingen (personal settings, admin settings once). Right: Dit dashboard
  (the cog's actions with the cog's gates, Delete apart) and Launchpad (Store, Sjablonen, Rapporten, Flows,
  Koppelingen, Documentatie, Functies en roadmap). Instellingen and Launchpad only show while the navigation panel is
  left out, so nothing is offered twice. It is built on the library's `CnGroupedMenu`.
- A placement with `content.fillHeight: true` makes the dashboard fill the height (the library grid's `fillHeight`).
  A placement with `content.fitRows: true` keeps its rows at its content's height.
- The attention widget takes `layout: "columns"` (one row of up to 4, 3 by default) and `title`, drawn with the count
  beside it ("3 dingen uit al uw apps die vandaag aandacht vragen").
- A widget card takes a header link from `content.headerLink` ("Open agenda").
- Mijn werkdag version 5 follows the board: the header "Vandaag" with greeting, date and week under it and the
  emblem; "Vandaag eerst" in one row of three; the counters in one row; the apps as tiles (`app-tiles`) on the left,
  the agenda and "Verder waar u was" on the right. The "Mijn werk" and "Alle apps" labels and the links list go.

## What an instance that installed version 4 sees

The Templates page offers the update to version 5, and `occ launchpad:template:install mijn-werkdag --update` applies
it to the template and every member's copy (REQ-TMPL-020).

## Depends on

nextcloud-vue `lpvandaag-start-page`: `CnDashboardGrid` `fillHeight`, `CnHeaderWidget` `greetingLine`,
`CnAppTilesWidget`, `CnGroupedMenu`.
