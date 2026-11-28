# lpvandaag-start-page Specification

## Purpose
The employee's start page (board `launchpad/LpVandaag`) in one screen: the rows share the height under the top bar
without the page scrolling, one Meer menu holds the start page's actions, and Vandaag eerst is one row.

## Requirements

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

The start page SHALL show one control in its header: the menu "Meer", the same menu as on every other page
(runtime-shell REQ-SHELL-013, board LpMeer). There SHALL be no "Startpagina aanpassen" button, no Bewerken button and
no other control for the dashboard's actions. Meer SHALL hold, left, Dashboards (every dashboard the person sees, the
open one checked, the markers standaard, gedeeld and groep, and "Alle dashboards"), and right, Dit dashboard
(Bewerken, Widget toevoegen, Delen, Link naar dit dashboard kopiëren, Kopie maken als nieuw dashboard, Als standaard
instellen, and Verwijderen last), with below it the folding item Instellingen (Persoonlijke instellingen,
Beheerinstellingen for an administrator only, Marktplaats, Sjablonen, Rapporten, Flows, Koppelingen, Documentatie,
Functies en roadmap). Versies, publishing, its schedule and the scheduled exports are tabs of the dashboard settings,
not menu items. Each action SHALL keep its gate. No action SHALL appear twice.

#### Scenario: An administrator opens Meer

- **GIVEN** the start page, for an administrator
- **WHEN** they open "Meer" and unfold Instellingen
- **THEN** "Beheerinstellingen" appears once, under Instellingen, and "Sjablonen" is in the same group
- **AND** the header shows no "Startpagina aanpassen" button

#### Scenario: A member opens Meer on a shared dashboard

- **GIVEN** a member on a dashboard shared with them
- **WHEN** they open "Meer"
- **THEN** Dit dashboard offers "Als standaard instellen", and neither Verwijderen nor Versies

### Requirement: Vandaag eerst is one row

The attention widget with `content.layout: "columns"` SHALL draw its items side by side in one row of
`content.columns` (1 to 4, 3 by default), each with its title, reason and app on the left and its action on the right.
With `content.title` it SHALL draw that heading with the number of items it shows beside it. On a narrow screen the
items SHALL stack.

#### Scenario: Three things today

- **GIVEN** three apps report something that needs attention
- **THEN** "Vandaag eerst" reads "3 dingen uit al uw apps die vandaag aandacht vragen" and shows the three side by side

### Requirement: The start page greets the reader

A header placement with `content.greeting: true` SHALL open with a greeting for the part of the day ("Goedemorgen"
before 12:00, "Goedemiddag" before 18:00, "Goedenavond" after; in English "Good morning", "Good afternoon", "Good
evening") and the reader's first name, followed by today's date in the reader's language. With `content.showWeek:
true` it SHALL add the week number. The greeting SHALL use the reader's display name and time zone, and SHALL be text,
not an image, so a screen reader reads it.

#### Scenario: Pieter opens the start page after lunch

- **GIVEN** Pieter Jansen, language Dutch, opens the start page on Monday 5 October 2026 at 13:10
- **WHEN** the header renders with `greeting` and `showDate`
- **THEN** it reads "Goedemiddag, Pieter · maandag 5 oktober 2026"

#### Scenario: The week number

- **GIVEN** the header sets `showWeek: true`
- **WHEN** Pieter opens it on Monday 5 October 2026
- **THEN** the header also shows "week 41"
