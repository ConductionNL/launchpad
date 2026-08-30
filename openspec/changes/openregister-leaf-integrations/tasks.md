# Tasks — openregister-leaf-integrations

## Schema Overlay

- [ ] Task 1: REQ-LEAF-001 — Create `lib/Settings/register.d/dashboard-talk-leaf.json` with the fleet overlay shape: `{"components": {"schemas": {"Dashboard": {"configuration": {"linkedTypes": ["talk"]}}}}` (mirror larpingapp's `register.d/player-to-contacts-leaf.json`)
- [ ] Task 2: REQ-LEAF-001 — Do NOT touch `lib/Settings/launchpad_register.json`; verify with `git diff --stat` that the base register file is unchanged
- [ ] Task 3: REQ-LEAF-001 — Verify the overlay actually imports: after `occ app:enable launchpad` (or the register repair step), fetch the Dashboard schema from OpenRegister and assert `configuration.linkedTypes == ["talk"]`. The register json itself warns that the importer can silently reject a schema — check the imported schema, not the file.

## Frontend Wiring

- [ ] Task 4: REQ-LEAF-002 — Mount the talk leaf's render surface on the dashboard view for collaborative dashboards only: `type === 'group_shared'` OR the dashboard has active shares (via the existing shares data from `GET /api/dashboard/{id}/shares`) OR `sharedWith` is non-empty
- [ ] Task 5: REQ-LEAF-002 — Ensure no discussion affordance renders for unshared personal dashboards (no hidden mount, no room creation side effect)
- [ ] Task 6: REQ-LEAF-004 — Feature-detect the leaf/Talk availability; when absent, skip the mount without console errors or server log noise

## Authorization

- [ ] Task 7: REQ-LEAF-003 — Confirm the leaf's access path resolves through `PermissionService::canViewDashboard()` (directly or via the object-level guard OpenRegister applies); document the resolved chain in the code comment at the mount point
- [ ] Task 8: REQ-LEAF-003 — Verify share revocation (`dashboardShareApi#destroy`, `dashboardShareApi#revokeForRecipient`) ends discussion access for the revoked user

## Guardrails

- [ ] Task 9: REQ-LEAF-005 — Verify no new routes, controllers, or services were added: `appinfo/routes.php` and `lib/Controller/` diff must be empty for this change
- [ ] Task 10: Run `composer check:strict` and the hydra gates; the change must not introduce findings

## Testing

- [ ] Task 11: Playwright test — group dashboard (member of the group): discussion surface present
- [ ] Task 12: Playwright test — personal dashboard shared with a second user: discussion surface present for both owner and recipient
- [ ] Task 13: Playwright test — unshared personal dashboard: no discussion affordance in the DOM
- [ ] Task 14: Playwright test — non-viewer attempting the dashboard (and its discussion) is denied identically for both
- [ ] Task 15: Test with Talk disabled — dashboard renders fully, no error surfaced

## Verification

`openspec validate` exits clean. The imported Dashboard schema carries `linkedTypes: ["talk"]`; the discussion surface appears exactly on collaborative dashboards; access tracks `canViewDashboard()`; zero new launchpad endpoints; Talk absence degrades silently.

## Tests (company-wide ADR-009)

Playwright end-to-end tests per Tasks 11–15. No unit-test surface exists in launchpad for this change (no new PHP or JS logic beyond the mount condition, which the e2e tests cover).

## Documentation (company-wide ADR-010)

Changelog entry: "Shared and group dashboards can now be discussed in Talk (OpenRegister talk leaf)". A short docs note in the sharing documentation page stating where the discussion appears and that it requires Talk.

## i18n (company-wide ADR-005)

Any launchpad-owned label around the mount (e.g. a section heading, if one is added) gets English and Dutch strings; the leaf's own UI ships its own translations.
