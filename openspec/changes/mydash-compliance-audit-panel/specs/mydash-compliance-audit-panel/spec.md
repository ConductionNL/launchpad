# Spec: mydash-compliance-audit-panel

**Status:** proposed
**Scope:** mydash
**Tier:** widget-capabilities
**Depends on:** widgets, widget-add-edit-modal, dashboards, permissions, conditional-visibility; cross-app runtime sources: openregister (audit-trail-immutable + archival-destruction-workflow + GraphQL, read-only), shillinq (retention rules via GraphQL), docudesk (compliance evidence via GraphQL)

## Purpose

Surface the organisation's compliance posture on a mydash dashboard through one widget (`mydash_compliance_audit`). The widget reads audit-trail, retention, and compliance-evidence data at runtime via GraphQL — consuming OR's `audit-trail-immutable` and `archival-destruction-workflow` abstractions (ADR-022 table rows "Audit trail" + "Archival + destruction workflow") plus shillinq's Archiefwet retention rules and docudesk's compliance documents.

mydash MUST NOT add an install-time dependency on any of these sibling apps — per `feedback_mydash-no-or-dependency.md`. Every data flow is a runtime read, every absent sibling renders an empty-state.

Sourced from Specter draft `compliance-audit-panel` (2 features: deadline alert, portfolio filter).

## ADDED Requirements

### REQ-CAP-001: The system SHALL register a `mydash_compliance_audit` widget type

The widget MUST appear in `src/constants/widgetRegistry.js` and the unified Add Widget modal (REQ-WDG-010 / REQ-WDG-014). The registry entry MUST carry the standard fields plus a soft `requires.graphql: ['openregister.auditTrail', 'openregister.archival', 'shillinq.retentionRules']` declaration.

#### Scenario: Widget registered and discoverable

- **GIVEN** the registry completeness test
- **WHEN** it runs
- **THEN** `compliance-audit` MUST appear in EXPECTED_TYPES
- **AND** the entry MUST surface in `listWidgetTypes()` with all required fields non-null

#### Scenario: Widget appears in the Add modal

- **GIVEN** the Add Widget modal is open
- **WHEN** the user opens the type picker
- **THEN** `Compliance and audit` MUST be selectable

### REQ-CAP-002: The widget content shape SHALL declare framework scope, filter, and alert configuration

The placement persists `{type: 'compliance-audit', content: {...}}` with:

| Field | Type | Required | Default | Purpose |
|---|---|---|---|---|
| `frameworks` | string[] | Yes | `['BIO', 'ISO-27001', 'AVG']` | Frameworks tracked (`BIO`, `ISO-27001`, `AVG`, `NEN-7510`, custom slugs allowed) |
| `portfolioFilter` | string[] | No | `[]` | Filter to specific portfolios (Specter source: "filter by portfolio") |
| `deadlineWindowDays` | integer | No | `30` | Look-ahead horizon for "imminent" alerts |
| `showAuditTrailCard` | boolean | No | `true` | Surface the audit-trail summary card |
| `showRetentionCard` | boolean | No | `true` | Surface the retention disposition counts |
| `showEvidenceCard` | boolean | No | `true` | Surface docudesk-attached compliance evidence |

#### Scenario: Default placement validates

- **GIVEN** the content shape contract
- **WHEN** `{type: 'compliance-audit', content: {frameworks: ['BIO']}}` is saved
- **THEN** validation MUST pass
- **AND** the defaults above MUST apply to unset fields

#### Scenario: Unknown framework slug accepted (forward-compat)

- **GIVEN** `content.frameworks = ['BIO', 'CUSTOM-FRAMEWORK-X']`
- **WHEN** validation runs
- **THEN** validation MUST pass — the widget renders an empty card for unknown frameworks rather than blocking placement save (the framework definition lives on the compliance sibling app, not in mydash)

### REQ-CAP-003: The audit-trail card SHALL be consumed from OR's `audit-trail-immutable` abstraction — never from a local audit table

The audit-trail summary card MUST issue a GraphQL query against OR exposing `auditEvents { type count latestTimestamp }` for the viewer's accessible objects. mydash MUST NOT define a local audit table, MUST NOT mirror OR audit events, and MUST NOT post writes to the audit trail (ADR-022 anti-pattern: "Home-grown audit trails").

#### Scenario: Audit summary card renders from OR GraphQL

- **GIVEN** OR is installed AND the viewer has 4 recent audit events across their objects
- **WHEN** the widget renders
- **THEN** the audit card MUST display the event types + counts + latest timestamp
- **AND** the GraphQL query MUST target OR's `/graphql` (no local table read, no axios to OR)

#### Scenario: OR absent renders empty-state inline

- **GIVEN** OR is not reachable (`/graphql` returns 404)
- **WHEN** the widget renders
- **THEN** the audit card MUST display `t('mydash', 'Audit trail unavailable — OpenRegister not reachable')`
- **AND** other cards on the widget MUST continue to render

#### Scenario: No write to audit trail

- **GIVEN** the compliance-audit widget source files
- **WHEN** scanned for POST / PUT against `/apps/openregister/.*audit`
- **THEN** zero matches MUST exist — the widget is read-only

### REQ-CAP-004: The retention card SHALL consume OR's `archival-destruction-workflow` + shillinq retention rules

Retention disposition counts MUST be queried via GraphQL from OR (`archival { dispositionState count }`) and joined with shillinq's Archiefwet retention rules (`retentionRules { selectielijstRef durationYears }`) at the GraphQL composition layer — handled by OR's relations abstraction (ADR-022). mydash MUST NOT join the two locally and MUST NOT cache retention metadata.

#### Scenario: Retention card renders with disposition states

- **GIVEN** OR archival + shillinq are installed AND the viewer's scope includes 12 objects pending destruction
- **WHEN** the widget renders
- **THEN** the retention card MUST surface the disposition state buckets (`pending-review`, `destroy-eligible`, `archived`, `destroyed`) with counts
- **AND** the Selectielijst reference for the largest bucket MUST surface as a tooltip

#### Scenario: shillinq absent partial-renders the card

- **GIVEN** OR present but shillinq absent
- **WHEN** the card renders
- **THEN** disposition state counts MUST still render
- **AND** the Selectielijst tooltip MUST display `t('mydash', 'Retention rules unavailable — shillinq not installed')`
- **AND** no error MUST throw

### REQ-CAP-005: The widget SHALL surface deadline alerts with cross-session persistence (Specter acceptance)

When a compliance deadline falls within `content.deadlineWindowDays`, the widget MUST render a visible alert naming the deadline and due date. The alert MUST persist across sessions until dismissed or the deadline passes. Dismissal state MUST persist via the existing mydash placement state — NOT via a new mydash backend table; the widget reuses the placement's existing `acknowledgements: {deadlineId: dismissedAt}` JSON sub-field within `content` (forward-compat extension of REQ-CAP-002).

#### Scenario: Imminent deadline surfaces alert (Specter source)

- **GIVEN** a compliance deadline within the configured threshold exists for the viewer's scope
- **WHEN** the viewer opens the dashboard
- **THEN** an alert MUST display naming the deadline and due date

#### Scenario: Alert persists until acknowledged (Specter source)

- **GIVEN** an unacknowledged imminent deadline alert
- **WHEN** the viewer signs out and back in
- **THEN** the alert MUST still be visible
- **AND** dismissal MUST persist by writing to the placement's `content.acknowledgements` map via the existing widget PUT endpoint (no new endpoint added)

#### Scenario: Passed deadline updates to overdue (Specter source)

- **GIVEN** the deadline has passed without acknowledgement
- **WHEN** the viewer next loads the dashboard
- **THEN** the alert MUST update to reflect overdue status (red styling + revised copy)

### REQ-CAP-006: The portfolio filter SHALL scope the widget's queries server-side, not client-side (Specter acceptance)

`content.portfolioFilter` MUST be passed as a GraphQL variable on every query the widget issues. Filtering MUST happen server-side (in the sibling app's GraphQL resolver). Client-side post-filter MUST be permitted only as a UX enhancement (sorting, paging); client-side filter MUST NOT replace the server-side variable.

#### Scenario: Filter included in GraphQL variables

- **GIVEN** `content.portfolioFilter = ['portfolio-a']`
- **WHEN** the widget issues its queries
- **THEN** the variables payload MUST include `{portfolioFilter: ['portfolio-a']}`
- **AND** the GraphQL document MUST reference the variable in its argument list

#### Scenario: Cleared filter widens result set (Specter source)

- **GIVEN** a portfolio filter is active
- **WHEN** the viewer clears it
- **THEN** the widget MUST re-issue the queries with the variable omitted
- **AND** the dashboard cards MUST update to reflect every portfolio the viewer can read

#### Scenario: Summary metrics reflect filter (Specter source)

- **GIVEN** `content.portfolioFilter = ['portfolio-a']` AND portfolio-a has 5 retention-pending objects
- **WHEN** the retention card renders
- **THEN** the card MUST display only the counts for portfolio-a
- **AND** expanding the filter to include portfolio-b MUST update counts to include both portfolios

### REQ-CAP-007: The widget SHALL not block install when sibling apps are absent (graceful degradation)

When OpenRegister, shillinq, or docudesk are not installed or not reachable at runtime, the widget MUST NOT throw errors or display "app required" messaging. Instead, the widget MUST render only the cards whose data sources are available, and display empty-state messages for unavailable data.

#### Scenario: All siblings absent renders minimal widget

- **GIVEN** OR, shillinq, and docudesk are all absent
- **WHEN** the widget renders
- **THEN** the widget MUST still display with the framework selector visible
- **AND** all three cards MUST show empty-state messaging with suggestions to install the apps
- **AND** the portfolio filter MUST still be visible but inert

#### Scenario: Partial availability surfaces available data

- **GIVEN** OR present, shillinq absent, docudesk absent
- **WHEN** the widget renders
- **THEN** the audit-trail card MUST display live data
- **AND** the retention card MUST display empty-state: `t('mydash', 'Retention unavailable')`
- **AND** the evidence card MUST display empty-state: `t('mydash', 'Compliance documents unavailable')`

### REQ-CAP-008: The evidence card SHALL surface docudesk compliance documents linked via OR object-interactions

Compliance evidence documents (uploaded compliance reports, audit findings, remediation plans) MUST be retrieved via OR's object-interactions GraphQL abstraction (ADR-019 integration registry). The card MUST display a list of linked documents grouped by compliance framework, with download + preview links per document.

#### Scenario: Evidence card lists linked documents

- **GIVEN** docudesk is installed AND 3 compliance evidence documents are linked to objects the viewer can read
- **WHEN** the widget renders
- **THEN** the evidence card MUST list the 3 documents grouped by framework
- **AND** each document MUST surface a download link + preview button

#### Scenario: No evidence documents shows empty state

- **GIVEN** no compliance evidence documents are linked
- **WHEN** the widget renders
- **THEN** the evidence card MUST display `t('mydash', 'No compliance evidence documents found')`

### REQ-CAP-009: Accessibility and responsive requirements

The widget MUST meet WCAG AA compliance and render correctly from 320px to 1920px viewports. All interactive elements (buttons, alerts, filters) MUST be keyboard-navigable and screen-reader accessible.

#### Scenario: Keyboard navigation works on all elements

- **GIVEN** the widget is rendered
- **WHEN** tabbing through interactive elements
- **THEN** all buttons, filter controls, and dismissible alerts MUST be reachable
- **AND** focus MUST be visible with a clear indicator

#### Scenario: Responsive layout on mobile (Specter concern: "mobile-remote-access")

- **GIVEN** the widget is viewed on a 320px-wide mobile viewport
- **WHEN** the widget renders
- **THEN** all cards MUST stack vertically
- **AND** the portfolio filter control MUST be easily selectable with touch
- **AND** framework status indicators MUST remain legible

## References

- ADR-022 — Apps Consume OpenRegister Abstractions (audit-trail-immutable, archival-destruction-workflow)
- ADR-024 — App Manifest (widget registry entries, soft requires)
- Specter draft `compliance-audit-panel` (source requirements: deadline alert, portfolio filter)
- feedback_mydash-no-or-dependency.md (runtime-only contract, no install-time deps)
- ADR-019 — Integration Registry Pattern (object-interactions compliance evidence)
