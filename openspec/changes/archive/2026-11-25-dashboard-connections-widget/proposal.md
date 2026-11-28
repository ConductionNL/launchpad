# Proposal: dashboard-connections-widget

## Why

Board LpManagement ends with a card "Koppelingen van dit dashboard": the outside connections this dashboard's widgets
use, each with its state, its last success and its last error. The parity row int-connections-status points at
REQ-LP-CONN-004, which is an admin page of all launchpad connections under settings, not a card on a dashboard. The
demo seed fills the card with a text widget, because no widget type can show it.

## What changes

- `app-connections`: a `connections` widget type, "Koppelingen van dit dashboard" (REQ-LP-CONN-005). It lists the
  declared connections (lib/Settings/connections.json) that the widgets on its own dashboard use, with the status,
  last success and last error integriq holds for each.
- Which widget uses which connection is a mapping in configuration next to the declaration, not code per widget type.

## Impact

- A widget renderer and form, a `uses` list per declared connection (widget types), the picker entry, translations.
- Without integriq the widget says the connections cannot be shown; it never calls a connection itself.
