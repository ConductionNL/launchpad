# Delta: dashboards

## MODIFIED Requirements

### Requirement: A reader can download the dashboard (REQ-DPD-003)

This dashboard in the More menu SHALL offer "Download" (nl "Downloaden"), between Duplicate as new dashboard and Set as
default (board LpMeer), to everyone who can open the dashboard, also with view-only rights. The page header SHALL NOT
carry a Download button (Ruben's review of 27 Nov 2026, item 8). Download SHALL give the reader the dashboard as a PDF,
rendered as the reader sees it now, with the period and the cross-widget filter applied and the reader's own visibility
rules respected, rendered in the reader's own browser (print styles and the browser's print dialog; decision 24 Nov
2026), not on the server. Nothing SHALL be stored or sent. A widget the renderer cannot draw (an embedded page) SHALL be
named in the PDF with a line that it is not included. The kiosk view and the public view SHALL NOT offer it.

#### Scenario: Pieter downloads the quarter

- **GIVEN** Pieter has view-only rights on Managementinformatie, with Kwartaal chosen
- **WHEN** he opens More and chooses Downloaden under Dit dashboard
- **THEN** his browser saves a PDF of Managementinformatie with the quarter's figures
- **AND** no scheduled export or run is created

#### Scenario: The embedded report

- **GIVEN** the dashboard holds the Power BI embed "Rapport Sociaal domein"
- **WHEN** Pieter downloads it
- **THEN** the PDF names "Rapport Sociaal domein" with a line that embedded pages are not included

#### Scenario: No Download in the header

- **GIVEN** a member on any dashboard
- **WHEN** the page renders
- **THEN** the header MUST NOT show a Download button, and This dashboard in More MUST list Download
