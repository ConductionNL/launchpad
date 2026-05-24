---
status: proposed
---

# Integration: Polls

## Purpose

Link NC Polls to OR objects through the registry. Surfaces poll status, tally, and user's own vote.

**Standards**: NC Polls API, ADR-019
**Cross-references**: [generic-integrations](../../../pluggable-integration-registry/specs/generic-integrations/spec.md)

---

## ADDED Requirements

### Requirement: Polls Provider Registration

`PollsProvider` SHALL be registered with id='polls', group='workflow', requiredApp='polls', storage='link-table'.

#### Scenario: Present when Polls installed

- **GIVEN** the NC Polls app is installed
- **WHEN** `IntegrationRegistry::getEnabled()` is called
- **THEN** exactly ONE provider with id='polls' MUST be included

#### Scenario: Hidden when Polls absent

- **GIVEN** the NC Polls app is NOT installed
- **WHEN** the registry resolves enabled providers
- **THEN** the `polls` provider MUST NOT appear in the enabled set

---

### Requirement: Poll Lifecycle Display

The tab SHALL show each poll's status (draft/open/closed), vote tally per option, and the current user's own vote.

#### Scenario: Closed poll shows final tally

- **GIVEN** a linked poll with status=closed and tally {yes:7, no:3, abstain:2}
- **WHEN** `CnPollsTab` renders
- **THEN** the row MUST show the deadline-elapsed "Closed" marker
- **AND** per-option counts with percentages MUST be rendered

#### Scenario: User's own vote highlighted

- **GIVEN** the current user voted "yes" on a linked poll
- **WHEN** the tab renders
- **THEN** the user's vote MUST be visually distinguished

---

### Requirement: Widget Surfaces

Per umbrella AD-6/AD-18, `CnPollsCard` SHALL render on all four surfaces (`user-dashboard`, `app-dashboard`, `detail-page`, `single-entity`); the `detail-page` rendering MUST include a mini bar-chart tally.

#### Scenario: Detail-page renders option bars

- **GIVEN** two linked polls each with three options
- **WHEN** `CnPollsCard` renders with `surface='detail-page'`
- **THEN** the widget MUST render two rows
- **AND** six mini option bars MUST be present (three per row)

#### Scenario: Dashboard renders count headline

- **GIVEN** two linked polls (one open, one closed)
- **WHEN** `CnPollsCard` renders with `surface='user-dashboard'`
- **THEN** the headline MUST surface both the total count and the open count

---

### Requirement: Reference-Property Auto-Rendering

`referenceType: 'polls'` SHALL render `CnPollsCard` at `surface='single-entity'`.

#### Scenario: Chip surfaces leading option

- **GIVEN** a schema property carrying `referenceType: 'polls'` whose value is a poll id
- **WHEN** the property is rendered via `CnDetailGrid` / `CnFormDialog`
- **THEN** a chip MUST appear with the poll title
- **AND** the leading option fragment (label + percentage) MUST be shown when option results are present

---

### Requirement: Permission Inheritance

`PollsProvider::requiresPermission()` SHALL return `null`; Polls' own ACLs apply.

#### Scenario: No extra permission check

- **GIVEN** any authenticated user requesting the polls sub-resource for an object
- **WHEN** `PollsProvider::list()` is called
- **THEN** the provider MUST NOT enforce an OR-side permission
- **AND** the visible polls MUST be those that NC Polls itself exposes to the user

---

### Requirement: Graceful Degradation

The provider SHALL conform to the umbrella's Error-Handling Contract. When an underlying poll in NC Polls is missing, inaccessible, or the backing service is down, the provider SHALL surface the documented exception types rather than leaking generic errors.

#### Scenario: Closed poll remains accessible after closure

- **GIVEN** a linked poll has been closed in NC Polls (status=closed)
- **WHEN** `CnPollsTab` renders
- **THEN** the poll MUST still be shown with final tally and `status='closed'`
- **AND** vote affordances MUST NOT be rendered

#### Scenario: Deleted poll filters out of list

- **GIVEN** a linked poll was deleted from NC Polls
- **WHEN** `CnPollsTab` renders
- **THEN** the orphaned link MUST be filtered out with a log warning for admin reconciliation
