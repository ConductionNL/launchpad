---
kind: code
---

# Add real e2e coverage for the dashboard-sharing UI — the spec's blanket `@e2e exclude` is stale

## Why

`openspec/specs/dashboard-sharing/spec.md:64` carries a single blanket
exclusion covering **all** requirements in the spec:

```
@e2e exclude all scenarios test PHP DashboardShareService REST API — sharing UI modals not present in v1.0.5
```

This claim is false at HEAD. `src/components/DashboardConfigModal.vue`
ships a complete sharing tab (`data-test="config-panel-sharing"`, lines
115-183): a sharee search picker (`NcSelect` + `@search="onShareeSearch"`
+ `@input="onShareeSelected"`, lines 128-147), a live list of existing
shares with a per-share permission-level `NcSelect`
(`permissionOptionFor`, `onShareLevelChange`, lines 149-169), and a
remove-share button (`onShareRemove`, lines 168-172). This is exactly the
"sharing UI modal" the exclusion says does not exist — it does, and per
`git log`/file timestamps it has existed since well before this sweep.

Coverage that *does* exist for this capability is backend-only:
`tests/Unit/Controller/DashboardShareApiControllerFollowupsTest.php`,
`tests/Unit/Service/DashboardShareServiceFollowupsTest.php` (PHPUnit,
calls the controller/service directly — bypasses NC's routing/middleware
stack), and `tests/integration/launchpad.postman_collection.json`
(Newman, HTTP-level but not browser-driven). Searching the entire
`tests/e2e/` tree for any reference to the sharing tab's test hooks
(`config-panel-sharing`, `dashboard-config__shares`, "Share with users
and groups") returns **zero matches** — including
`tests/e2e/docs-screenshots.spec.ts`, which screenshots many other
modals but not this one.

Net effect: a user-facing, security-relevant flow (owner-only share
grant/revoke, permission-level changes — REQ-SHARE-001 and neighbors)
that a real user drives entirely through the browser has never been
exercised by a test that drives the real router → middleware → controller
→ Vue re-render path. The Vitest unit test for `DashboardConfigModal.vue`
(`src/components/__tests__/DashboardConfigModal.spec.js`) mounts the
component in isolation with mocked stores, which validates the tab-split
rendering logic but not the real save/persist/re-fetch round trip a
Playwright test would catch (e.g. a share silently failing to persist, or
the sharee search hitting a broken endpoint).

## What Changes

- Add a Playwright e2e spec, e.g. `tests/e2e/dashboard-sharing.spec.ts`,
  that drives the real UI flow: open a personal dashboard's config modal,
  switch to the Sharing tab, search for and add a user share, change its
  permission level, remove a share, and reload to confirm persistence.
  Follow the existing house pattern in `tests/e2e/` for auth/fixture
  setup (see `tests/e2e/active-dashboard-resolution.spec.ts` or
  `tests/e2e/nc-dashboard-widget.spec.ts` for the admin-fixture
  convention already in use).
- Update `openspec/specs/dashboard-sharing/spec.md:64` to remove the
  blanket exclusion and instead tag the specific scenarios that
  genuinely have no UI surface (if any remain) with a precise,
  per-scenario `@e2e exclude <reason>`, while scenarios now covered by
  the new Playwright spec reference it directly (e.g.
  `@e2e tests/e2e/dashboard-sharing.spec.ts`).
- No production code changes required — this is test-only, closing a
  coverage gap on already-shipped UI.
- **BREAKING**: none.

## Capabilities

### Modified Capabilities

- `dashboard-sharing`: the sharing UI flow (add/change/remove a share
  from `DashboardConfigModal`'s Sharing tab) MUST have real Playwright
  e2e coverage; the spec's e2e-exclusion annotation MUST accurately
  reflect what is and is not UI-testable, not a stale "no UI exists"
  claim.

## Impact

**Affected code:** new `tests/e2e/dashboard-sharing.spec.ts`;
`openspec/specs/dashboard-sharing/spec.md` annotation update.

**Affected APIs:** none — test-only change.

**Dependencies:** none.
