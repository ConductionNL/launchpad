## ADDED Requirements

### Requirement: The start page fits the screen

A dashboard with a placement that sets `content.fillHeight: true` SHALL fill the height below the Nextcloud top bar on
a screen at least 960px wide, outside edit mode: the page SHALL NOT scroll, the rows of placements that set
`content.fitRows: true` SHALL take their content's height, the other rows SHALL share the rest, and a widget taller than
its share SHALL scroll inside its card. On a narrower screen the cards SHALL stack and the page MAY scroll. In edit mode
the grid SHALL be the editable grid as before.

#### Scenario: Mijn werkdag at 1440×900 and 1366×768

- **GIVEN** Mijn werkdag version 5
- **WHEN** an employee opens the start page at 1440×900 or 1366×768
- **THEN** the page's scroll height equals its height, and the Apps, Agenda vandaag and Verder waar u was cards fill
  the space under the counters

### Requirement: One Meer menu holds the start page's actions

The start page SHALL show "Startpagina aanpassen" and one "Meer" menu, and no other control for the dashboard's
actions. The menu SHALL hold, left, Dashboards (every dashboard the person sees, the open one checked, and "Alle
dashboards") and Instellingen (personal settings and, for an administrator only, the admin settings), and, right, Dit
dashboard (Bewerken, Instellingen, Widget toevoegen, Delen, Als standaard instellen, Versies, Inplannen, Publiceren or
Publicatie intrekken, Leesbevestigingen, and Verwijderen apart) and Launchpad (Store, Sjablonen, Rapporten, Flows,
Koppelingen, Documentatie, Functies en roadmap). Each action SHALL keep the gate it had in the dashboard cog menu.
Instellingen and Launchpad SHALL only show while the navigation panel is left out. No action SHALL appear twice.

#### Scenario: An administrator opens Meer

- **GIVEN** the start page without the navigation panel, for an administrator
- **WHEN** they open "Meer"
- **THEN** "Beheerinstellingen" appears once, and "Sjablonen" is in the Launchpad group

#### Scenario: A member opens Meer on a shared dashboard

- **GIVEN** a member on a dashboard shared with them
- **WHEN** they open "Meer"
- **THEN** Dit dashboard offers "Als standaard instellen", and neither Instellingen of the dashboard nor Verwijderen

### Requirement: Vandaag eerst is one row

The attention widget with `content.layout: "columns"` SHALL draw its items side by side in one row of
`content.columns` (1 to 4, 3 by default), each with its title, reason and app on the left and its action on the right.
With `content.title` it SHALL draw that heading with the number of items it shows beside it. On a narrow screen the
items SHALL stack.

#### Scenario: Three things today

- **GIVEN** three apps report something that needs attention
- **THEN** "Vandaag eerst" reads "3 dingen uit al uw apps die vandaag aandacht vragen" and shows the three side by side
