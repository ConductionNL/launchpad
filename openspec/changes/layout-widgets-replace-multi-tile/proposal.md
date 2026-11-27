---
kind: code
depends_on:
  - app-link-tile-widget
chain:
  - add-widget-dialog-with-miniatures
  - app-link-tile-widget
  - layout-widgets-replace-multi-tile   # this change
---

# Proposal: layout-widgets-replace-multi-tile

## Why

Ruben's review of 27 November 2026, item 13: drop the multi-tile widget ("App tiles", "Tiles for several apps at
once", type `app-tiles`). Tiles are clustered by putting App or link tiles in a well (Container) or a Tabs widget. Every
existing placement of the multi-tile widget becomes a well holding one App or link tile per former tile, same order and
settings, so no dashboard loses tiles. The multi-tile widget leaves the Add widget catalogue.

Board LpWidgetKiezen draws Container and Tabs (nl Tabbladen) under the kind Layout. Launchpad has no Tabs widget
yet.

The start page Vandaag (REQ-DWB-008) and the first-dashboard starter set (REQ-APPTILE-003) place the multi-tile widget
today; both change with it.

This is change 3 of 3 in the widget catalogue chain.

## What changes

- New capability `tabs-widget` (REQ-TABS-001 to -005): a widget with named tabs, each holding widgets like a well.
- `container-widget` REQ-CONT-008: inside a well, a tile for an app the viewer may not open is left out for that viewer.
- `app-tile-widget`: REQ-APPTILE-003 (starter set) uses App or link tiles only; REQ-APPTILE-007 removes `app-tiles`
  from the catalogue and migrates every placement into a well.
- `default-widget-bundle` REQ-DWB-008: the start page's "My apps" block is a well of App or link tiles.

## Out of scope

- The App or link tile itself (change 2).

## Boards

LpWidgetKiezen (Container and Tabs under Layout), LpVandaag (My apps), LpTegelToevoegen (bookmark folders become wells).
