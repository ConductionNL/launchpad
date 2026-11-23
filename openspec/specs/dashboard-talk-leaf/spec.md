# dashboard-talk-leaf Specification

## Purpose
TBD - created by archiving change openregister-leaf-integrations. Update Purpose after archive.

## Requirements

### Requirement: REQ-LEAF-001 Talk leaf declared on the Dashboard schema via register.d overlay

The talk leaf MUST be declared declaratively, not in code. A new overlay file `lib/Settings/register.d/dashboard-talk-leaf.json` MUST add `configuration.linkedTypes: ["talk"]` to the `Dashboard` schema, following the established fleet overlay shape (`components.schemas.Dashboard.configuration.linkedTypes`, as in larpingapp's `register.d/player-to-contacts-leaf.json`). The base `launchpad_register.json` MUST NOT be edited for this; the overlay is the single source of the declaration.

#### Scenario: Overlay declares the leaf

- **GIVEN** the app is installed or upgraded with the overlay file present
- **WHEN** the register/schema import runs
- **THEN** the imported `Dashboard` schema MUST carry `configuration.linkedTypes` containing exactly `"talk"`
- **AND** no other leaf type MUST be declared by launchpad

#### Scenario: Base register file untouched

- **GIVEN** the change is implemented
- **WHEN** `lib/Settings/launchpad_register.json` is inspected
- **THEN** it MUST contain no `linkedTypes` key — the declaration lives only in `register.d/dashboard-talk-leaf.json`

### Requirement: REQ-LEAF-002 Discussion surface renders only on shared dashboards

The Talk discussion surface MUST render only for dashboards that are genuinely collaborative: `type === 'group_shared'`, or a dashboard with at least one active share (user/group shares via `DashboardShareService`, or a non-empty `sharedWith`). A personal dashboard with no shares MUST NOT show any discussion affordance — there is nobody to talk to.

#### Scenario: Group dashboard shows discussion

- **GIVEN** a `group_shared` dashboard scoped to group `marketing`
- **WHEN** a member of `marketing` opens that dashboard
- **THEN** the Talk discussion surface MUST be available for that dashboard

#### Scenario: Shared personal dashboard shows discussion

- **GIVEN** a personal dashboard the owner has shared with user `alice`
- **WHEN** the owner or `alice` opens the dashboard
- **THEN** the Talk discussion surface MUST be available

#### Scenario: Unshared personal dashboard shows nothing

- **GIVEN** a personal dashboard with no shares and empty `sharedWith`
- **WHEN** its owner opens it
- **THEN** no discussion affordance MUST render
- **AND** no Talk room MUST be created for it

### Requirement: REQ-LEAF-003 Discussion access follows the existing view guard

Access to a dashboard's discussion MUST be gated by the same authorization the dashboard itself uses: a user may see or join the discussion only if `PermissionService::canViewDashboard()` grants them view access to that dashboard. The leaf MUST NOT introduce a parallel ACL, and losing view access (share revoked via `DashboardShareApiController::destroy`/`revokeForRecipient`) MUST also end discussion access.

#### Scenario: Non-viewer cannot reach the discussion

- **GIVEN** a dashboard user `bob` has no view access to
- **WHEN** `bob` attempts to access that dashboard's Talk discussion
- **THEN** access MUST be denied with the same outcome as accessing the dashboard itself

#### Scenario: The discussion object carries the dashboard's audience

- **GIVEN** `alice` shares her dashboard with user `bob` and group `finance`
- **WHEN** the share is saved
- **THEN** the dashboard's OpenRegister object MUST carry `audienceUsers: ["alice", "bob"]` and `audienceGroups: ["finance"]`
- **AND** the Dashboard schema's read rule MUST admit a reader only through those fields

#### Scenario: Revoking a share ends discussion access

- **GIVEN** `alice` had a share on a dashboard and participated in its discussion
- **WHEN** the owner revokes `alice`'s share
- **THEN** `alice` MUST no longer be able to access the dashboard's discussion surface through launchpad

### Requirement: REQ-LEAF-004 Graceful degradation without Talk

When the Talk app is not installed or is disabled, the leaf MUST degrade silently: no discussion affordance renders, no error is logged per page view, and every other dashboard function remains unaffected. LaunchPad MUST NOT hard-depend on Talk.

#### Scenario: Talk absent

- **GIVEN** an instance where Talk is not installed
- **WHEN** a user opens a `group_shared` dashboard
- **THEN** the dashboard MUST render fully with no discussion surface and no user-visible error

### Requirement: REQ-LEAF-005 No launchpad-side wrapper code

Launchpad MUST NOT add controllers, routes or services that proxy Talk or the leaf (ADR-022). Its server-side artifacts are the register.d overlay and the service that keeps a shared dashboard's OpenRegister object and its audience in step with the dashboard.

#### Scenario: No proxy endpoints appear

- **GIVEN** the change is implemented
- **WHEN** `appinfo/routes.php` and `lib/Controller/` are inspected
- **THEN** no new Talk- or discussion-related route or controller MUST exist
