# Proposal: people-new-colleagues

## Why

Board LpIntranet draws, under the heading Collega's ("Wie nieuw is, wie jarig is en wie wat weet"), a list "Nieuwe
collega's": Yasmina El Amrani, Thomas Brouwer and Lotte Verhoeven, each with photo, role and team. The kiosk board says
"Welkom Yasmina, Thomas en Lotte", the demo seed has Yasmina "starts next Monday", and the picker describes the people
widget as "Een team of nieuwe collega's met foto" (board LpWidgetKiezen).

The people widget selects people by hand or by filter, and shows birthdays; nothing knows when someone started.
Custom profile fields (REQ-PEX-001) are text or tags only.

## What changes

- `people-widget`: a custom profile field can be a date, and one date field can be marked as the start date
  (REQ-PEX-005); the widget gets a selection "New colleagues" that lists people whose start date falls in a window
  around today (REQ-PPL-014).
- Where the start date comes from is configuration (filled by an administrator, read from LDAP, or filled by the
  person), like every custom field.

## Impact

- Custom profile field admin form and storage (date type, start-date marker), the users endpoint (filter and sort by
  start date), the people widget form and renderer, translations.
