---
kind: code
depends_on: []
chain: []
---

# Proposal: own-look-fields

## Why

Ruben's review of 27 November 2026, item 17: in the dashboard settings, block Own look (DashboardConfigModal, board
LpDashboardInstellingen), the colour swatch wrapped under its field, and "Image" was a bare browser file input
("Bestand kiezen / Geen bestand gekozen") that did not say what the image is for. The same pattern held for every
colour field and image upload in launchpad.

launchpad#2214 built the fix and it was checked live on 27 Nov 2026. This change writes the rule down, so the pixel pass
and the e2e suite hold it, and so new colour or image fields follow it.

## What changes

- `dashboards` REQ-THM-005: every colour field shows its picker beside its input on one row.
- `dashboards` REQ-THM-006: the dashboard's background image is a labelled, styled upload with a preview and a remove
  action.

## Out of scope

- What the look does to the dashboard (REQ-THM-001).

## Boards

LpDashboardInstellingen.
