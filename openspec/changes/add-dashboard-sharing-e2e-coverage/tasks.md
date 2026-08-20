# Tasks — add-dashboard-sharing-e2e-coverage

## New e2e spec

- [ ] Task 1: Create `tests/e2e/dashboard-sharing.spec.ts` following the
  fixture/auth setup convention used in `tests/e2e/nc-dashboard-widget.spec.ts`
  or `tests/e2e/active-dashboard-resolution.spec.ts` (login as the admin
  fixture user, or seed a second test user if the flow requires a real
  distinct recipient).
- [ ] Task 2: Test 1 — "Owner adds a user share": create/open a personal
  dashboard, open `DashboardConfigModal`, switch to the Sharing tab
  (`data-test="config-panel-sharing"`), use the sharee `NcSelect`
  (search + select) to add a user, save, and assert the new share appears
  in `.dashboard-config__shares`.
- [ ] Task 3: Test 2 — "Owner changes a share's permission level": with an
  existing share present, change its permission-level `NcSelect` value,
  save, reload the modal, and assert the change persisted.
- [ ] Task 4: Test 3 — "Owner removes a share": click the remove-share
  button (`Close` icon `NcButton`, `aria-label` "Remove share"), save,
  reload, and assert the share no longer appears.
- [ ] Task 5: Test 4 (if feasible with the test fixture) — "Recipient sees
  the shared dashboard": log in as the share recipient and confirm the
  shared dashboard appears in their dashboard switcher with the correct
  permission level enforced (read-only vs full, per REQ-SHARE-00x).
  If a second seeded user is not available in the current e2e fixture,
  use `test.skip(..., 'reason')` with a concrete, honest reason (matching
  the house pattern already used elsewhere in `tests/e2e/`) rather than
  silently omitting the scenario.

## Spec annotation cleanup

- [ ] Task 6: In `openspec/specs/dashboard-sharing/spec.md`, remove the
  blanket `@e2e exclude all scenarios test PHP DashboardShareService REST
  API — sharing UI modals not present in v1.0.5` line (line 64).
- [ ] Task 7: For each `### Requirement:` in the same spec, add either a
  direct `@e2e tests/e2e/dashboard-sharing.spec.ts` reference (for the
  scenarios covered by Tasks 2-5) or a specific, honest
  `@e2e exclude <reason>` for any requirement that genuinely has no UI
  surface (e.g. purely internal permission-ranking logic with no directly
  observable UI state).

## Verification

- [ ] Task 8: Run `npx playwright test dashboard-sharing` and confirm all
  new tests pass against a local dev instance.
- [ ] Task 9: Run the `gate-19`/`hydra-gate-e2e-coverage` check (or
  `openspec/coverage-report.json` regeneration) against the diff and
  confirm the `dashboard-sharing` capability's scenarios are no longer
  flagged as blanket-excluded without per-scenario justification.
