# Delta for live-data-tile-widget: lists, tables and field mapping

## ADDED Requirements

### Requirement: A live tile can show a list, a table or key-value pairs (REQ-LTC-001)

A live tile MUST accept a display of `value` (the default), `list`, `table` or `keyvalue`, a mapping of up to six fields each with a label, a path and a role (title, link, date or field), and a limit between 1 and 50. A tile saved before this change MUST keep rendering its single value.

#### Scenario: Ticket list from a connected system

- **GIVEN** Integriq holds a source "TOPdesk" with stored credentials and Ella adds a live tile in connector mode with source "TOPdesk", expression `$.incidents`, display "List", limit 5, and mapping title `briefDescription`, link `url`, date `callDate`
- **WHEN** Pieter opens the Workspace page
- **THEN** the tile lists five incidents, each title linking to the incident

#### Scenario: Existing single-value tile

- **GIVEN** a live tile saved last month with `valueExpr: "$.open"` and no display
- **WHEN** it renders after this change
- **THEN** it shows the single value as before

### Requirement: Mapping happens on the server and drops unmapped data (REQ-LTC-002)

The server MUST evaluate the mapping, return only mapped fields, truncate to the limit, and cache the mapped result. The raw response MUST NOT be sent to the browser.

#### Scenario: Personal data in the payload stays on the server

- **GIVEN** a source whose items include `callerEmail`, and a mapping without it
- **WHEN** the tile's data is requested
- **THEN** the response items hold only the mapped fields and no `callerEmail`

### Requirement: Only safe links are clickable (REQ-LTC-003)

A mapped link MUST render as a link only when it is an `https` address, opening in a new tab; any other value MUST render as plain text.

#### Scenario: Script link shown as text

- **GIVEN** an item whose mapped link is `javascript:alert(1)`
- **WHEN** the list renders
- **THEN** the title shows as plain text without a link

### Requirement: A preset shows another intranet's latest documents (REQ-LTC-004)

The tile form MUST offer a "Latest documents" preset with SharePoint and Confluence variants that fill a list display with title, link, last modified and modified by, for the author to confirm.

#### Scenario: SharePoint library

- **GIVEN** Integriq holds a source "Intranet documenten" for a SharePoint document library through Microsoft Graph
- **WHEN** Ella picks the preset "Latest documents", variant SharePoint, and the source, and saves
- **THEN** the tile lists the ten latest documents with title, last modified date and editor, and each title opens the document in SharePoint
