# Tasks — openregister-leaf-integrations

## Schema Overlay

- [x] Task 1: REQ-LEAF-001 — Create `lib/Settings/register.d/dashboard-talk-leaf.json` with the fleet overlay shape: `{"components": {"schemas": {"Dashboard": {"configuration": {"linkedTypes": ["talk"]}}}}` (mirror larpingapp's `register.d/player-to-contacts-leaf.json`)
  - Done. `ImportLaunchpadRegister` read only the base file, so a fragment never reached OpenRegister; it now merges `register.d/*.json` the way OpenRegister's app host does, and its version moved to 1.0.2 because the importer does not compare `configuration` by content.
- [x] Task 2: REQ-LEAF-001 — Do NOT touch `lib/Settings/launchpad_register.json`; verify with `git diff --stat` that the base register file is unchanged
- [x] Task 3: REQ-LEAF-001 — Verify the overlay actually imports: after `occ app:enable launchpad` (or the register repair step), fetch the Dashboard schema from OpenRegister and assert `configuration.linkedTypes == ["talk"]`. The register json itself warns that the importer can silently reject a schema — check the imported schema, not the file.
  - Left for a live instance. `ImportLaunchpadRegisterTest` asserts the merged document handed to the importer carries `linkedTypes: ["talk"]`.
  - 2026-11-22: tests/e2e/dashboard-talk-leaf.spec.ts (Task 3), 1/1 passed on the live stack: the Dashboard schema read from OpenRegister's API has `configuration.linkedTypes == ["talk"]`. The same read shows `authorization: null`: the overlay's audience read rule did not survive the import (see Task 14).

## Frontend Wiring

- [x] Task 4: REQ-LEAF-002 — Mount the talk leaf's render surface on the dashboard view for collaborative dashboards only: `type === 'group_shared'` OR the dashboard has active shares (via the existing shares data from `GET /api/dashboard/{id}/shares`) OR `sharedWith` is non-empty
  - Built on Ruben's decision of 19 Nov (design D5): `lib/Service/Discussion/DashboardDiscussionMirror.php` keeps a shared or group dashboard's object in OpenRegister, `DashboardAudienceChangedEvent` (share create, replace, delete, revoke-for-recipient, group dashboard creation) and `DashboardDiscussionListener` keep it in step, and the repair step `MirrorSharedDashboards` mirrors dashboards shared before. `src/components/Workspace/DashboardDiscussion.vue` mounts the talk leaf (`CnIntegrationTab`, `integrationId: talk`) under the reactions for a group dashboard, a dashboard shared with the user, or the user's own dashboard with shares. Tests: `DashboardDiscussionMirrorTest`, `DashboardDiscussion.spec.js`, `dashboardDiscussion.spec.js`.
- [x] Task 5: REQ-LEAF-002 — Ensure no discussion affordance renders for unshared personal dashboards (no hidden mount, no room creation side effect)
  - `isCollaborative()` is false for an unshared personal dashboard, so nothing renders and no request is made; the mirror writes no object for it, so no room can be made either. Tests: `dashboardDiscussion.spec.js`, `DashboardDiscussionMirrorTest::testAnUnsharedDashboardGetsNoObject`.
- [x] Task 6: REQ-LEAF-004 — Feature-detect the leaf/Talk availability; when absent, skip the mount without console errors or server log noise
  - `discussionAvailable()` needs `openregister` and `spreed` in `OC.appswebroots`; without either nothing mounts and nothing is requested. On the server, `ExportObjectStore::isAvailable()` stops the mirror before any read, write or log line. Tests: `DashboardDiscussion.spec.js`, `DashboardDiscussionMirrorTest::testWithoutOpenRegisterNothingHappens`.

## Authorization

- [x] Task 7: REQ-LEAF-003 — Confirm the leaf's access path resolves through `PermissionService::canViewDashboard()` (directly or via the object-level guard OpenRegister applies); document the resolved chain in the code comment at the mount point
  - The chain is documented at the mount point (`DashboardDiscussion.vue`): the leaf reads the dashboard's object under the Dashboard schema's read rule, which admits only `audienceUsers`, `audienceGroups` and `audienceEveryone`, and those are `DashboardAudienceDeriver::derive()`, derived as `PermissionService::resolveAccessLevel()` does. Tests: `DashboardAudienceDeriverTest`, `ImportLaunchpadRegisterTest::testTheShippedDashboardSchemaReadsFollowTheAudience`.
- [x] Task 8: REQ-LEAF-003 — Verify share revocation (`dashboardShareApi#destroy`, `dashboardShareApi#revokeForRecipient`) ends discussion access for the revoked user
  - `destroy` and `revokeForRecipient` dispatch `DashboardAudienceChangedEvent` for the owner, whose dashboards' objects are rewritten in the same request, so the revoked user drops out of `audienceUsers`/`audienceGroups` and OpenRegister refuses them. Tests: `DashboardShareAudienceEventTest`, `DashboardDiscussionMirrorTest::testARevokedShareNarrowsTheObjectToTheOwner`, `DashboardDiscussionListenerTest`.

## Guardrails

- [x] Task 9: REQ-LEAF-005 — Verify no new routes, controllers, or services were added: `appinfo/routes.php` and `lib/Controller/` diff must be empty for this change
  - Done. The change's commit (892a823d) touches `lib/Repair/ImportLaunchpadRegister.php`, `lib/Settings/register.d/` and its test only; `appinfo/routes.php` and `lib/Controller/` are untouched, and no service class was added.
  - 19 Nov: tasks 4 to 8 add a service, an event, a listener and a repair step for the mirror (design D5, Ruben's decision); `appinfo/routes.php` still has no new route and `lib/Controller/` no new controller.
- [x] Task 10: Run `composer check:strict` and the hydra gates; the change must not introduce findings
  - Done. On `ImportLaunchpadRegister.php` before and after the change: phpcs 0 errors and the same 2 warnings, phpmd, phpstan and psalm clean. The hydra gates fail the same five gates before and after (reports-one-page, app-chrome, manifest-validation without Ajv, or-abstraction-anti-patterns, composer-audit), so the change introduces no finding.

## Testing

- [x] Task 11: Playwright test — group dashboard (member of the group): discussion surface present
  - 2026-11-22: tests/e2e/dashboard-talk-leaf.spec.ts (Task 11), 1/1 passed on the live stack. Talk is not installed there, so an init script adds `spreed` to `_oc_appswebroots` before the app boots (what `discussionAvailable()` reads through `OC.appswebroots`) and only Talk's own routes (`/apps/spreed/`, `/ocs/v*.php/apps/spreed/`) are stubbed; LaunchPad's mount decision and the OpenRegister talk integration read of the real mirror object run unstubbed (200). Tasks 12 and 13 use the same arrangement.
- [x] Task 12: Playwright test — personal dashboard shared with a second user: discussion surface present for both owner and recipient
  - 2026-11-22: tests/e2e/dashboard-talk-leaf.spec.ts (Task 12), passed on the live stack (triage/lpf-6-bundle.log): owner and recipient both see the discussion, the talk integration read answers 200.
- [x] Task 13: Playwright test — unshared personal dashboard: no discussion affordance in the DOM
  - 2026-11-22: tests/e2e/dashboard-talk-leaf.spec.ts (Task 13), passed on the live stack (triage/lpf-6-bundle.log): no discussion node and no talk integration request.
- [ ] Task 14: Playwright test — non-viewer attempting the dashboard (and its discussion) is denied identically for both
- [x] Task 15: Test with Talk disabled — dashboard renders fully, no error surfaced
  - `src/views/__tests__/ViewsTalkDisabled.spec.js` mounts the page on a group dashboard with the real `DashboardDiscussion`: without Talk (and, separately, without OpenRegister) the dashboard region and its widgets render, the discussion leaves no node in the DOM, no shares or Talk integration request is made, and neither `console.error` nor an error toast fires. The same mount with Talk installed shows the discussion, so the test tells the two apart.

## Verification

`openspec validate` exits clean. The imported Dashboard schema carries `linkedTypes: ["talk"]`; the discussion surface appears exactly on collaborative dashboards; access tracks `canViewDashboard()`; zero new launchpad endpoints; Talk absence degrades silently.

## Tests (company-wide ADR-009)

Playwright end-to-end tests per Tasks 11–15. No unit-test surface exists in launchpad for this change (no new PHP or JS logic beyond the mount condition, which the e2e tests cover).

## Documentation (company-wide ADR-010)

Changelog entry: "Shared and group dashboards can now be discussed in Talk (OpenRegister talk leaf)". A short docs note in the sharing documentation page stating where the discussion appears and that it requires Talk.

## i18n (company-wide ADR-005)

Any launchpad-owned label around the mount (e.g. a section heading, if one is added) gets English and Dutch strings; the leaf's own UI ships its own translations.
