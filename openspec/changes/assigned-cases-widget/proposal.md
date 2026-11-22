# Proposal: assigned-cases-widget

## Why

Board LpWerkplek (design-system #1192) shows "Mijn zaken": the counts per status of the person's open cases and the
first four cases with deadline and status. Board LpAfdeling repeats it on the department page.

## What changes

- An `assigned-cases` widget type, "My cases" in the picker. It reads the open cases assigned to the viewer from an
  OpenRegister register with the viewer's own session (`GET /apps/openregister/api/objects/{register}/{schema}` with
  `assignee=<uid>`, `_order[deadline]=asc`, `_extend[]=status`), so OpenRegister decides which cases they may see.
  The register and schema default to dossiq's `dossiq` and `case`; the form names them.
- Counts per status above the cases, over the first 100 open cases read; the cases through `CnStatusList` with the case
  number, deadline (overdue once passed) and status in the colour of its status type.
- Each case links into dossiq at `/apps/<id>/cases/<uuid>`, where `<id>` is the id dossiq is installed under. The
  initial state's `integrationApps.dossiq` carries that id, resolved through `FleetAppId`, so launchpad never names one
  spelling of dossiq's id.
- A widget that reads through an app not enabled for the viewer is left out of the grid outside edit mode, and out of
  the picker. This applies to every type with `requiresApp`.

## Decisions

- The widget is generic: it shows "cases assigned to me" from any register whose objects carry `assignee`,
  `deadline`, `status` and `isFinalStatus`. Nothing in it is named after a procedure.
- Counts come from the cases read, not from OpenRegister facets, because a facet needs the schema's facet
  configuration and the counts of a person's own open cases fit in one read.
