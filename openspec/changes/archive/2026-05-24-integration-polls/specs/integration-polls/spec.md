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
- **AND** its required app MUST be reported as `'polls'`

#### Scenario: Hidden when Polls absent

- **GIVEN** the NC Polls app is NOT installed
- **WHEN** the registry resolves enabled providers
- **THEN** the `polls` provider MUST NOT appear in the enabled set
- **AND** `health()` MUST report `status='unavailable'`

---

### Requirement: Poll Lifecycle Display

The tab SHALL show each poll's status (draft/open/closed), vote tally per option, and the current user's own vote.

#### Scenario: Closed poll shows final tally

- **GIVEN** a linked poll with status=closed and tally {yes:7, no:3, abstain:2}
- **WHEN** `CnPollsTab` renders
- **THEN** the row MUST show the deadline-elapsed "Closed" marker
- **AND** per-option counts with percentages MUST be rendered (e.g. "Yes 7 (58%)")

#### Scenario: Open poll shows deadline countdown

- **GIVEN** a linked poll whose deadline is 3 days in the future
- **WHEN** `CnPollsTab` renders
- **THEN** the row MUST include a "Closes in 3 days" indicator
- **AND** per-option progress bars MUST be rendered

---

### Requirement: Widget Surfaces

`CnPollsCard` SHALL render on all four surfaces (`user-dashboard`, `app-dashboard`, `detail-page`, `single-entity`); the `detail-page` rendering MUST include a per-row mini option bar set.

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

`referenceType: 'polls'` SHALL render `CnPollsCard` at `surface='single-entity'` showing poll title + leading option.

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

The provider SHALL conform to the umbrella's Error-Handling Contract. When NC Polls is missing or unreachable, the provider SHALL surface a `503` response that the bespoke Vue components MUST translate into an "unavailable" banner.

#### Scenario: Provider returns 503 when Polls is down

- **GIVEN** the NC Polls app is enabled but the backing query raises a `Throwable`
- **WHEN** the OR integrations controller serves `/integrations/polls`
- **THEN** the response MUST be HTTP 503
- **AND** `CnPollsTab` / `CnPollsCard` MUST render the unavailable banner instead of throwing

#### Scenario: Closed poll remains accessible after closure

- **GIVEN** a linked poll has been closed in NC Polls (deadline elapsed)
- **WHEN** `CnPollsTab` renders
- **THEN** the poll MUST still be shown with its final tally
- **AND** the row MUST be marked closed (no vote affordances)
