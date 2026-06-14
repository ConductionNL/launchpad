# Follow-up issues — dashboard-public-share

Filed per Task 14. Each item is a future OpenSpec change or tracking
issue that should land in a separate PR; the parent
`dashboard-public-share` change ships the minimum shareable surface
(token + render + password + revoke + view-count) and explicitly defers
the operational/admin scaffolding below.

## 1. Admin UI for share management

**Scope.** A page under `Settings → LaunchPad → Sharing` (or the
`SharingTab` introduced by `refactor-launchpad-ia-alignment`) that lists
every active public share fleet-wide with columns `dashboard`,
`createdBy`, `createdAt`, `expiresAt`, `viewCount`, `lastViewedAt`,
plus row-level **Revoke** + **Copy link** actions.

**Why deferred.** Per-dashboard share-list + revoke is already exposed
via the owner-only endpoints (`GET /api/dashboards/{uuid}/public-shares`,
`DELETE /api/dashboards/{uuid}/public-shares/{id}`). An admin
fleet-wide view is a separate UX surface that needs RBAC + pagination
+ filter design.

**Acceptance.** New page renders for admins; non-admin → 403; revoke
button calls existing endpoint; copy-link copies the existing
`PublicShare::$url` payload.

## 2. View analytics — beyond raw counter

**Scope.** Expand `oc_launchpad_public_shares.viewCount` /
`lastViewedAt` into a small `oc_launchpad_public_share_views` ledger
table (one row per debounce-bucket: `shareId`, `ipHash`, `viewedAt`,
`country?`, `referrer?`). Add an analytics widget on the dashboard
detail page (`Last 30 days`, `Unique IPs`, `Geographic spread`).

**Why deferred.** Storing per-view rows for every public share grows
unbounded; needs a retention policy + GDPR/AVG review for `ipHash` +
`country` columns before the schema lands.

**Acceptance.** Migration adds the ledger table with a 90-day retention
job; widget renders without performance regression on a dashboard with
≥10k views.

## 3. Token regeneration

**Scope.** Allow share owners to rotate the token on an existing share
(`POST /api/dashboards/{uuid}/public-shares/{id}/rotate`) so a leaked
link can be invalidated without losing the share's password / expiry /
counters.

**Why deferred.** Current owners can `DELETE` + `POST` (manual
rotation) — the convenience endpoint is an enhancement, not a security
gap.

**Acceptance.** New endpoint returns the new token + URL; old token
returns 404 immediately; viewCount is preserved.

## 4. Email allow-list

**Scope.** Add an optional `recipientEmails` column to
`oc_launchpad_public_shares` (CSV of recipient email addresses); the
render endpoint requires the bearer to be logged-in to a NC account
whose email is on the list (or to a guest session backed by a
magic-link). Falls back to current "public" behaviour when the column
is null.

**Why deferred.** This is effectively a second share modality
("share-with-specific-emails") that overlaps with NC native shares;
needs a UX-and-spec discussion before implementation.

**Acceptance.** New share creation accepts `recipientEmails: string[]`;
render with mismatched email → 403; magic-link flow via NC's existing
`mail.share` machinery.

## 5. Hard-delete cleanup job for revoked shares >90 days

**Scope.** A `BackgroundJob` (cron) that purges rows from
`oc_launchpad_public_shares` where `revokedAt IS NOT NULL` AND
`revokedAt < (now - 90 days)`. The current soft-revoke means revoked
rows stay forever; hard-delete after a quarantine window keeps the
table size predictable on long-running fleets.

**Why deferred.** Soft-revoke is correct for audit purposes; the
hard-delete cutoff (90 days) is a policy decision that should be
admin-configurable. Default settings + admin override land together.

**Acceptance.** New job registered via `IRegistrationContext`; runs
nightly; admin setting `launchpad.public_share_purge_after_days`
(default 90, 0 = never).

## 6. Playwright e2e coverage (parent Task 11)

**Scope.** `tests/e2e/public-share.spec.ts` covering anonymous-render
+ password-unlock + revoked-404 + expired-404 + view-debounce.

**Why deferred.** The five endpoints have full PHPUnit + service-level
coverage (`PublicShareServiceTest.php`,
`PublicShareControllerTest.php`); the missing pieces are
session-isolation + browser cookie behaviour, which Playwright is the
right tool for.

**Acceptance.** Spec runs green against the dev container; covers all
five user paths enumerated in the parent Task 11 line.
