## MODIFIED Requirements

### Requirement: REQ-DEMO-001 Bundled showcase ZIP archives

Each `dashboards/{uuid}.json` MUST declare `type: "group_shared"`, a null `userId` and a `showcase-{id}` slug, so an installed showcase is a group dashboard rather than someone's personal one. Its `permissionLevel` MUST be `view_only`, except for a showcase that shows a shared intranet colleagues add to, which MAY be `add_only`.

The organisation showcases include the demo municipality Zuiddrecht:
- `zuiddrecht-intranet`, the intranet every colleague may add widgets to (`add_only`)
- `zuiddrecht-management`, the management information (`view_only`)

#### Scenario: The Zuiddrecht intranet lets colleagues add widgets
- **GIVEN** an administrator installed the `zuiddrecht-intranet` showcase
- **WHEN** a colleague opens "Intranet Zuiddrecht"
- **THEN** the dashboard is a group dashboard the colleague may add widgets to, and not edit or delete

#### Scenario: The management information is read only
- **GIVEN** an administrator installed the `zuiddrecht-management` showcase
- **WHEN** a colleague opens "Managementinformatie"
- **THEN** the dashboard shows the counts from the registers and offers no editing
