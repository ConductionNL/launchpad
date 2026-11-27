# Delta: dashboard-versioning

## ADDED Requirements

### Requirement: REQ-VERS-010 Leaving edit mode keeps the state from before editing

An edit session runs from the moment an editor enters edit mode on a dashboard (`POST /api/dashboards/{uuid}/presence`
admits them) until they leave it (`DELETE /api/dashboards/{uuid}/presence`). The system MUST keep the dashboard as it
was when the session began (dashboard fields and every widget with its configuration), once per session: a renewal of
the presence MUST NOT replace it. When the session ends and the dashboard's widgets or fields differ from that state,
the system MUST store that state as one automatic version (REQ-VERS-001's note says what changed), independent of the
60-second debounce. It MUST NOT store a version when nothing changed, or when the newest version already holds that
state. A failure to keep or store the state MUST NOT refuse entering or leaving edit mode. A session whose end never
arrives MAY store no version; the kept state expires after 12 hours.

#### Scenario: Changing a widget keeps a version

- **GIVEN** Ruben's dashboard "My Dashboard" has no versions and holds a Notes widget titled "Today"
- **WHEN** he enters edit mode, renames the widget to "This week" and leaves edit mode
- **THEN** the Versions tab lists one automatic version by Ruben
- **AND** restoring it brings the widget back with the title "Today"

#### Scenario: Several changes in one session keep one version

- **GIVEN** a dashboard with three widgets
- **WHEN** the editor adds a widget, removes another and configures the third, then leaves edit mode
- **THEN** one version is stored, holding the three widgets as they were before the session

#### Scenario: Editing without changes keeps no version

- **WHEN** an editor enters and leaves edit mode without changing anything
- **THEN** no version is stored

#### Scenario: A version the settings save already stored is not stored twice

- **GIVEN** an editor enters edit mode and the first change is a dashboard settings save, which stores the state before it (REQ-VERS-001)
- **WHEN** the editor leaves edit mode
- **THEN** no second version with that same state is stored
