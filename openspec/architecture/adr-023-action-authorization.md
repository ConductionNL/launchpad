# ADR-023: Action-level authorization (LaunchPad adoption record)

**Status:** accepted
**Date:** 2026-08-08
**Canonical decision:** `hydra/openspec/architecture/adr-023-action-authorization.md`

## Why this file exists

Twenty-seven `@spec` tags across six files already pointed at this path:

| file | tags |
|---|---|
| `lib/Service/ActionAuthService.php` | 6 |
| `lib/Controller/ActionMatrixController.php` | 4 |
| `lib/Repair/ApplyActionBaseline.php` | 4 |
| `lib/Repair/InitializeActions.php` | 4 |
| `src/components/admin/ActionAuthMatrix.vue` | 8 |
| `src/services/api.js` | 1 |

Nothing was here. A whole implemented capability — a service, a controller, two
repair steps, an admin matrix UI and its API client — was annotated against a
document that had never been written, so every one of those tags was a dangling
reference (`gate-46 spec-anchor-existence`).

The decision itself is **not** LaunchPad's to make: it is a company-wide ADR and
its canonical home is hydra. This file is the **adoption record** — what
LaunchPad concretely does to satisfy ADR-023, expressed against this repo's own
code so the anchors resolve to something that actually describes what they
annotate. When the two disagree, hydra wins and this file is the bug.

Anchors cannot simply point at hydra: gate-46 matches `@spec openspec/…` and
resolves it inside the repo, so a cross-repo path would not be a reference — it
would be invisible.

## Context

ADR-023 splits authorization in two:

- **Data RBAC** — who may read/write which objects — is OpenRegister's job
  (ADR-022). Apps never roll their own.
- **Action RBAC** — who may *invoke* which controller method — is the app's job,
  and must be declarative, admin-visible without a code change, and mechanically
  checkable.

LaunchPad has both audiences in one app: end users living on the dashboard
canvas, and tenant admins governing sharing, retention and ops (ADR-001). Action
RBAC is what keeps the second group's surface out of the first group's reach
without hardcoding `isAdmin()` into controller bodies.

## Decision — how LaunchPad implements it

### 1. One service, one entry point

`OCA\LaunchPad\Service\ActionAuthService::requireAction(IUser, string $action)`
is the only action-authorization decision point. It throws
`OCSForbiddenException` when the caller may not proceed. There are **80 call
sites across 18 controllers**.

Resolution order, and it matters:

1. **Nextcloud admin passes** — break-glass for ops and debugging.
2. **`@all` sentinel passes** — checked *before* the admin-only short-circuit,
   so an entry of `["admin", "@all"]` reads as "everyone", not "admin only".
3. An empty entry, or exactly `["admin"]`, **denies** every non-admin.
4. Otherwise the user's group ids must intersect the entry, with `admin` and
   `@all` removed from it first — neither is a real group membership to match
   against.

Group ids come from `AdminTemplateService::getUserGroupIdsFor()`, the
single-source-of-truth accessor (REQ-TMPL-013), not from `IGroupManager`
directly.

### 2. The matrix is data, not code

The action → groups mapping is a JSON document in `IAppConfig` under
`launchpad`/`actions`. Both `getMatrix()` and `setMatrix()` normalise on the way
through: non-string action keys, non-array entries, non-string and empty group
ids are discarded, and group lists are de-duplicated.

**Default-deny.** `getAllowedGroups()` falls back to `["admin"]` for any action
not in the matrix, and malformed or unparseable JSON returns an empty matrix —
which, through that same fallback, means admin-only for everything. A corrupt
config fails closed.

### 3. `@all` exists because Nextcloud has no "everyone" group

Nextcloud has no real group containing every account, so a matrix that can only
name groups cannot express "ordinary users may list their own dashboards" —
which is why the first shipped default locked every non-admin out of the app.
`ActionAuthService::GROUP_ALL_USERS` (`@all`) closes that gap.

The `@` prefix is load-bearing: group ids created through the UI or the
provisioning API never start with it, so the sentinel can neither shadow nor be
shadowed by a real group.

**`@all` grants the right to CALL an endpoint, never the right to touch someone
else's dashboard.** Every `@all` action that mutates a dashboard still passes
through `PermissionService` for the per-object ownership / share-level check.
Action RBAC and data RBAC are both required; neither substitutes for the other.

### 4. Editing the matrix is admin-only, and does not use the matrix

`ActionMatrixController::getMatrix()` / `setMatrix()` carry
`#[AuthorizedAdminSetting(LaunchPadAdmin::class)]` and perform **no** in-body
`requireAction()` call. Per ADR-023, operations that configure the authorization
system itself are gated at the route layer instead — otherwise the matrix would
govern who may rewrite the matrix.

`ActionAuthService::setMatrix()` deliberately does not gate its own writes; its
docblock says so, and its only caller is that admin-only endpoint.

### 5. Seeding and upgrades: seed once, then never re-broaden

Two repair steps, with different jobs:

- **`InitializeActions`** seeds the matrix from `lib/actions.seed.json` on
  install.
- **`ApplyActionBaseline`** is the upgrade path. It is versioned
  (`BASELINE_VERSION` vs a stored applied-version) and it broadens **only**
  entries still holding the pristine `["admin"]` default, or absent entirely.
  Any entry an admin has changed is counted as `preserved` and left alone.

That asymmetry is the point: an admin who has deliberately narrowed an action
back to admin-only must not have it re-broadened by the next upgrade. If the seed
is unreadable the step warns and logs, and leaves the matrix untouched — it never
falls back to a permissive default.

### 6. The seed is the complete enforced set

`lib/actions.seed.json` declares **78** actions: 36 admin-only, 42 carrying
`@all`. Administrative surfaces (analytics, tile catalogue, metadata field
definitions, conditional rules, publication workflow, version history, org
navigation, `dashboard-lock.force-release`) are admin-only; the ordinary
end-user surface ships with `@all` so a fresh install is usable.

The seed and the code agree **exactly**, in both directions — every seeded action
is enforced by a `requireAction()` call, and every enforced action is seeded:

```
seeded not enforced: []
enforced not seeded: []
seed=78 used=78
```

Either kind of drift is a defect. A seeded-but-unenforced action is a
configuration surface that governs nothing — an admin narrows it and nothing
changes. An enforced-but-unseeded action falls through `getAllowedGroups()` to
`["admin"]` and silently becomes admin-only on every install, which is how an
end-user endpoint disappears for non-admins without anything reporting an error.

## Consequences

- Admins retune action access from Admin Settings → LaunchPad → Action
  authorization; no code change, no deploy.
- Adding a routed action means three edits, not one: the `requireAction()` call,
  the `actions.seed.json` entry, and a `BASELINE_VERSION` bump if existing
  installs should receive it. Skipping the second leaves the action admin-only by
  default-deny.
- Admin break-glass means a Nextcloud admin can invoke every action regardless of
  the matrix. That is deliberate, and it is why the matrix is not a substitute
  for the per-object `PermissionService` checks.

## References

- Canonical: `hydra/openspec/architecture/adr-023-action-authorization.md`
- ADR-022 — apps consume OpenRegister abstractions (data RBAC)
- ADR-001 — LaunchPad information architecture (the two-audience split)
- REQ-TMPL-013 — single-source-of-truth group-ids accessor
