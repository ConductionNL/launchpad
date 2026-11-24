# Tasks

One PR per spec, or per requirement group for the two largest. Each task is done when the spec meets "How a spec is done" in `proposal.md`.

Counts are scenarios, and scenarios cited by a Playwright test, measured on `development` on 2026-09-11. "Blanket" means the spec hides every scenario behind one spec-level `@e2e exclude`.

## Done

- [x] `dashboard-export-import`: 48 scenarios, each with its own verdict. Three import defects fixed (#616, with #612 before it).
- [x] `demo-data-showcases`: per-scenario verdicts replaced the stale "no UI surface" exclusion (#606).

## 1. Tier 0, safety

8 specs, 343 scenarios.

- [ ] 1.1 `admin-settings`: 83 scenarios, 2 cited, blanket. Access requirements in progress 2026-09-11. Its settings-behaviour scenarios stay open under this task.
  - Spec written: `tests/e2e/admin-settings-api.spec.ts`, `tests/e2e/admin-settings-dashboard-effects.spec.ts`, `tests/e2e/admin-settings-groups.spec.ts`, `tests/e2e/admin-settings-ui.spec.ts` (helper `tests/e2e/support/adminSettings.ts`), 58 scenarios cited, 13 excluded with a reason, blanket removed, not yet run (cloud, 2026-11-19)
  - Spec written: `tests/e2e/allowed-websites-list.spec.ts` (REQ-ASET-021: the Embed half, a host added with Embed takes an embedded page and refuses a live tile, and the upgrade merge of the per-feature lists, run through `occ maintenance:repair`), 2 scenarios cited (new), admin-settings now 62 of 75 cited, 13 excluded with a reason, 0 uncited, not yet run (cloud, 2026-11-24)
- [x] 1.2 `admin-roles`: 55 scenarios, 0 cited, blanket. In progress 2026-09-11.
  - Spec written: `tests/e2e/admin-roles.spec.ts`, `tests/e2e/admin-roles-enforcement.spec.ts` (helper `tests/e2e/support/roles.ts`), 55 scenarios cited, 0 excluded, blanket removed, not yet run (cloud, 2026-11-19)
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 43 of 43 green, every listed test run (checked against `playwright test --list`).
- [ ] 1.3 `conditional-visibility`: 49 scenarios, 0 cited, blanket. In progress 2026-09-11.
  - Spec written: `tests/e2e/conditional-visibility.spec.ts`, `tests/e2e/conditional-visibility-evaluation.spec.ts` (helper `tests/e2e/support/visibilityRules.ts`), 52 scenarios cited, 0 excluded, blanket removed, not yet run (cloud, 2026-11-19)
  - Spec written: `tests/e2e/conditional-visibility-evaluation.spec.ts` (stale midnight anchor re-pointed to the renamed scenario, both halves of the wrap asserted; the 12 group, attribute and combination tests now assert their server verdict inline, which gate 19 read as no-assertion), 13 scenarios cited, 0 excluded, not yet run (cloud, 2026-11-24)
  - Spec written: `tests/e2e/conditional-visibility.spec.ts` (the cascade-delete control now asserts the exact 404 of a deleted dashboard instead of any status from 400 up), 55 of 55 scenarios cited, 0 excluded, not yet run (cloud, 2026-11-24)
- [ ] 1.4 `permissions`: 42 scenarios, 0 cited, blanket. In progress 2026-09-11, with `admin-roles` and the access requirements of `admin-settings`.
  - Spec written: `tests/e2e/permissions.spec.ts` (new) and `tests/e2e/dashboard-permission-levels.spec.ts` (tightened), 40 scenarios cited, 2 excluded with a reason, blanket removed, not yet run (cloud, 2026-11-19)
- [ ] 1.5 `dashboard-public-share`: 38 scenarios, 25 cited. In progress 2026-09-11.
  - Spec written: `tests/e2e/public-start-page-admin.spec.ts`, `tests/e2e/ci/public-share-view-count.spec.ts`, 31 scenarios cited, 14 excluded with a reason, requirement-level excludes replaced per scenario, not yet run (cloud, 2026-11-19)
  - Spec written: `tests/e2e/public-start-page.spec.ts` (REQ-PSHR-012, synced in #1765: the start-page dashboard carries an Opening hours text widget with a Markdown list, and a signed-out visitor must see two `li` items and an `em`, not the raw markers), 33 of 47 scenarios cited, 14 excluded with a reason, not yet run (cloud, 2026-11-24)
  - Spec written: `tests/e2e/public-start-page-default-app.spec.ts` (REQ-PSP-003: the default-app claim on install or upgrade, staged with `occ maintenance:repair`, and the admin switch through the page, with `defaultapp` read and written by `occ config:system`; both exclusions replaced), 2 scenarios cited (new), dashboard-public-share now 35 of 47 cited, 12 excluded with a reason, not yet run (cloud, 2026-11-24)
- [ ] 1.6 `dashboard-sharing`: 34 scenarios, 10 cited. In progress 2026-09-11. Supersedes `add-dashboard-sharing-e2e-coverage`.
  - Spec written: `tests/e2e/ci/dashboard-share-access.spec.ts`, `tests/e2e/ci/dashboard-share-notifications.spec.ts`, `tests/e2e/ci/dashboard-share-user-deletion.spec.ts`, `tests/e2e/dashboard-sharing-limits.spec.ts` (helpers `tests/e2e/support/shareApi.ts`, `tests/e2e/support/notifications.ts`), 37 scenarios cited, 1 excluded with a reason, not yet run (cloud, 2026-11-19)
  - Spec written: `tests/e2e/ci/dashboard-share-access.spec.ts` (after `DELETE /api/sharees/user/bob`, bob's reads of dashboards 5 and 7 now assert REQ-SHARE-003's exact 403 instead of "not 200"), 38 of 39 scenarios cited, 1 excluded with a reason, not yet run (cloud, 2026-11-24)
  - Spec written: `tests/e2e/dashboard-sharing.spec.ts` (four annotations that matched no scenario heading removed; its four UI tests remain the steps of the cited browser-flow scenario), 38 of 39 scenarios cited, 1 excluded with a reason, not yet run (cloud, 2026-11-24)
- [ ] 1.7 `role-feature-permissions`: 27 scenarios, 0 cited. Queued 2026-09-11, after the permissions PR.
  - Spec written: `tests/e2e/role-feature-permissions.spec.ts` (helpers in `tests/e2e/fixtures/role-feature-permissions.ts`), 27 scenarios cited, not yet run (cloud, 2026-11-19)
  - Spec written: `tests/e2e/role-feature-permissions-upgrade.spec.ts` (REQ-RFP-011: the two upgrade scenarios, staged with `occ maintenance:repair` against the old shipped `default` and `hrm` rules; both exclusions replaced), 2 scenarios cited (new), role-feature-permissions now 30 of 31 cited, 1 excluded with a reason, not yet run (cloud, 2026-11-24)
- [x] 1.8 `launchpad-enterprise-security-access`: 15 scenarios, 0 cited, blanket. Done 2026-09-11: the blanket is replaced by 15 per-scenario verdicts, all `never built`. `launchpad_security_access` appears in no commit on any branch under src, lib, appinfo or templates (`git log --all -S`).

## 2. Tier 1, data integrity

11 specs, 341 scenarios.

- [ ] 2.1 `dashboard-bulk-operations`: 50 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-bulk-operations.spec.ts, tests/e2e/dashboard-bulk-operations-ui.spec.ts, 44 scenarios cited, not yet run (cloud, 2026-11-19). 6 excluded with a reason.
  - Run 19 Nov (live pass, NC 34.0.6 + OpenRegister 2.2.0-dev): `dashboard-bulk-operations.spec.ts` green after the non-admin refusal was aligned with the framework (Nextcloud's admin-setting check answers 403 before LaunchPad's message; spec scenario amended). `dashboard-bulk-operations-ui.spec.ts`: the header-checkbox test does not find its seeded rows in the bulk table; open.
  - Spec written: tests/e2e/dashboard-bulk-operations.spec.ts, tests/e2e/dashboard-bulk-operations-ui.spec.ts, 44 scenarios cited, 6 excluded, 0 uncited (re-measured against the spec on development), not yet run (cloud, 2026-11-24). No spec change: the header-checkbox red was the panel, not the test. `DashboardBulkOperations.vue` read `/api/dashboards/visible` as a bare list while it answers `{items, quota}`, so the table stayed empty; 23d58d30 (21 Nov) reads the envelope. The UI file needs a rerun on development after that commit.
- [x] 2.2 `resource-uploads`: 46 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/resource-uploads.spec.ts, 37 scenarios cited, not yet run (cloud, 2026-11-19). 7 excluded with a reason.
  - Run 19 Nov: 33 of 37 green. Open, decision: the spec wants `{status:'error', error:'forbidden'}` for a non-admin and 403 for an anonymous caller, but `#[AuthorizedAdminSetting]` lets Nextcloud answer first (403 with its own body, 401 for anonymous).
  - Run 20 Nov on development with #1444-#1447 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev): 38 of 38 green. The refusal cases follow Nextcloud's 401/403 on admin routes (#1446).
- [x] 2.3 `dashboard-versioning`: 38 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-versioning.spec.ts, tests/e2e/dashboard-versioning-ui.spec.ts, 29 scenarios cited, not yet run (cloud, 2026-11-19). 14 excluded with a reason.
  - Run 19 Nov: 2 app defects fixed (#1430: restore of calculatedFields; seeded widgets now cleared by the spec). Open, decisions: REQ-VERS-002..005 say owner and admin, `lib/actions.seed.json` grants `dashboard-version.*` to admin only; the scenarios "Automatic snapshot on PUT" (state before the update) and "Snapshot captures full state" (the new metadata as persisted) contradict each other; the code takes the state after.
  - Run 20 Nov: dashboard-versioning-ui 4 of 4 green; dashboard-versioning 21 of 22, the red "restoring the pre-restore version" is fixed in #1456 and waits for the rerun.
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 26 of 26 green, every listed test run (checked against `playwright test --list`).
  - Spec written: tests/e2e/dashboard-versioning-notes.spec.ts, 2 scenarios cited ("Several changes at once", "Intranet updated from its template", added to the spec by the archived change version-change-notes after the green run), not yet run (cloud, 2026-11-24). The spec now counts 46 scenarios: 32 cited, 14 excluded. Expect "Several changes at once" to go red: `SnapshotChangeDescriber` names the dashboard's own changes before widget changes, so the same three changes read "Columns from 8 to 12 and 2 more changes" (its unit test pins that order); the scenario says "Widget Inbox added and 2 more changes". A product decision, not a test fix.
- [x] 2.4 `orphaned-data-cleanup`: 36 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/orphaned-data-cleanup.spec.ts, 13 scenarios cited, not yet run (cloud, 2026-11-19). 23 excluded with a reason.
  - Run 19 Nov: 12 of 13 green. Open, decision: an unauthenticated scan gets Nextcloud's 401, the spec says 403.
  - Run 20 Nov on development with #1444-#1447: 10 of 10 green; the unauthenticated scan follows Nextcloud's 401 (#1446).
- [x] 2.5 `dashboard-cascade-events`: 35 scenarios, 1 cited, blanket.
  - Spec written: tests/e2e/dashboard-cascade-events.spec.ts, 9 scenarios cited (8 new) (cloud, 2026-11-19). 26 excluded with a reason.
  - Run 19 Nov (live pass, NC 34.0.6 + OpenRegister 2.2.0-dev): `dashboard-cascade-events.spec.ts` green, after the spec cleared the seeded default widgets before counting placements and the non-cascade refusal was aligned with REQ-DASH-030 (409; scenario amended).
- [ ] 2.6 `dashboard-locking`: 29 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-locking.spec.ts, 17 scenarios cited, not yet run (cloud, 2026-11-19). 17 excluded with a reason.
  - Run 19 Nov: 3 red. Open, decisions: an administrator may not acquire the lock of another user's dashboard after a force-release (403 lock_forbidden); a colleague's save after the owner deleted the dashboard answers 403, the spec says 404; the lock-cleanup test relies on an import restoring the dashboard under its uuid, which it does not.
  - Spec written: tests/e2e/dashboard-locking.spec.ts (the uuid lookup after the preserve-uuid import now reads the `{items, quota}` envelope of `GET /api/dashboards`; it read only `dashboards`/`data`, so both REQ-LOCK-008 cascade tests failed on "the import must restore alice's dashboard" although the import restores it with its owner), 17 scenarios cited, 17 excluded with a reason, not yet run (cloud, 2026-11-24). The other two reds stay open as product decisions: the administrator's acquire after a force-release (403 lock_forbidden) and the colleague's save after deletion (403 from the permission check before the existence check, the spec says "Dashboard not found").
- [x] 2.7 `dashboard-metadata-fields`: 28 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-metadata-fields.spec.ts, 30 scenarios cited, not yet run (cloud, 2026-11-19). 2 excluded with a reason.
  - Run 19 Nov: app defect fixed (#1430: a text field was refused by the database). Open, decision: `dashboard-metadata.set-metadata` is admin-only in `lib/actions.seed.json`, the spec lets the owner write values.
  - Run 20-21 Nov on development (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 30 of 30 green. The owner writes metadata values since #1444 (owner actions), and #1456 fixed the empty object, the dot-form list filter and the switcher filter.
- [x] 2.8 `dashboard-quota-limits`: 24 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-quota-limits.spec.ts, 20 scenarios cited, not yet run (cloud, 2026-11-19). 4 excluded with a reason.
  - Run 19 Nov: 1 red. Open, decision: a non-admin adding a LaunchPad widget gets 403 because the shipped default widget rights (`SeedRolePermissions`, group `default`) allow only activity and recommendations.
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 20 of 20 green, every listed test run (checked against `playwright test --list`).
- [x] 2.9 `legacy-widget-bridge`: 21 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/legacy-widget-bridge.spec.ts, 4 scenarios cited, not yet run (cloud, 2026-11-19). 17 excluded with a reason.
  - Run 20-21 Nov on development (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 3 of 3 green.
- [ ] 2.10 `launchpad-compliance-audit-panel`: 18 scenarios, 0 cited, blanket.
  - Spec written: openspec/specs/launchpad-compliance-audit-panel/spec.md (no e2e file; never built), 0 scenarios cited, not yet run (cloud, 2026-11-19). 18 excluded as never built.
- [ ] 2.11 `dashboard-acknowledgements`: 16 scenarios, 3 cited.
  - Spec written: tests/e2e/dashboard-acknowledgements.spec.ts, tests/e2e/dashboard-acknowledgements-template.spec.ts, tests/e2e/dashboard-acknowledgements-ask.spec.ts, 22 scenarios cited, not yet run (cloud, 2026-11-19). 2 excluded with a reason.
  - Spec written: tests/e2e/dashboard-acknowledgements.spec.ts (the report test follows the Read confirmations dialog of #1749, whose `ack-count`/`pending-count` it asserted are gone: it now reads the summary, the bar, the status badges, the Not confirmed yet filter and the department column; it also cites the two delta scenarios of the open change `read-receipts-dialog`), 16 scenarios cited in this file (2 new), not yet run (cloud, 2026-11-24)

## 3. Tier 2, core surfaces

19 specs, 823 scenarios.

- [ ] 3.1 `dashboards`: 167 scenarios, 0 cited, blanket. The largest spec. Split it by requirement across several PRs.
  - Spec written: `tests/e2e/dashboards-crud.spec.ts`, `dashboards-group-shared.spec.ts`, `dashboards-resolution.spec.ts`, `dashboards-fork.spec.ts`, `dashboards-tree.spec.ts`, `dashboards-tree-ui.spec.ts`, `dashboards-publication.spec.ts`, `dashboards-personal-layer.spec.ts`, `dashboards-group-admin.spec.ts`, `dashboards-look.spec.ts`, `dashboards-api-contract.spec.ts` (helpers `support/dashboardsSpec.ts`, `support/dashboardHierarchy.ts`), 162 scenarios cited (fixme tests for suspected defects, see the PR), 26 excluded, not yet run (cloud, 2026-11-19)
- [ ] 3.2 `widgets`: 110 scenarios, 0 cited, blanket. Split it by requirement across several PRs.
  - Spec written: `tests/e2e/widgets-placements-api.spec.ts`, `widgets-rendering.spec.ts`, `widgets-picker.spec.ts`, `widgets-context-menu-contract.spec.ts`, `widgets-nc-widget.spec.ts`, `widgets-tile-type.spec.ts`, `widgets-canvas-search.spec.ts`, `widgets-reload-interval.spec.ts` (helpers `support/widgets.ts`, `support/widgetPlacements.ts`), 99 scenarios cited (12 tests `test.fixme` for suspected defects, see the PR), 21 excluded, not yet run (cloud, 2026-11-19)
- [ ] 3.3 `admin-templates`: 86 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/admin-templates-crud.spec.ts`, `admin-templates-distribution.spec.ts`, `admin-templates-gallery.spec.ts`, `admin-templates-resync.spec.ts`, `admin-templates-shipped.spec.ts`, `admin-templates-ui.spec.ts` (helper `support/adminTemplates.ts`), 100 scenarios cited (10 as `test.fixme` for suspected defects, see the PR), 13 excluded, not yet run (cloud, 2026-11-19)
  - Run 20 Nov: admin-templates-gallery 15 of 15 green, with the gallery following the user's groups (#1447); the other five files wait for the rerun.
- [ ] 3.4 `grid-layout`: 56 scenarios, 16 cited.
  - Spec written: `tests/e2e/grid-layout-drag-resize.spec.ts`, `grid-layout-keyboard.spec.ts`, `grid-layout-placement.spec.ts`, `grid-layout-rendering.spec.ts` (helper `support/ownDashboard.ts`), 56 scenarios cited in total (fixme tests for suspected defects, see the PR), 8 excluded, not yet run (cloud, 2026-11-19)
  - Spec written: `tests/e2e/responsive-grid-breakpoints.spec.ts` (new scenario app-tiles-four-to-a-row-on-a-phone from the four-column phone step; the one-column phone asserts, cited by a scenario that no longer exists, now read four columns), 2 scenarios cited (app-tiles-four-to-a-row-on-a-phone, below-smallest-breakpoint, which was cited by a 1600 px test), not yet run (cloud, 2026-11-24)
  - Spec written: `tests/e2e/widget-collision-placement.spec.ts` (four citations of slugs no grid-layout scenario carries removed; the occupied-grid test now asserts the bottom-append rule, that no existing widget moves, instead of the retired push-down), 0 scenarios cited, not yet run (cloud, 2026-11-24)
- [ ] 3.5 `setup-wizard`: 55 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/setup-wizard.spec.ts`, 42 scenarios cited (14 of them as `test.fixme` for suspected defects, see the PR), 9 excluded, not yet run (cloud, 2026-11-19)
- [ ] 3.6 `footer-customization`: 47 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/footer-customization-api.spec.ts`, `tests/e2e/footer-customization-render.spec.ts` (helper `tests/e2e/support/footerSettings.ts`), 46 scenarios cited (29 of them as `test.fixme` because the footer never renders, see the PR), 1 excluded, not yet run (cloud, 2026-11-19)
- [ ] 3.7 `navigation-editor-org`: 45 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/navigation-editor-org-api.spec.ts`, `tests/e2e/navigation-editor-org-editor.spec.ts`, `tests/e2e/navigation-editor-org-panel.spec.ts` (helper `tests/e2e/support/orgNavigation.ts`), 45 scenarios cited (22 tests are `test.fixme` for suspected defects, see the PR), 0 excluded, not yet run (cloud, 2026-11-19)
- [ ] 3.8 `tiles`: 38 scenarios, 0 cited.
  - Spec written: `tests/e2e/tiles-api.spec.ts`, `tiles-rendering.spec.ts`, `tiles-launch-target.spec.ts`, `tiles-import-discovery.spec.ts`, 56 scenarios cited in total (fixme tests for suspected defects, see the PR), 13 excluded, not yet run (cloud, 2026-11-19)
- [ ] 3.9 `dashboard-language-content`: 35 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/dashboard-language-content.spec.ts`, `tests/e2e/dashboard-language-content-ui.spec.ts`, 32 scenarios cited (5 as `test.fixme` for suspected defects, see the PR), 7 excluded, not yet run (cloud, 2026-11-19)
- [x] 3.10 `dashboard-reactions`: 31 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/dashboard-reactions.spec.ts`, `tests/e2e/dashboard-reactions-ui.spec.ts`, 27 scenarios cited, 6 excluded, not yet run (cloud, 2026-11-19)
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 26 of 26 green, every listed test run (checked against `playwright test --list`).
- [x] 3.11 `tile-quick-search`: 28 scenarios, 8 cited.
  - Spec written: tests/e2e/tile-quick-search-widget.spec.ts (new) and tests/e2e/tile-quick-search.spec.ts, 33 scenarios cited, not yet run (cloud, 2026-11-19)
  - Run 21 Nov on development after #1476 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): tile-quick-search-widget 24 of 24 and tile-quick-search 9 of 9 green.
- [x] 3.12 `dashboard-icons`: 25 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-icons.spec.ts (new), 21 scenarios cited, not yet run (cloud, 2026-11-19)
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 15 of 15 green, every listed test run (checked against `playwright test --list`).
- [x] 3.13 `conditional-visibility-editor`: 23 scenarios, 13 cited.
  - Spec written: tests/e2e/conditional-visibility-editor.spec.ts, 23 scenarios cited, not yet run (cloud, 2026-11-19)
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 22 of 22 green, every listed test run (checked against `playwright test --list`).
- [ ] 3.14 `dashboard-kiosk-mode`: 23 scenarios, 0 cited.
  - Spec written: tests/e2e/dashboard-kiosk-mode.spec.ts (new), 24 scenarios cited, not yet run (cloud, 2026-11-19)
  - Spec written: `tests/e2e/dashboard-kiosk-mode.spec.ts`, `tests/e2e/kiosk-position-indicator.spec.ts` (synced in by #1766), 30 scenarios in the spec now, 26 cited, 4 excluded with a reason (playlist read-only marker, shared throttle bucket, refresh clamp with no input, watchdog never built), 0 uncited; not yet run (cloud, 2026-11-24)
- [x] 3.15 `dashboard-switcher`: 20 scenarios, 11 cited.
  - Spec written: tests/e2e/dashboard-switcher.spec.ts (new), 19 scenarios cited, not yet run (cloud, 2026-11-19)
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 15 of 15 green, every listed test run (checked against `playwright test --list`).
- [ ] 3.16 `runtime-shell`: 16 scenarios, 5 cited.
  - Spec written: tests/e2e/runtime-shell.spec.ts (new), with tests/e2e/support/firstVisit.ts, 20 scenarios cited (11 by the new file), not yet run (cloud, 2026-11-19)
  - Spec written: `tests/e2e/runtime-shell.spec.ts`, `tests/e2e/runtime-shell-canEdit.spec.ts`, `tests/e2e/admin-menu-permissions.spec.ts` (six annotations that matched no scenario heading removed or retargeted: the tour header now names the two current tour scenarios its tests already cite; the two empty-state fixme tests sit under scenarios that carry a reasoned exclusion; the three admin-menu permission slugs never had a scenario in any spec), 28 scenarios cited, 7 excluded with a reason, not yet run (cloud, 2026-11-24)
- [ ] 3.17 `dashboard-deeplinking`: 7 scenarios, 0 cited.
  - Written: `tests/e2e/dashboard-deeplinking.spec.ts` cites 6 scenarios (stale slug lands on a dashboard with 200, a slug address opens that dashboard, the path endpoint, one pushState per switch and none on mount, back returns to the previous dashboard, the health route); their exclusions are removed. The route-table order keeps a reasoned exclusion. The NULL-slug half of the path endpoint stays with DashboardApiControllerComputePathTest (a created dashboard always gets a derived slug). Open: the CI run that shows they ran, and the seen-to-fail mutation pass.
- [x] 3.18 `default-widget-bundle`: 6 scenarios, 0 cited.
  - Written: `tests/e2e/default-widget-bundle.spec.ts` cites 4 scenarios (the create response and the rendered grid, the preset tile columns, the envelope, the loading shim held on the active-dashboard read). The 2 left keep a reasoned exclusion naming their PHPUnit tests: the seedDefaults flag of the bootstrap (not visible in a browser) and the admin-template override (no e2e yet). Open: the CI run that shows they ran, and the seen-to-fail mutation pass.
  - Run 21 Nov on development after #1476 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 4 of 4 green.
- [ ] 3.19 `effective-default-marker`: 5 scenarios, 0 cited.
  - Written: `tests/e2e/effective-default-marker.spec.ts` cites all 5 scenarios, and their exclusions are removed from the spec. Open: the CI run that shows they ran, and the seen-to-fail mutation pass.
  - Spec written: `tests/e2e/effective-default-marker.spec.ts`, 5 scenarios cited, 0 excluded; "the pin wins over a group fallback" no longer skips when no group dashboard is on screen but asserts one is (SeedDefaultDashboard provisions it), so it can fail; not yet run (cloud, 2026-11-24)

## 4. Tier 3, widgets

24 specs, 817 scenarios.

- [ ] 4.1 `text-display-widget`: 90 scenarios, 9 cited.
  - Spec written: `tests/e2e/text-display-widget.spec.ts`, `text-display-widget-markdown.spec.ts`, `text-display-widget-table.spec.ts`, `text-display-widget-table-editor.spec.ts`; 1 scenario excluded with a reason, 89 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.2 `news-widget`: 58 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/news-widget.spec.ts`, `news-widget-server.spec.ts`, `news-widget-rendering.spec.ts`; 19 scenarios excluded with reasons, 39 scenarios cited, not yet run (cloud, 2026-11-19)
- [x] 4.3 `files-widget`: 57 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/files-widget.spec.ts`, `files-widget-listing.spec.ts`, `files-widget-navigation.spec.ts`, `files-widget-actions.spec.ts`, `files-widget-access.spec.ts`; 6 scenarios excluded with reasons, 51 scenarios cited, not yet run (cloud, 2026-11-19)
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 51 of 51 green, every listed test run (checked against `playwright test --list`).
- [ ] 4.4 `header-widget`: 56 scenarios, 0 cited.
  - Spec written: `tests/e2e/header-widget.spec.ts`, `header-widget-content.spec.ts`, `header-widget-a11y.spec.ts`, `header-widget-form.spec.ts`; 7 scenarios excluded with reasons, 49 scenarios cited, not yet run (cloud, 2026-11-19)
- [x] 4.5 `calendar-widget`: 52 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/calendar-widget.spec.ts`, `calendar-widget-sources.spec.ts`, `calendar-widget-rendering.spec.ts`, `calendar-widget-config.spec.ts`; 13 scenarios excluded with reasons, 39 scenarios cited, not yet run (cloud, 2026-11-19)
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 39 of 39 green, every listed test run (checked against `playwright test --list`).
- [ ] 4.6 `links-widget`: 52 scenarios, 0 cited.
  - Spec written: `tests/e2e/links-widget.spec.ts`, `links-widget-grid-navigation.spec.ts`, `links-widget-config.spec.ts`, `links-widget-editor.spec.ts`; 0 scenarios excluded, 52 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.7 `video-widget`: 51 scenarios, 0 cited.
  - Spec written: `tests/e2e/video-widget.spec.ts`, `video-widget-files.spec.ts` (with `fixtures/tiny.webm`); 18 scenarios excluded with reasons, 33 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.8 `link-button-widget`: 50 scenarios, 0 cited.
  - Spec written: `tests/e2e/link-button-widget.spec.ts`, `link-button-widget-list.spec.ts`, `link-button-widget-form.spec.ts`; 3 scenarios excluded with reasons, 48 scenarios cited, not yet run (cloud, 2026-11-19)
  - Spec written: `tests/e2e/link-button-widget-list.spec.ts` (citation for "An action_id entry is never rendered as a link" corrected to `an-actionid-entry-is-never-rendered-as-a-link`, since gate-19 strips `_`), 49 scenarios cited, 3 excluded with reasons, of 52, not yet run (cloud, 2026-11-24)
- [ ] 4.9 `people-widget`: 49 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/people-widget.spec.ts`, `people-widget-config.spec.ts`, `people-widget-rendering.spec.ts`, `people-widget-search.spec.ts`, `people-widget-profile-fields.spec.ts`; 8 scenarios excluded with reasons, 50 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.10 `image-widget`: 45 scenarios, 8 cited.
  - Spec written: `tests/e2e/image-widget-scenarios.spec.ts` (new) with `image-widget.spec.ts` and `image-widget-clickthrough.spec.ts`; 20 scenarios excluded with reasons, 25 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.11 `menu-widget`: 37 scenarios, 0 cited.
  - Spec written: `tests/e2e/menu-widget.spec.ts`, `menu-widget-styles.spec.ts`, `menu-widget-links.spec.ts`; 2 scenarios excluded with reasons, 35 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.12 `quicklinks-widget`: 36 scenarios, 0 cited.
  - Spec written: `tests/e2e/quicklinks-widget.spec.ts`, `quicklinks-widget-interaction.spec.ts`, `quicklinks-widget-form.spec.ts`; 0 scenarios excluded, 36 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.13 `divider-widget`: 29 scenarios, 3 cited.
  - Spec written: tests/e2e/divider-widget.spec.ts, 24 scenarios cited, not yet run (cloud, 2026-11-19), 2 excluded with a reason
- [x] 4.14 `clock-weather-widgets`: 21 scenarios, 0 cited.
  - Spec written: tests/e2e/clock-weather-widgets.spec.ts, 15 scenarios cited, not yet run (cloud, 2026-11-19), 6 excluded with a reason
  - Run 21 Nov on development after #1476 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 15 of 16 green; the remaining test is the existing fixme for a requirement that was never built.
- [x] 4.15 `launchpad-spend-analytics-widget`: 21 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/launchpad-spend-analytics-widget.spec.ts`, 9 scenarios cited, 12 excluded with a reason (8 never built, 4 unit-only, CLI or source scan), not yet run (cloud, 2026-11-19). The blanket was wrong: the widget was built in 7d7da1bb and renders today through nc-vue `CnSpendAnalyticsWidget`; the manifest entry it describes was removed in 2cf53831.
  - Run 21 Nov on development after #1476 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 8 of 8 green.
- [x] 4.16 `launchpad-ai-dashboard-assistant`: 18 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/launchpad-ai-dashboard-assistant.spec.ts, 8 scenarios cited, 3 excluded with a reason, not yet run (cloud, 2026-11-19)
  - Run 21 Nov on development after #1476 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 7 of 7 green.
- [x] 4.17 `iframe-embed-widget`: 15 scenarios, 0 cited.
  - Spec written: tests/e2e/iframe-embed-widget.spec.ts, 14 scenarios cited, not yet run (cloud, 2026-11-19), 2 excluded with a reason
  - Run 21 Nov on development after #1476 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 12 of 12 green (the allow-list fixes of #1471).
- [x] 4.18 `launchpad-mobile-remote-access`: 15 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/launchpad-mobile-remote-access.spec.ts`, 1 scenario cited, 14 excluded with a reason (8 never built: `mobileReady` appears in no commit under src, lib, appinfo or templates (`git log --all -S`); 6 need an expired session, an https feed host, or are source scans), not yet run (cloud, 2026-11-19)
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 1 of 1 green, every listed test run (checked against `playwright test --list`).
- [x] 4.19 `live-data-tile-widget`: 15 scenarios, 0 cited.
  - Spec written: tests/e2e/live-data-tile-widget.spec.ts, 14 scenarios cited, not yet run (cloud, 2026-11-19), 4 excluded with a reason
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 15 of 15 green, every listed test run (checked against `playwright test --list`).
- [ ] 4.20 `launchpad-meeting-calendar-actions`: 14 scenarios, 0 cited, blanket.
  - Spec written: none, 0 scenarios cited, 14 excluded as never built, not yet run (cloud, 2026-11-19). The blanket is replaced by 14 per-scenario verdicts. `launchpad_meeting_actions` and `meeting-actions` appear in no commit on any branch under src, lib, appinfo or templates (`git log --all -S`); the existing `calendar` widget reads LaunchPad's own events endpoint, not CalDAV or decidesk.
- [x] 4.21 `label-widget`: 12 scenarios, 11 cited.
  - Spec written: tests/e2e/label-widget-content-edit.spec.ts, 1 scenarios cited, not yet run (cloud, 2026-11-19)
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 2 of 2 green, every listed test run (checked against `playwright test --list`).
- [ ] 4.22 `launchpad-file-access-widget`: 12 scenarios, 0 cited, blanket.
  - Spec written: none, 0 scenarios cited, 12 excluded as never built, not yet run (cloud, 2026-11-19). The blanket is replaced by 12 per-scenario verdicts. `launchpad_file_access` and `file-access` appear in no commit on any branch under src, lib, appinfo or templates (`git log --all -S`); opening a file from the existing `files` widget belongs to the files-widget spec.
- [x] 4.23 `container-widget`: 8 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/container-widget.spec.ts, tests/e2e/container-tile-sorting.spec.ts, 11 scenarios cited, not yet run (cloud, 2026-11-19), 1 excluded with a reason
  - Run 21 Nov on development after #1476 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): container-widget 7 of 7 and container-tile-sorting 6 of 6 green.
- [ ] 4.24 `nc-dashboard-widget-proxy`: 4 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/nc-dashboard-widget-proxy.spec.ts, 4 scenarios cited, not yet run (cloud, 2026-11-19)

## 5. Tier 4, admin, operations and integrations

14 specs, 419 scenarios.

- [ ] 5.1 `dashboard-view-analytics`: 65 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-view-analytics.spec.ts, 53 scenarios cited, not yet run (cloud, 2026-11-19), 18 excluded with reasons
  - Spec written: tests/e2e/dashboard-view-analytics-jobs.spec.ts, 5 more scenarios cited (the salt rotation and four retention/purge scenarios, by running the jobs through occ), 13 excluded with reasons, not yet run (cloud, 2026-11-24)
  - Spec written: tests/e2e/dashboard-view-analytics-history.spec.ts (+ tests/e2e/support/instanceDb.ts), 8 more scenarios cited (both table schemas, two days, the salt across days, both period filters, both purge deletions) by seeding and reading rows through PHP on the instance, 5 excluded with reasons, not yet run (cloud, 2026-11-24)
  - Spec written: tests/e2e/dashboard-view-analytics-history.spec.ts, 1 more scenario cited (the tile-click salt is the one analytics_dailysalt), 67 cited, 4 excluded with reasons, not yet run (cloud, 2026-11-24)
  - Spec written: tests/e2e/dashboard-view-analytics-cache.spec.ts, 2 more scenarios cited (the dedup cache key and its TTL, the hash kept in the cache and out of the views table) by reading the distributed cache on the instance; they skip, saying why, where that cache is per-process. 69 cited, 2 excluded with reasons, not yet run (cloud, 2026-11-24)
- [x] 5.2 `background-job-feed-refresh`: 56 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/background-job-feed-refresh.spec.ts, 6 scenarios cited, not yet run (cloud, 2026-11-19), 50 excluded with reasons
  - Run 20 Nov on development with #1444: 6 of 6 green.
- [ ] 5.3 `cli-commands`: 53 scenarios, 0 cited, blanket.
  - Spec written: none (tests/e2e/cli-commands.spec.ts not created), 0 scenarios cited, 60 excluded with reasons, not yet run (cloud, 2026-11-19)
  - Spec written: tests/e2e/cli-commands.spec.ts (+ tests/e2e/support/occ.ts), 39 scenarios cited, 21 excluded with reasons, not yet run (cloud, 2026-11-24). The old "Playwright cannot run occ" reason was wrong: the suite already runs occ on the instance.
  - Spec written: tests/e2e/cli-commands.spec.ts, 1 more scenario cited (an operator updates the installed template: an older install staged through occ and tests/e2e/support/instanceDb.ts), 40 cited, 20 excluded with reasons, not yet run (cloud, 2026-11-24)
- [x] 5.4 `activity-feed-integration`: 52 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/activity-feed-integration.spec.ts, 21 scenarios cited, not yet run (cloud, 2026-11-19), 39 excluded with reasons
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 22 of 22 green, every listed test run (checked against `playwright test --list`).
- [x] 5.5 `nc-unified-search-integration`: 48 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/nc-unified-search-integration.spec.ts, 42 scenarios cited, not yet run (cloud, 2026-11-19), 6 excluded with reasons
  - Run 21-22 Nov on development after #1502 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 42 of 42 green, every listed test run (checked against `playwright test --list`).
- [x] 5.6 `confluence-html-import`: 41 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/confluence-html-import.spec.ts, 37 scenarios cited, not yet run (cloud, 2026-11-19), 4 excluded with reasons
  - Run 20-21 Nov on development (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 37 of 37 green.
- [x] 5.7 `prometheus-metrics`: 23 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/prometheus-metrics.spec.ts, 15 scenarios cited, not yet run (cloud, 2026-11-19), 10 excluded with reasons
  - Run 21 Nov on development after #1476 (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 15 of 15 green.
- [ ] 5.8 `launchpad-adopt-or-abstractions`: 20 scenarios, 1 cited.
  - Spec written: tests/e2e/launchpad-adopt-or-abstractions.spec.ts (+ tests/e2e/support/orWidget.ts), 3 new scenarios cited (4 with manifest-boot.spec.ts), 16 excluded with reasons, not yet run (cloud, 2026-11-19)
  - Spec written: tests/e2e/or-absent.spec.ts, 2 more scenarios cited (install and sharing on an OR-less instance, made by disabling openregister/openconnector through occ for the file), 6 cited, 14 excluded with reasons, not yet run (cloud, 2026-11-24)
- [x] 5.9 `infrastructure-helpers`: 17 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/infrastructure-helpers.spec.ts, 14 scenarios cited, 3 excluded with reasons, not yet run (cloud, 2026-11-19)
  - Run 20-21 Nov on development (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 14 of 14 green.
- [ ] 5.10 `service-health-ping`: 15 scenarios, 0 cited.
  - Spec written: tests/e2e/service-health-ping.spec.ts, 14 scenarios cited, not yet run (cloud, 2026-11-19), 1 excluded with reasons
  - Spec written: tests/e2e/service-health-ping.spec.ts, the last scenario (background refresh) now cited by running HealthPingRefreshJob through occ, 0 excluded, not yet run (cloud, 2026-11-24)
- [x] 5.11 `initial-state-contract`: 11 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/initial-state-contract.spec.ts, 2 scenarios cited, 9 excluded with reasons, not yet run (cloud, 2026-11-19)
  - Run 20-21 Nov on development (Nextcloud 34.0.6, OpenRegister 2.2.0-dev, live pass stack): 4 of 4 green.
- [ ] 5.12 `runtime-or-consumption`: 11 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/runtime-or-consumption.spec.ts (+ tests/e2e/support/orWidget.ts), 7 scenarios cited, 4 excluded with reasons, not yet run (cloud, 2026-11-19)
  - Spec written: tests/e2e/or-absent.spec.ts, 2 more scenarios cited (boot without OR, the OR-data widget's empty state without OR), 8 cited, 2 excluded with reasons (both never built), not yet run (cloud, 2026-11-24)
- [ ] 5.13 `license-header-consistency`: 7 scenarios, 0 cited, blanket.
  - Spec written: no spec file, 0 scenarios cited, 7 excluded with reasons, one per requirement; the blanket is gone. Every scenario reads checked-in files or CI behaviour, not a running instance. REQ-LIC-003 does not hold: 12 files under `src/` still name `LaunchPad Contributors` (cloud, 2026-11-19)
- [ ] 5.14 `groupfolder-storage-backend`: 0 scenarios, 0 cited. Has no scenarios. Decide whether the spec is still wanted.
  - Decided: keep it. The spec is marked `status: withdrawn` and records why the capability was retired (launchpad#87) and what was removed. With no requirements and no scenarios it owes gate-19 nothing; archiving or deleting it is a separate change (cloud, 2026-11-19)
  - Spec written: tests/e2e/groupfolder-storage-backend.spec.ts, 1 scenario cited, 1 excluded with reason, not yet run (cloud, 2026-11-24). The spec has since gained REQ-GFSB-011 with 2 scenarios, so it does owe gate-19 now.
  - Spec written: tests/e2e/groupfolder-storage-backend.spec.ts, 2 scenarios cited, 0 excluded, not yet run (cloud, 2026-11-24). "No storage commands" now lists the occ commands through tests/e2e/support/occ.ts.

## 6. Close the programme

- [ ] 6.1 Re-measure every spec with the same count, and publish the before and after totals in this change.
- [ ] 6.2 Confirm no spec-level `@e2e exclude` remains in `openspec/specs/`.
- [ ] 6.3 Archive `add-dashboard-sharing-e2e-coverage` once tier 0 covers it, repointing any citation into it first.
