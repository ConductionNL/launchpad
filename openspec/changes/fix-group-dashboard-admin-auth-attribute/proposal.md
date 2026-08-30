---
kind: code
---

# Fix admin-only endpoints that use `#[NoAdminRequired]` + in-body `isAdmin()` instead of `#[AuthorizedAdminSetting]`

## Why

`hydra/openspec/architecture/adr-005-security.md` states the auth-attribute
contract explicitly: `#[NoAdminRequired]` means "any authenticated user
allowed; body MUST NOT call `requireAdmin()`" and admin-only semantics
"belongs on `#[AuthorizedAdminSetting]` instead." Four methods in
`lib/Controller/DashboardApiController.php` violate this and — unusually —
the violation is **self-documented as deliberate** in the docblocks:

- `createGroup()` (line 916): docblock at lines 902-905 reads *"Admin-only —
  the route attribute is `#[NoAdminRequired]` so the gate-route-auth check
  passes; the in-body admin check is the actual authorization point
  (gate-semantic-auth)."* The method is annotated `#[NoAdminRequired]`
  (line 916) and then calls `$this->dashboardService->isAdmin(...)` at
  line 926, returning `ResponseHelper::forbidden()` when false.
- `updateGroup()` (line 1026): same pattern, `isAdmin()` check at line 1039.
- `deleteGroup()` (line 1090): same pattern, `isAdmin()` check at line 1099.
- `setGroupDefault()` (line 1143): same pattern, `isAdmin()` check at
  line 1152.

This is precisely the anti-pattern `hydra-gate-semantic-auth` (ADR-005
gate-9) exists to catch — using the syntactically-permissive
`#[NoAdminRequired]` attribute to satisfy the older syntactic gate-5
route-auth check while the real gate (semantic-auth) is bypassed because
the mismatch is between the attribute and the body, not "attribute
missing." The docblocks show the mismatch was noticed and intentionally
left in place rather than fixed.

Separately, `lib/Controller/AdminSettingsController.php::listGroups()`
(line 89) and `::updateGroupOrder()` (line 162) carry **no auth attribute
at all**. Both call the same private `assertAdmin()` guard pattern used
elsewhere in the file. Per ADR-005 this defaults to admin-only at the NC
framework level (no annotation = admin-only), so it is not an
authorization bug, but ADR-005 explicitly "prefer[s] the explicit
`#[AuthorizedAdminSetting]` for clarity" — the same file's own docblock
style (`@return … or HTTP 403 when the caller is not an administrator`)
already documents the admin-only intent without the machine-checkable
attribute to back it up.

## What Changes

- `lib/Controller/DashboardApiController.php`: replace `#[NoAdminRequired]`
  with `#[AuthorizedAdminSetting(Application::APP_ID)]` on `createGroup()`,
  `updateGroup()`, `deleteGroup()`, and `setGroupDefault()`. Remove the
  now-redundant in-body `$this->dashboardService->isAdmin(...)` /
  `ResponseHelper::forbidden()` blocks in each of the four methods (the
  attribute-level middleware check replaces them) or, if the team prefers
  defense-in-depth, keep the body check but update the docblocks to stop
  describing the mismatch as intentional.
- `lib/Controller/AdminSettingsController.php`: add
  `#[AuthorizedAdminSetting(Application::APP_ID)]` to `listGroups()` and
  `updateGroupOrder()` so the admin-only contract is machine-checkable,
  matching the pattern already used elsewhere in the same controller
  family (e.g. `AdminController`, `AdminBulkController`).
- No behavioural change for callers: all six endpoints remain
  reachable only by Nextcloud global admins, exactly as today.
- **BREAKING**: none — this is an attribute/annotation correction with no
  change to the actual authorization outcome.

## Capabilities

### Modified Capabilities

- `dashboards`: clarifies that the group-shared dashboard CRUD endpoints
  (REQ-DASH-014) are declared admin-only via the framework-enforced
  attribute rather than an in-body check layered on a permissive attribute.

## Impact

**Affected code:**

- `lib/Controller/DashboardApiController.php` (createGroup, updateGroup,
  deleteGroup, setGroupDefault — lines 916, 1026, 1090, 1143)
- `lib/Controller/AdminSettingsController.php` (listGroups, updateGroupOrder
  — lines 89, 162)

**Affected APIs:** none — same routes, same authorization outcome, only the
declared contract changes.

**Dependencies:** none.
