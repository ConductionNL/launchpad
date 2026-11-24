---
status: done
---

# dashboard-public-share Specification

## Purpose
Lets dashboard owners publish read-only public links to their dashboards, optionally protected by a password and an expiry date. Anonymous visitors render the dashboard through a unique share token while the system enforces read-only access, soft-revocation, brute-force throttling, debounced view counting, and a service-account read path for GroupFolder-backed content.

## Requirements

### Requirement: REQ-PSHR-001 Create Public Share

Dashboard owners (or admins) MUST be able to create a public share with an optional password and expiry date.

#### Scenario: Create a public share without password
- GIVEN a logged-in Nextcloud user "alice" who owns dashboard "Sales Pipeline" (uuid `550e8400-e29b-41d4-a716-446655440001`)
- WHEN she sends `POST /api/dashboards/550e8400-e29b-41d4-a716-446655440001/public-share` with body `{}`
- THEN the system MUST create a new public share record with a generated 64-byte URL-safe random `token`, `passwordHash` set to NULL, `expiresAt` set to NULL, and `createdBy` set to "alice"
- AND return HTTP 201 with response body `{token, url, passwordRequired: false, expiresAt: null}`

#### Scenario: Create a public share with password
- GIVEN user "alice" owns dashboard uuid `550e8400-e29b-41d4-a716-446655440001`
- WHEN she sends `POST /api/dashboards/550e8400-e29b-41d4-a716-446655440001/public-share` with body `{"password": "SecurePass123!"}`
- THEN the system MUST hash the password via BCrypt (cost factor ≥ 10) and store the hash (NOT plaintext) in `passwordHash`
- AND return `passwordRequired: true` in the response

#### Scenario: Create a public share with expiry
- GIVEN user "alice" owns the dashboard
- WHEN she sends `POST /api/dashboards/550e8400-e29b-41d4-a716-446655440001/public-share` with body `{"expiresAt": "2026-12-31T23:59:59Z"}`
- THEN the system MUST parse the ISO 8601 timestamp and store it in the `expiresAt` column
- AND return the expiry in the response

#### Scenario: Create multiple shares for one dashboard
- GIVEN user "alice" owns a dashboard and has already created one public share for it
- WHEN she creates a second share with different password/expiry settings
- THEN both shares MUST be active simultaneously with independent tokens and passwords
- AND each share MUST NOT affect the other

#### Scenario: Non-owner cannot create shares
- GIVEN user "bob" does NOT own dashboard uuid `550e8400-e29b-41d4-a716-446655440001`
- WHEN he sends `POST /api/dashboards/550e8400-e29b-41d4-a716-446655440001/public-share`
- THEN the system MUST return HTTP 403
- AND no share MUST be created

### Requirement: REQ-PSHR-002 List Active Shares

Dashboard owners MUST be able to list all active (non-revoked) public shares for their dashboard.

#### Scenario: List shares for a dashboard with multiple active shares
- GIVEN user "alice" owns a dashboard with 3 active shares and 1 revoked share
- WHEN she sends `GET /api/dashboards/550e8400-e29b-41d4-a716-446655440001/public-shares`
- THEN the system MUST return HTTP 200 with an array of 3 shares (revoked excluded)
- AND each share object MUST include: `id`, `token` (fully visible, not redacted), `url`, `passwordRequired`, `expiresAt`, `viewCount`, `lastViewedAt`

#### Scenario: List shares for a dashboard with no shares
- GIVEN user "alice" owns a dashboard with zero shares
- WHEN she sends `GET /api/dashboards/550e8400-e29b-41d4-a716-446655440001/public-shares`
- THEN the system MUST return HTTP 200 with an empty array `[]`

#### Scenario: Non-owner cannot list shares
- GIVEN user "bob" does NOT own the dashboard
- WHEN he sends `GET /api/dashboards/550e8400-e29b-41d4-a716-446655440001/public-shares`
- THEN the system MUST return HTTP 403

#### Scenario: Revoked shares are not included in list
- GIVEN user "alice" has 5 shares, 2 of which have `revokedAt IS NOT NULL`
- WHEN she fetches the list
- THEN exactly 3 shares MUST be returned

### Requirement: REQ-PSHR-003 Revoke Public Share

Dashboard owners MUST be able to soft-revoke a share via setting `revokedAt` without hard-deleting the row.

#### Scenario: Soft-revoke a public share
- GIVEN user "alice" owns a dashboard and share id=7 is active
- WHEN she sends `DELETE /api/dashboards/550e8400-e29b-41d4-a716-446655440001/public-shares/7`
- THEN the system MUST set `revokedAt` to the current timestamp (soft-delete)
- AND NOT hard-delete the row from the database
- AND return HTTP 204 (No Content)

#### Scenario: Revoked share is no longer valid
- GIVEN share id=7 has been revoked (`revokedAt IS NOT NULL`)
- WHEN an anonymous user tries to access `GET /s/{token-for-share-7}`
- THEN the system MUST return HTTP 404 (not distinguishing whether token never existed or was revoked)

#### Scenario: Non-owner cannot revoke shares
- GIVEN user "bob" does NOT own the dashboard
- WHEN he sends `DELETE /api/dashboards/550e8400-e29b-41d4-a716-446655440001/public-shares/7`
- THEN the system MUST return HTTP 403

#### Scenario: Revoking already-revoked share is idempotent
- GIVEN share id=7 already has `revokedAt IS NOT NULL`
- WHEN alice sends DELETE for the same share again
- THEN the system MUST return HTTP 204 (no error)

### Requirement: REQ-PSHR-004 Public Render Dashboard (Anonymous)

Anonymous users MUST be able to render a dashboard via a public share token, subject to password protection and expiry checks.

#### Scenario: Public render via valid token without password
- GIVEN a public share with `token = "vK9mP2qL7xR4nJ5tU8wS3cF6gH1jZ0bY"` (no password, not revoked, not expired)
- WHEN an unauthenticated user sends `GET /s/vK9mP2qL7xR4nJ5tU8wS3cF6gH1jZ0bY`
- THEN the system MUST validate the token, confirm `revokedAt IS NULL` and expiry not elapsed, load the dashboard and placements
- AND return HTTP 200 with the dashboard data (read-only JSON, no edit endpoints exposed)

#### Scenario: Invalid token returns 404
- GIVEN a request with `token = "invalid-token-does-not-exist"`
- WHEN `GET /s/invalid-token-does-not-exist` is sent
- THEN the system MUST return HTTP 404

#### Scenario: Revoked token returns 404
- GIVEN a share with a valid token but `revokedAt IS NOT NULL`
- WHEN the token is used to render
- THEN the system MUST return HTTP 404 (avoid leaking whether the token ever existed)

#### Scenario: Expired token returns 404
- GIVEN a share with `expiresAt = "2026-04-30T23:59:59Z"` and the current time is 2026-05-01T00:00:00Z
- WHEN the token is used to render
- THEN the system MUST return HTTP 404

#### Scenario: Public render must not reuse user session for GroupFolder content
@e2e exclude the GroupFolders app is not installed on the Playwright fixture — code-quality.yml `additional-apps` provisions only ConductionNL/openregister and neither seed script adds groupfolders — so the GroupFolder branch of the render path cannot be entered from a browser at all; same reason as the sibling groupfolder-storage-backend spec
- GIVEN dashboard content lives in the GroupFolder backend (sibling spec: `groupfolder-storage-backend`)
- WHEN a public-share token renders the dashboard
- THEN the system MUST use a service-account path (NOT the current Nextcloud user's permissions)
- SO that the GroupFolder ACL bypass mechanism allows the render as intended

### Requirement: REQ-PSHR-005 Password Gate (Unlock)

Password-protected shares MUST require a POST unlock before rendering via the public share UI.

#### Scenario: Password-protected share returns 401 without unlock
- GIVEN a public share with `passwordHash IS NOT NULL` and `token = "abc123"`
- WHEN an anonymous user sends `GET /s/abc123` without unlock
- THEN the system MUST return HTTP 401 with body `{passwordRequired: true}`
- AND the dashboard data MUST NOT be included

#### Scenario: Unlock with correct password
- GIVEN a share with BCrypt password hash for password "SecurePass123!"
- WHEN an anonymous user sends `POST /s/abc123/unlock` with body `{"password": "SecurePass123!"}`
- THEN the system MUST verify the plaintext password against the stored hash via `password_verify()`
- AND return HTTP 200 with body `{access: true}`

#### Scenario: Unlock with incorrect password
- GIVEN the same share and password
- WHEN the user sends `POST /s/abc123/unlock` with body `{"password": "WrongPassword"}`
- THEN the system MUST return HTTP 401 with body `{access: false}`
- AND NOT block subsequent attempts (throttle is separate)

#### Scenario: Unlock is throttled to prevent brute-force
@e2e exclude the throttle bucket is IP-global by design, so spending it would leave every later test in the same job facing 429 from a shared runner IP — playwright.config.ts runs `workers: 1`, `fullyParallel: false`, so there is no isolated IP to burn; asserted instead by PublicShareControllerTest::testUnlockReturns429WhenThrottled
- GIVEN a public share with a password
- WHEN an attacker sends 11 failed unlock attempts from IP `203.0.113.100` within 60 seconds
- THEN the system MUST invoke Nextcloud's `IThrottler` service with action `launchpad_share_password` (limit: 10 per 60 s, IP-global)
- AND return HTTP 429 (Too Many Requests) on the 11th attempt with `Retry-After` header

#### Scenario: Query param password alternative to header
- GIVEN a password-protected share
- WHEN an anonymous user sends `GET /s/abc123?password=SecurePass123!` OR includes header `X-Share-Password: SecurePass123!`
- THEN the system MUST accept either method
- AND return HTTP 200 with the rendered dashboard (server-side verification)

### Requirement: REQ-PSHR-006 Read-Only Enforcement

Any mutation endpoint accessed with a public-share token (not a logged-in Nextcloud user session) MUST return HTTP 403.

#### Scenario: Cannot create widget on public share
@e2e exclude the guard is PublicShareContext, a request-scoped PHP marker set only inside PublicShareController::renderShare(); no route mutates anything under /s/{token}, so no request a browser can issue is both a public-share bearer and a mutation — an anonymous POST is refused as unauthenticated instead, which would make a passing test prove the wrong thing. Also note the endpoint this scenario names, POST /api/dashboards/{uuid}/placements, does not exist in appinfo/routes.php. Guard asserted by PublicShareContextTest::testRequireMutableThrowsAfterMarkBearer
- GIVEN an anonymous user has rendered dashboard via public share
- WHEN they attempt `POST /api/dashboards/{uuid}/placements`
- THEN the system MUST detect the public-share bearer and return HTTP 403 with message "Cannot modify dashboard via public share"

#### Scenario: Cannot edit dashboard via public share
@e2e exclude same reason as the create-widget scenario above — PublicShareContext is request-scoped and only PublicShareController::renderShare() marks it, so an anonymous PUT /api/dashboard/{uuid} is refused as unauthenticated rather than as a public-share bearer; the choke point is DashboardService::updateDashboard()'s requireMutable() call, asserted by PublicShareContextTest::testRequireMutableThrowsAfterMarkBearer
- GIVEN a public share allows viewing
- WHEN an anonymous user attempts `PUT /api/dashboard/{uuid}` to rename
- THEN the system MUST return HTTP 403

#### Scenario: Cannot delete dashboard via public share
@e2e exclude same reason as the two scenarios above — no browser-issuable request is simultaneously a public-share bearer and a DELETE; the choke point is DashboardService::deleteDashboard()'s requireMutable() call, asserted by PublicShareContextTest::testRequireMutableThrowsAfterMarkBearer. The fourth scenario of this requirement, logged-in-user-on-public-share-renders-read-only, IS browser-observable and has a real test in tests/e2e/ci/public-share.spec.ts
- GIVEN a public share allows viewing
- WHEN an anonymous user attempts `DELETE /api/dashboard/{uuid}`
- THEN the system MUST return HTTP 403

#### Scenario: Logged-in user on public share renders read-only
- GIVEN an authenticated Nextcloud user accesses the public share link
- THEN the system MUST render it as read-only (same as anonymous)
- AND the user's normal dashboard edit permissions MUST NOT override the public-share read-only mode

### Requirement: REQ-PSHR-007 View Count Debouncing

View counts MUST be incremented at most once per minute per (token, client IP) pair to prevent refresh-spam inflation.

#### Scenario: Repeated renders from same IP in one minute
- GIVEN a public share with `viewCount = 10`
- WHEN the same IP renders the dashboard at t=0s, t=15s, t=45s (all within 60 seconds)
- THEN `viewCount` MUST be incremented to 11 (once only)
- AND `lastViewedAt` MUST be set to the latest render time (t=45s)

#### Scenario: Renders from different IPs are counted separately
@e2e exclude needs two distinct client IPs reaching the server, and the Playwright job has one runner; Nextcloud only honours X-Forwarded-For from a configured `trusted_proxies`, which the fixture does not set, so a header cannot stand in for a second IP. The per-(token, IP) cache key is pinned by PublicShareMapperTest::testDebounceIncrementsSetsCache and PublicShareMapperCoverageTest::testIncrementViewCountCountsOnceAndArmsTheDebounceKey; no test renders from two IPs
- GIVEN the same share
- WHEN IP `203.0.113.42` renders at t=0s and IP `203.0.113.43` renders at t=10s
- THEN `viewCount` MUST be incremented once per IP (each IP counts independently)

#### Scenario: Renders beyond the 60-second window reset the debounce
@e2e exclude needs a 65-second wall-clock wait between two renders, longer than the 60s per-test timeout in playwright.config.ts, with no clock a test can move; the window expiring is the 60s TTL of the cache key, pinned by PublicShareMapperTest::testDebounceIncrementsSetsCache (it asserts the key is armed with ttl 60). The same-IP scenario above is covered by tests/e2e/ci/public-share-view-count.spec.ts
- GIVEN the same share
- WHEN IP `203.0.113.42` renders at t=0s and again at t=65s (beyond the 60-second window)
- THEN `viewCount` MUST be incremented twice (one per window)

### Requirement: REQ-PSHR-008 Expiry and Revocation 404 Response

Shares with `expiresAt < now()` OR `revokedAt IS NOT NULL` MUST return HTTP 404 and avoid leaking whether the token ever existed.

#### Scenario: Expired share returns 404
- GIVEN a share with `expiresAt = "2026-04-30T23:59:59Z"`
- AND the current time is 2026-05-01T00:00:00Z
- WHEN an anonymous user attempts `GET /s/{token}`
- THEN the system MUST return HTTP 404

#### Scenario: Share expiring in the future is still valid
- GIVEN a share with `expiresAt = "2026-05-02T12:00:00Z"`
- AND the current time is 2026-05-01T14:00:00Z
- WHEN the token is used to render
- THEN the system MUST return HTTP 200

#### Scenario: Share with null expiresAt never expires
- GIVEN a share with `expiresAt IS NULL`
- WHEN the token is rendered at any future time
- THEN the system MUST NOT apply expiry logic
- AND the share MUST remain valid until revoked

### Requirement: REQ-PSHR-009 Brute-Force Protection

Failed unlock attempts MUST be throttled via Nextcloud's `IThrottler` to prevent password guessing.

#### Scenario: 10 failed unlocks per 60 seconds allowed
@e2e exclude spends the IThrottler `launchpad_share_password` bucket, which is IP-global on purpose; playwright.config.ts runs workers:1 / fullyParallel:false from one runner IP, so a test that exhausts it hands 429 to every later unlock test in the job, and there is no endpoint to reset it. The 429 path is asserted by PublicShareControllerTest::testUnlockReturns429WhenThrottled
- GIVEN a public share with a password
- WHEN an attacker sends unlock requests with wrong passwords from IP `203.0.113.50`
- THEN the first 10 attempts MUST return HTTP 401
- AND the 11th attempt within the 60-second window MUST return HTTP 429
- NOTE: The throttle action is `launchpad_share_password` with limit 10 per 60 s, tracked IP-globally (not per-share). The IThrottler action string contains no token component.

#### Scenario: Throttle is IP-global across all shares
@e2e exclude same as the scenario above: proving the bucket is shared across shares means exhausting it from the one runner IP, which leaves every later unlock test in the job facing 429. Asserted by PublicShareControllerTest::testUnlockReturns429WhenThrottled; no test drives two shares
- GIVEN two different public shares A and B
- WHEN IP `203.0.113.50` sends 10 failed unlocks against share A
- THEN the throttle counter for `launchpad_share_password` is IP-global: those 10 attempts count toward share B as well
- AND a subsequent failed unlock attempt against share B from the same IP MUST return HTTP 429
- NOTE: There is no per-share scoping of the throttle. An attacker trying many tokens from the same IP hits the ceiling across all shares simultaneously.

#### Scenario: Throttle resets after time window
@e2e exclude needs the bucket exhausted (see above) and then a 60s+ wall-clock wait, against a 60s per-test timeout; the reset is Nextcloud's own IThrottler window, not LaunchPad code, and no LaunchPad test pins it
- GIVEN IP `203.0.113.50` exhausted 10 failed attempts
- AND 60+ seconds pass with no new attempts
- WHEN they send another unlock attempt
- THEN the system MUST accept it (not return 429)

### Requirement: REQ-PSHR-010 Service-Account File Read for GroupFolder Content

Public shares referencing dashboards with GroupFolder-backed content MUST use a service-account read path, not the viewer's session.

#### Scenario: Public share rendering GroupFolder content uses service account
@e2e exclude the GroupFolders app is not installed on the Playwright fixture — code-quality.yml `additional-apps` provisions only ConductionNL/openregister and ConductionNL/integriq and neither seed script adds groupfolders — so the GroupFolder read path cannot be entered from a browser; same reason the sibling groupfolder-storage-backend spec carries
- GIVEN a dashboard containing a widget that references a GroupFolder resource
- WHEN an anonymous user renders the dashboard via a public share token
- THEN the system MUST use a service-account file-read context (NOT the anonymous user's non-existent session)
- AND the GroupFolder backend's ACL bypass mechanism MUST allow the render
- NOTE: The service-account context MUST be obtained before any GroupFolder filesystem call. The anonymous viewer's session (which does not exist) MUST NOT be passed to the GroupFolder ACL evaluator. Passing a null or non-existent user context causes reads to fail silently or return empty widget data.

#### Scenario: Widget data accessible via service account
@e2e exclude same as the scenario above: needs the GroupFolders app and a folder ACL limited to a group, and the app is not installed on the Playwright fixture
- GIVEN a GroupFolder resource is readable only by members of group "Engineering"
- AND an anonymous public-share user is not a member of "Engineering"
- WHEN the service-account render path is used
- THEN the service account MUST have appropriate read permissions (granted by GroupFolder admin configuration)
- AND the widget data MUST be included in the render

---

### Requirement: Administrators choose a public link as the start page (REQ-PSP-001)

An administrator MUST be able to pick one existing public link and switch on the public start page. The server MUST refuse a link with a password or with an expiry in the past. When the chosen link is later revoked, protected or expired, the start page MUST switch itself off and the admin section MUST say why.

#### Scenario: Library start page

- **GIVEN** Noor has a public link for the dashboard "Bibliotheek Noord" without a password
- **WHEN** she picks it as the public start page on the Sharing tab and switches it on
- **THEN** the Sharing tab shows "Public start page: Bibliotheek Noord"

#### Scenario: Protected link refused

- **GIVEN** a public link protected by a password
- **WHEN** Noor tries to pick it as the start page
- **THEN** it is not offered, and a direct request to save it returns 400

### Requirement: Signed-out visitors land on the start page (REQ-PSP-002)

When the public start page is on, a visitor who is not signed in and opens the plain login page (no redirect address, no `direct` parameter) MUST be taken to the start page. A visitor following a deep link MUST still get the login form with the redirect kept.

#### Scenario: Visitor opens the address

- **GIVEN** the public start page is on
- **WHEN** a visitor who is not signed in opens `https://cloud.bibliotheek.nl/`
- **THEN** they see the dashboard "Bibliotheek Noord" read-only with a "Sign in" button

#### Scenario: Deep link keeps the login form

- **GIVEN** the public start page is on
- **WHEN** a signed-out staff member opens a link to `/apps/files/`
- **THEN** the login form shows, and after signing in they land in Files

### Requirement: The start page offers a way in for staff (REQ-PSP-003)

The start page MUST show a "Sign in" button that opens the login form with `direct=1`, so the visitor is not sent back to the start page and single sign-on setups keep working.

#### Scenario: Staff member signs in

- **GIVEN** Pieter is on the public start page
- **WHEN** he clicks "Sign in"
- **THEN** the login form opens at `/login?direct=1`
- **AND** after signing in he lands on his Workspace page

#### Scenario: LaunchPad is the default app after install
@e2e exclude happens once, when the app is installed or upgraded, before any test runs, and the other clauses need Nextcloud's `defaultapp` system value preset, which only `occ config:system:set` or config.php can write; re-installing LaunchPad mid-suite would reset the state every other spec relies on. Pinned by SetDefaultAppTest::testRunClaimsAndReports and DefaultAppSettingTest::testClaimSetsLaunchpadWhenNoDefaultAppIsSet / testClaimKeepsTheAdministratorsDefaultApp / testClaimKeepsAnEmptyValueTheAdministratorSet / testARepeatedClaimNeverOverwritesALaterChoice

- **GIVEN** no default app is configured (Nextcloud's `defaultapp` system value is absent)
- **WHEN** LaunchPad is installed or upgraded
- **THEN** LaunchPad sets `defaultapp` to `launchpad`, so signing in lands on the Workspace page
- **AND** when a default app is configured, LaunchPad keeps it, including an empty value, which is what Nextcloud's own "reset to default" writes
- **AND** an upgrade never overwrites a default app the administrator chose after the install

#### Scenario: The administrator switches it off
@e2e exclude two of its three clauses need Nextcloud's `defaultapp` system value set to another app, which no HTTP route writes (only `occ config:system:set` or config.php), and flipping the instance default app mid-suite changes where every later login in the job lands (tests/e2e/public-start-page.spec.ts relies on it). Pinned by DefaultAppSettingTest::testSwitchingOffUnsetsTheDefaultAppLaunchpadSet, testSwitchingOffKeepsADefaultAppChosenLater and testSwitchingBackOnSetsLaunchpadAgain

- **GIVEN** LaunchPad set itself as the default app
- **WHEN** the administrator switches off "Open LaunchPad after signing in" in the admin settings (`defaultAppEnabled`, on by default)
- **THEN** LaunchPad unsets `defaultapp`, so Nextcloud's own default applies again
- **AND** when the administrator chose another default app in the meantime, that choice is kept
- **AND** switching it back on sets LaunchPad again only when no default app is set

### Requirement: Live widgets on a public link and a kiosk screen (REQ-PSHR-011)

On a public link and on a kiosk screen, the news widget SHALL list the published announcements targeted at everyone, the weather widget SHALL show its reading, and the live data widget SHALL show its value, each read through the link's or playlist's token and only for a placement that is on the shared dashboard. An announcement targeted at groups SHALL NOT be shown to an anonymous visitor.

#### Scenario: The canteen screen

- **GIVEN** the playlist Kantine shows Today in Zuiddrecht with News, Weather and Waiting time at the desk
- **WHEN** the screen shows it without a login
- **THEN** the cards show the news items, the weather and the waiting time

### Requirement: Text in Markdown on public screens (REQ-PSHR-012)

A text widget on a public link or kiosk screen SHALL render its Markdown (lists, emphasis, links), sanitised as on the dashboard.

#### Scenario: Opening hours as a list

- **GIVEN** the Opening hours widget holds a Markdown list
- **THEN** the public start page shows it as a list, not as raw text
