# assigned-cases-widget Specification

## Purpose
The open cases assigned to the person viewing, with counts per status, on a dashboard.

## Requirements

### Requirement: The widget lists the viewer's open cases, deadline first (REQ-CASES-001)

The `assigned-cases` widget SHALL read the cases assigned to the viewer from the configured OpenRegister register and
schema with the viewer's own session, leave out cases with a final status, and list the first `limit` (default 4),
deadline first. Each case SHALL show its title as a link into the case app, its case number, its deadline (overdue
once passed) and its status in the colour of its status type. When there are more open cases than shown, a link SHALL
lead to the case list.

#### Scenario: Pieter's cases

- **GIVEN** Pieter has open cases "Kapvergunning Molenweg 4" (In behandeling, deadline today) and "Dakkapel
  Kerkstraat 12" (Wacht op aanvrager, 21 October)
- **WHEN** he opens a dashboard with "My cases"
- **THEN** both show, the one due today first, each with case number, deadline and status

### Requirement: The widget counts the open cases per status (REQ-CASES-002)

Above the cases the widget SHALL show one count per status over the viewer's open cases.

#### Scenario: Counts

- **GIVEN** Pieter has 6 open cases "In behandeling" and 5 "Wacht op aanvrager"
- **THEN** the widget shows "In behandeling 6" and "Wacht op aanvrager 5"

### Requirement: The widget names its data source (REQ-CASES-003)

The form SHALL name the register and the schema the widget reads, by default dossiq's `dossiq` and `case`, and the
number of cases, 1 to 20.

#### Scenario: Default source

- **WHEN** an author adds "My cases"
- **THEN** the form shows register `dossiq` and schema `case`

### Requirement: The widget is offered only with dossiq (REQ-CASES-004)

Launchpad SHALL find dossiq under every id it has had and never by one spelling. The picker SHALL offer "My cases" only
when dossiq is enabled for the viewer. Outside edit mode a placed widget, and every other widget whose app is not
enabled for the viewer, SHALL be left out of the grid. Links SHALL use the id dossiq is installed under.

#### Scenario: No dossiq

- **GIVEN** an instance without dossiq
- **WHEN** a person opens a dashboard holding "My cases"
- **THEN** the widget is not shown, and the picker does not offer "My cases"
