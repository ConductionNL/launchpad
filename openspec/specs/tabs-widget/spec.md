# tabs-widget Specification

## Purpose
A Tabs widget holds widgets on named tabs inside one dashboard cell, as the well does without tabs; it replaces clustering by the retired multi-tile widget.

## Requirements

### Requirement: The Tabs widget is in the catalogue (REQ-TABS-001)

The widget registry SHALL include a `tabs` widget type named "Tabs" (nl "Tabbladen"), in the kind Layout of Add widget
(board LpWidgetKiezen), whose `defaultContent` is two tabs titled "Tab 1" and "Tab 2" without widgets. Like the well,
it groups other widgets; tiles are grouped with it or with a well (Ruben's review of 27 Nov 2026, item 13).

#### Scenario: Add Tabs

- **GIVEN** the owner in edit mode
- **WHEN** they choose Add widget, kind Layout, Tabs, and Add widget
- **THEN** a Tabs widget with "Tab 1" and "Tab 2" is placed in the draft

### Requirement: Each tab holds widgets (REQ-TABS-002)

Each tab SHALL hold its own widgets on an inner grid with the well's grid constants (container-widget REQ-CONT-002),
rendered through the same widget dispatcher (REQ-CONT-003), so a tab can hold any widget type, App or link tiles
included. Only the open tab's widgets SHALL render; the others SHALL load when their tab opens. In edit mode the open
tab's grid SHALL be editable as a well's is.

#### Scenario: Tiles in two tabs

- **GIVEN** a Tabs widget with "Werk" holding two app tiles and "Samenwerken" holding one link tile
- **WHEN** Pieter opens "Samenwerken"
- **THEN** the link tile shows, and the two app tiles do not

### Requirement: The tabs follow the WAI-ARIA tabs pattern (REQ-TABS-003)

The tab row SHALL be a `tablist` of `tab`s, each controlling its `tabpanel`. Left and Right SHALL move to the previous
and next tab and open it, Home and End SHALL go to the first and last, and only the open tab SHALL be in the tab order.
The open tab SHALL be marked with more than colour alone.

#### Scenario: Move with the keyboard

- **GIVEN** focus on the tab "Werk"
- **WHEN** Pieter presses Right
- **THEN** "Samenwerken" is open and has focus, with `aria-selected="true"`

### Requirement: The Tabs form names and orders the tabs (REQ-TABS-004)

The widget's settings SHALL list the tabs, each with its title, and SHALL let the editor add a tab, rename it, move it up
or down and remove it. Removing a tab that holds widgets SHALL ask first and name how many widgets go with it. A Tabs
widget SHALL keep at least one tab.

#### Scenario: Remove a tab with widgets

- **GIVEN** the tab "Samenwerken" holds one link tile
- **WHEN** the editor removes it
- **THEN** the form asks "Remove the tab and its 1 widget?" before removing it

### Requirement: Tabs and wells nest at most three deep (REQ-TABS-005)

A Tabs widget SHALL count as one level of the well's depth limit (container-widget REQ-CONT-006): wells and tabs inside
each other SHALL go at most three levels deep, and the picker SHALL refuse to place a fourth.

#### Scenario: A fourth level is refused

- **GIVEN** a well inside a Tabs widget inside a well
- **WHEN** the editor tries to add Tabs inside the inner well
- **THEN** the picker says the maximum depth is reached and places nothing
