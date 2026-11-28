# Proposal: version-change-notes

## Why

Board LpVersies draws the dashboard settings on the tab Versies: a table "Wanneer, Door, Wat veranderde" with rows such
as "Widget Nieuws verplaatst", "Widget Verjaardagen verwijderd", "Kolommen van 8 naar 12" and, by "Sjabloon
Intranet", "Bijgewerkt vanuit het sjabloon"; the current version marked "Huidige versie"; and a confirmation that
names what comes back ("Met deze versie komt de widget Verjaardagen terug en verdwijnen de wijzigingen van daarna").

- The "What changed" note of an automatic snapshot is built (#1611, `SnapshotChangeDescriber`) and tagged to the
  versioning spec as a whole, without a requirement.
- A template re-sync does not note that the copy was updated from the template, nor by which template.
- REQ-VERSUI-001 still describes a "Version history" entry in the dashboard menu, which REQ-DASH-039 and runtime-shell
  REQ-SHELL-013 replaced with the Versions tab.

## What changes

- `dashboard-versioning`: REQ-VERSUI-001 describes the Versions tab and its columns; REQ-VERSUI-004 specs the "What
  changed" note; REQ-VERSUI-005 specs the note and author of a version written by a template re-sync.

## Impact

- `SnapshotChangeDescriber` and `DashboardVersionService` (tags and the re-sync note), the re-sync service, the
  Versions tab, translations.
