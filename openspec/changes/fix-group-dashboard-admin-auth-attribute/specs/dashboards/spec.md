---
capability: dashboards
delta: true
status: draft
---

# Dashboards — Group-Shared CRUD Authorization Attribute

## MODIFIED Requirements

### Requirement: REQ-DASH-014 Group-shared dashboard CRUD is admin-only and declared as such

Group-shared dashboard CRUD endpoints (`createGroup`, `updateGroup`, `deleteGroup`, `setGroupDefault`) MUST be reachable only by Nextcloud global administrators, AND this restriction MUST be declared via the
`#[AuthorizedAdminSetting]` PHP attribute rather than via an in-body
`isAdmin()` check layered on a `#[NoAdminRequired]` attribute. The
attribute-level declaration is enforced by Nextcloud's own middleware
before the controller method body runs, and is the mechanically
verifiable form per ADR-005's auth-attribute contract.

#### Scenario: Non-admin cannot create a group-shared dashboard

- **GIVEN** an authenticated non-admin user "bob"
- **WHEN** bob sends `POST /api/dashboards/group/{groupId}`
- **THEN** the framework MUST reject the request with HTTP 403 before the
  controller body's business logic runs
- **AND** no dashboard MUST be created

#### Scenario: Non-admin cannot update, delete, or set-default a group-shared dashboard

- **GIVEN** an authenticated non-admin user "bob"
- **WHEN** bob sends `PUT /api/dashboards/group/{groupId}/{uuid}`,
  `DELETE /api/dashboards/group/{groupId}/{uuid}`, or
  `POST /api/dashboards/group/{groupId}/default`
- **THEN** each request MUST be rejected with HTTP 403 by the framework
  attribute check
- **AND** no mutation MUST occur

#### Scenario: Admin can perform all four group-shared operations

- **GIVEN** an authenticated Nextcloud global admin
- **WHEN** the admin calls `createGroup`, `updateGroup`, `deleteGroup`, or
  `setGroupDefault`
- **THEN** each call MUST succeed exactly as it did before this change
- **AND** the response payloads MUST be unchanged

#### Scenario: Controller method carries the correct attribute

- **GIVEN** `lib/Controller/DashboardApiController.php`
- **WHEN** `createGroup()`, `updateGroup()`, `deleteGroup()`, and
  `setGroupDefault()` are inspected
- **THEN** each MUST carry `#[AuthorizedAdminSetting(Application::APP_ID)]`
- **AND** none MUST carry `#[NoAdminRequired]`
