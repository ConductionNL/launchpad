# Dashboard Sharing — E2E Coverage Delta

## MODIFIED Requirements

### Requirement: Owner-only share management (REQ-SHARE-001)

Only the owner of a dashboard MUST be allowed to list, create, update, or delete shares on that dashboard. All share-management endpoints MUST return HTTP 403 for any caller that is not the dashboard owner, including users who themselves have a `full`-level share on the dashboard. The owner-driven sharing UI flow (open `DashboardConfigModal`'s Sharing tab, add a share, change its permission level, remove a share) MUST be covered by a real Playwright e2e test that drives the actual router → middleware → controller → Vue re-render path, not only by PHPUnit tests that call the controller/service directly.

#### Scenario: Owner adds a share

- GIVEN a logged-in user "alice" who owns dashboard id `5`
- WHEN she sends `POST /api/dashboard/5/shares` with body `{"shareType": "user", "shareWith": "bob", "permissionLevel": "view_only"}`
- THEN the system MUST insert a row in `oc_launchpad_dashboard_shares` with the four fields plus `createdAt = now()`
- AND respond with HTTP 201 and the new share's id, displayName, and serialized fields

#### Scenario: Recipient cannot manage shares

- GIVEN dashboard `5` is owned by "alice" and shared with "bob" at `full` level
- WHEN bob sends `POST /api/dashboard/5/shares` to add another recipient
- THEN the system MUST return HTTP 403
- AND no share row MUST be created

#### Scenario: Updating an existing share replaces, does not duplicate

- GIVEN alice has shared dashboard `5` with "bob" at `view_only`
- WHEN she sends `POST /api/dashboard/5/shares` with the same `shareType` and `shareWith` but `permissionLevel: "full"`
- THEN the system MUST update the existing share row, not create a second one
- AND only one share row MUST exist for `(dashboardId=5, shareType=user, shareWith=bob)`

#### Scenario: A user drives the full sharing flow through the browser

- GIVEN alice is logged in and opens the config modal for a dashboard she owns
- WHEN she switches to the Sharing tab, searches for and adds "bob" at `view_only`, changes his permission level to `full`, then removes his share, and reloads the page
- THEN `tests/e2e/dashboard-sharing.spec.ts` MUST assert each step persists correctly end to end (the added share appears, the level change is reflected, the removal survives a reload)
- AND the spec's e2e-exclusion annotation MUST be scoped to only the scenarios (if any) that genuinely have no UI surface, not a blanket exclusion of the whole capability
