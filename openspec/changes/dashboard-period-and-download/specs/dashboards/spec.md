# Delta: dashboards

## ADDED Requirements

### Requirement: The header says what dashboard this is and what the reader may do (REQ-DPD-001)

Under the dashboard title the header SHALL show one line: the kind of dashboard with its icon ("Persoonlijk
dashboard", "Groepsdashboard van {group}", "Gedeeld door {person}"), what the reader may do on it ("alleen bekijken",
"u mag widgets toevoegen", or nothing extra with full rights), "opent als eerste" when it is the reader's default, and
when it last changed ("bijgewerkt 10:45" today, the weekday within the last week, the date before that), separated by
" · ".

#### Scenario: A view-only group dashboard

- **GIVEN** Pieter has view-only rights on the group dashboard Managementinformatie of Bedrijfsvoering, last changed today at 10:45
- **WHEN** he opens it
- **THEN** the line under the title reads "Groepsdashboard van Bedrijfsvoering · alleen bekijken · bijgewerkt 10:45"

#### Scenario: Pieter's own default dashboard

- **GIVEN** Mijn werkplek is Pieter's personal default dashboard
- **WHEN** he opens it
- **THEN** the line reads "Persoonlijk dashboard · opent als eerste" followed by when it last changed

### Requirement: A dashboard can have a period that its figures follow (REQ-DPD-002)

A widget whose data source is a register query MAY name the date field of its records in `content.source.periodField`.
When at least one widget on a dashboard names one, the header SHALL show a period choice: This month, Quarter, Year
(nl Deze maand, Kwartaal, Jaar), This month first. The chosen period SHALL be added to the query of every widget that
names a period field, as that field between the period's start and now, and SHALL be kept in the address
(`?period=month|quarter|year`) so a copied link opens with the same period. A widget without a period field SHALL
ignore the period, and its card SHALL NOT claim to follow it. The period SHALL work alongside the cross-widget filter
(cross-widget-filter) and SHALL be read-only: choosing it never changes the stored dashboard.

#### Scenario: Pieter looks at the quarter

- **GIVEN** Managementinformatie with "Zaken per status" whose source names `periodField: "startDate"`, and a Power BI embed
- **WHEN** Pieter chooses Kwartaal
- **THEN** "Zaken per status" counts only cases started this quarter
- **AND** the address carries `period=quarter`
- **AND** the Power BI embed is unchanged

#### Scenario: A dashboard without dated sources

- **GIVEN** a dashboard on which no widget names a period field
- **WHEN** it renders
- **THEN** the header shows no period choice

### Requirement: A reader can download the dashboard (REQ-DPD-003)

The header SHALL offer "Download" (nl "Downloaden") to everyone who can open the dashboard, also with view-only
rights. It SHALL give the reader the dashboard as a PDF, rendered as the reader sees it now, with the period and the
cross-widget filter applied and the reader's own visibility rules respected, through the renderer that scheduled
exports use. Nothing SHALL be stored or sent. A widget the renderer cannot draw (an embedded page) SHALL be named in the
PDF with a line that it is not included. The kiosk view and the public view SHALL NOT offer it.

#### Scenario: Pieter downloads the quarter

- **GIVEN** Pieter has view-only rights on Managementinformatie, with Kwartaal chosen
- **WHEN** he chooses Downloaden
- **THEN** his browser saves a PDF of Managementinformatie with the quarter's figures
- **AND** no scheduled export or run is created

#### Scenario: The embedded report

- **GIVEN** the dashboard holds the Power BI embed "Rapport Sociaal domein"
- **WHEN** Pieter downloads it
- **THEN** the PDF names "Rapport Sociaal domein" with a line that embedded pages are not included
