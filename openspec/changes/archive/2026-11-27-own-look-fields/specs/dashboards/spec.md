# Delta: dashboards

## ADDED Requirements

### Requirement: A colour field shows its picker beside its input (REQ-THM-005)

Every colour field in Launchpad, the accent and background colour of Own look (board LpDashboardInstellingen) among them,
SHALL show its labelled input and its colour picker on one row, the picker showing the current colour beside the input;
they SHALL NOT wrap at the width of the dialog they are in. Under 480 px they MAY stack. In Own look, as the board draws it,
the block is an inset card, each colour field has its label above it and its swatch left of the hex field, and the accent,
the background colour and the background image stand side by side.

#### Scenario: The accent field

- **GIVEN** the owner opens the dashboard settings, tab Details, at 1440 px
- **WHEN** Own look renders
- **THEN** the accent colour's input and its picker stand on one row, and so do the background colour's

### Requirement: The background image is a labelled upload with a preview (REQ-THM-006)

Own look SHALL offer the dashboard's background image as "Background image" (nl "Achtergrondafbeelding") with the hint
"Shown behind this dashboard; leave empty to follow the theme", a styled upload button in the app's language instead of
the browser's bare file input, and, once an image is set, a small preview with a Remove action. Removing it SHALL make
the dashboard follow the theme again. Every image upload in Launchpad SHALL follow this pattern.

#### Scenario: Set and remove a background image

- **GIVEN** the owner in Own look
- **WHEN** they upload an image with the upload button and save
- **THEN** a preview of the image shows beside Remove, and the dashboard shows it as its background
- **AND** after Remove and save, the dashboard follows the theme
