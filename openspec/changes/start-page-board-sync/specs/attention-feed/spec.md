# Delta: attention-feed

## ADDED Requirements

### Requirement: Vandaag eerst can draw one card per record (REQ-ATT-007)

An attention widget with `content.itemCards: true` SHALL ask every source for up to `content.limit` records instead of
one count, and SHALL draw one card per record: the record's own name as the title, the source's title as the reason
("De beslistermijn loopt vandaag af"), the app that owns it, and one action that opens the record, named by the
source ("Zaak openen", "Beantwoorden", "Paraferen") or "Open {item}" when the source names none. The cards SHALL keep
the merge and ranking order of REQ-ATT-004 and SHALL stop at `content.limit` in total. Without `itemCards` the widget
SHALL draw one item per source with its count, as before.

#### Scenario: Two cases due today

- **GIVEN** dossiq reports two cases whose decision term ends today, and the widget sets `itemCards: true` and `limit: 3`
- **WHEN** Pieter opens the start page
- **THEN** Vandaag eerst shows two cards, each named by its case, each with "De beslistermijn loopt vandaag af" and "Zaak openen"
- **AND** "Zaak openen" opens that case in dossiq

#### Scenario: Without item cards

- **GIVEN** the same feed and a widget without `itemCards`
- **WHEN** the widget renders
- **THEN** it shows one item for the dossiq source with the count 2
