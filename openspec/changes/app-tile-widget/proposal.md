# Proposal: app-tile-widget

## Why

Ruben reviewed the start page on 22 November 2026. Every app should be its own tile on the grid, like a workspace
start page: a square in a solid colour with the app's icon large and white in the middle. Today the add flow places a
discovered app as a plain tile with a copied title, icon and link, and the first dashboard gets one Apps widget that
holds all apps in one card.

## What changes

- The `app-tile` widget type from nextcloud-vue (`CnAppTileWidget`) is offered and rendered. It is configured with one
  app id and reads the app's name, icon and address from the viewer's own app menu, so an app the viewer may not open
  never shows as a link.
- "Discovered on this server" in the add flow places a picked app as an `app-tile`, one entry per app.
- An app tile renders without the card frame or header and starts at two columns by four rows.
- The first-run starter set places an app tile for each of up to four most-used apps in the person's app menu,
  instead of the one Apps widget. Without a known app menu it keeps the Apps widget.
- The `app-tiles` widget keeps working for existing dashboards and stays in the picker under "Widgets from Launchpad".

## Out of scope

- The visual design of the tile itself lives in nextcloud-vue (`openspec/changes/app-tile`).
