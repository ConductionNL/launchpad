# Delta for dashboard-metadata-fields: details tab, field editor and filter

## ADDED Requirements

### Requirement: An administrator defines detail fields (REQ-MDUI-001)

The administration settings MUST let an administrator create, edit and delete detail fields of type text, number, date, select and multi-select, with options for the two select types, and MUST refuse a select field without options with the service message.

#### Scenario: Department field

- **GIVEN** Ruben is a Nextcloud administrator
- **WHEN** he adds a select field "Department" with options "HR" and "Finance"
- **THEN** the field is listed and appears on every dashboard's Details tab

#### Scenario: Select without options

- **GIVEN** the same administrator
- **WHEN** he saves a select field with no options
- **THEN** the form shows the service message and nothing is saved

### Requirement: A dashboard owner fills in the details (REQ-MDUI-002)

The dashboard settings dialog MUST have a "Details" tab showing one control per defined field and saving the values for that dashboard.

#### Scenario: Set the owning department

- **GIVEN** the field "Department" exists and Sanne owns "Payroll"
- **WHEN** she selects "Finance" on the Details tab and saves
- **THEN** reopening the dialog shows "Finance"

### Requirement: Dashboards can be filtered by a detail (REQ-MDUI-003)

The dashboards list endpoint MUST pass `metadata.<key>` query parameters to the metadata filter, and the switcher MUST offer "Filter by detail" when at least one field can be filtered.

#### Scenario: Filter to Finance

- **GIVEN** "Payroll" has Department Finance and "Onboarding" has Department HR
- **WHEN** Pieter filters the switcher by Department Finance
- **THEN** only "Payroll" is listed

