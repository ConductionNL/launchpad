---
kind: code
depends_on: []
chain:
  - add-widget-dialog-with-miniatures   # this change
  - app-link-tile-widget
  - layout-widgets-replace-multi-tile
---

# Proposal: add-widget-dialog-with-miniatures

## Why

Ruben's review of 27 November 2026:

- Item 9: a tile is a widget. The separate "Add tiles" button goes; tiles are added through Add widget, kind "Tiles and
  links". Where the tile flow had extra steps (LpTegelToevoegen), they live inside the Add widget dialog.
- Item 11: the Add widget dialog is wider, and each widget shows a small thumbnail: an atomic-design miniature of the
  widget, a scaled-down live rendering built from the widget's real components with sample data, not a static image,
  so the thumbnail always matches the widget.

Board LpWidgetKiezen (design-system PR 1212) draws the dialog 1120 px wide (was 720) with a miniature above every
card's icon, title and line, and a new kind Layout (nl Indeling) for Container and Tabs.

This is change 1 of 3 in the widget catalogue chain. Change 2 (`app-link-tile-widget`) puts the App/Link tile form,
with the former tile steps, inside this dialog; change 3 (`layout-widgets-replace-multi-tile`) adds the tabs widget and
removes the multi-tile widget.

## What changes

- `widgets` REQ-WDG-040: the dialog is 1120 px wide and every card carries a miniature.
- `widgets` REQ-WDG-041: the kinds gain Layout (Container, Tabs once change 3 lands).
- `widgets` REQ-WDG-048: the miniature is the widget's own renderer with sample data, scaled down.
- `widgets` REQ-WDG-049: Add widget is the only way to add anything; there is no Add tiles button.

## Out of scope

- The App/Link tile and its form (change 2), the tabs widget and the multi-tile removal (change 3).

## Boards

LpWidgetKiezen, LpBewerken (no Add tiles button), LpTegelToevoegen (the tile form inside the dialog, built in change 2).
