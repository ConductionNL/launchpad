# Tasks

One PR per spec, or per requirement group for the two largest. Each task is done when the spec meets "How a spec is done" in `proposal.md`.

Counts are scenarios, and scenarios cited by a Playwright test, measured on `development` on 2026-09-11. "Blanket" means the spec hides every scenario behind one spec-level `@e2e exclude`.

## Done

- [x] `dashboard-export-import`: 48 scenarios, each with its own verdict. Three import defects fixed (#616, with #612 before it).
- [x] `demo-data-showcases`: per-scenario verdicts replaced the stale "no UI surface" exclusion (#606).

## 1. Tier 0, safety

8 specs, 343 scenarios.

- [ ] 1.1 `admin-settings`: 83 scenarios, 2 cited, blanket. Access requirements in progress 2026-09-11. Its settings-behaviour scenarios stay open under this task.
- [ ] 1.2 `admin-roles`: 55 scenarios, 0 cited, blanket. In progress 2026-09-11.
- [ ] 1.3 `conditional-visibility`: 49 scenarios, 0 cited, blanket. In progress 2026-09-11.
- [ ] 1.4 `permissions`: 42 scenarios, 0 cited, blanket. In progress 2026-09-11, with `admin-roles` and the access requirements of `admin-settings`.
- [ ] 1.5 `dashboard-public-share`: 38 scenarios, 25 cited. In progress 2026-09-11.
- [ ] 1.6 `dashboard-sharing`: 34 scenarios, 10 cited. In progress 2026-09-11. Supersedes `add-dashboard-sharing-e2e-coverage`.
- [ ] 1.7 `role-feature-permissions`: 27 scenarios, 0 cited. Queued 2026-09-11, after the permissions PR.
  - Spec written: `tests/e2e/role-feature-permissions.spec.ts` (helpers in `tests/e2e/fixtures/role-feature-permissions.ts`), 27 scenarios cited, not yet run (cloud, 2026-11-19)
- [x] 1.8 `launchpad-enterprise-security-access`: 15 scenarios, 0 cited, blanket. Done 2026-09-11: the blanket is replaced by 15 per-scenario verdicts, all `never built`. `launchpad_security_access` appears in no commit on any branch under src, lib, appinfo or templates (`git log --all -S`).

## 2. Tier 1, data integrity

11 specs, 341 scenarios.

- [ ] 2.1 `dashboard-bulk-operations`: 50 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-bulk-operations.spec.ts, tests/e2e/dashboard-bulk-operations-ui.spec.ts, 44 scenarios cited, not yet run (cloud, 2026-11-19). 6 excluded with a reason.
- [ ] 2.2 `resource-uploads`: 46 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/resource-uploads.spec.ts, 37 scenarios cited, not yet run (cloud, 2026-11-19). 7 excluded with a reason.
- [ ] 2.3 `dashboard-versioning`: 38 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-versioning.spec.ts, tests/e2e/dashboard-versioning-ui.spec.ts, 29 scenarios cited, not yet run (cloud, 2026-11-19). 14 excluded with a reason.
- [ ] 2.4 `orphaned-data-cleanup`: 36 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/orphaned-data-cleanup.spec.ts, 13 scenarios cited, not yet run (cloud, 2026-11-19). 23 excluded with a reason.
- [ ] 2.5 `dashboard-cascade-events`: 35 scenarios, 1 cited, blanket.
  - Spec written: tests/e2e/dashboard-cascade-events.spec.ts, 9 scenarios cited (8 new), not yet run (cloud, 2026-11-19). 26 excluded with a reason.
- [ ] 2.6 `dashboard-locking`: 29 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-locking.spec.ts, 17 scenarios cited, not yet run (cloud, 2026-11-19). 17 excluded with a reason.
- [ ] 2.7 `dashboard-metadata-fields`: 28 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-metadata-fields.spec.ts, 30 scenarios cited, not yet run (cloud, 2026-11-19). 2 excluded with a reason.
- [ ] 2.8 `dashboard-quota-limits`: 24 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-quota-limits.spec.ts, 20 scenarios cited, not yet run (cloud, 2026-11-19). 4 excluded with a reason.
- [ ] 2.9 `legacy-widget-bridge`: 21 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/legacy-widget-bridge.spec.ts, 4 scenarios cited, not yet run (cloud, 2026-11-19). 17 excluded with a reason.
- [ ] 2.10 `launchpad-compliance-audit-panel`: 18 scenarios, 0 cited, blanket.
  - Spec written: openspec/specs/launchpad-compliance-audit-panel/spec.md (no e2e file; never built), 0 scenarios cited, not yet run (cloud, 2026-11-19). 18 excluded as never built.
- [ ] 2.11 `dashboard-acknowledgements`: 16 scenarios, 3 cited.
  - Spec written: tests/e2e/dashboard-acknowledgements.spec.ts, tests/e2e/dashboard-acknowledgements-template.spec.ts, tests/e2e/dashboard-acknowledgements-ask.spec.ts, 22 scenarios cited, not yet run (cloud, 2026-11-19). 2 excluded with a reason.

## 3. Tier 2, core surfaces

19 specs, 823 scenarios.

- [ ] 3.1 `dashboards`: 167 scenarios, 0 cited, blanket. The largest spec. Split it by requirement across several PRs.
  - Spec written: `tests/e2e/dashboards-crud.spec.ts`, `dashboards-group-shared.spec.ts`, `dashboards-resolution.spec.ts`, `dashboards-fork.spec.ts`, `dashboards-tree.spec.ts`, `dashboards-tree-ui.spec.ts`, `dashboards-publication.spec.ts`, `dashboards-personal-layer.spec.ts`, `dashboards-group-admin.spec.ts`, `dashboards-look.spec.ts`, `dashboards-api-contract.spec.ts` (helpers `support/dashboardsSpec.ts`, `support/dashboardHierarchy.ts`), 162 scenarios cited (fixme tests for suspected defects, see the PR), 26 excluded, not yet run (cloud, 2026-11-19)
- [ ] 3.2 `widgets`: 110 scenarios, 0 cited, blanket. Split it by requirement across several PRs.
  - Spec written: `tests/e2e/widgets-placements-api.spec.ts`, `widgets-rendering.spec.ts`, `widgets-picker.spec.ts`, `widgets-context-menu-contract.spec.ts`, `widgets-nc-widget.spec.ts`, `widgets-tile-type.spec.ts`, `widgets-canvas-search.spec.ts`, `widgets-reload-interval.spec.ts` (helpers `support/widgets.ts`, `support/widgetPlacements.ts`), 99 scenarios cited (12 tests `test.fixme` for suspected defects, see the PR), 21 excluded, not yet run (cloud, 2026-11-19)
- [ ] 3.3 `admin-templates`: 86 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/admin-templates-crud.spec.ts`, `admin-templates-distribution.spec.ts`, `admin-templates-gallery.spec.ts`, `admin-templates-resync.spec.ts`, `admin-templates-shipped.spec.ts`, `admin-templates-ui.spec.ts` (helper `support/adminTemplates.ts`), 100 scenarios cited (10 as `test.fixme` for suspected defects, see the PR), 13 excluded, not yet run (cloud, 2026-11-19)
- [ ] 3.4 `grid-layout`: 56 scenarios, 16 cited.
  - Spec written: `tests/e2e/grid-layout-drag-resize.spec.ts`, `grid-layout-keyboard.spec.ts`, `grid-layout-placement.spec.ts`, `grid-layout-rendering.spec.ts` (helper `support/ownDashboard.ts`), 56 scenarios cited in total (fixme tests for suspected defects, see the PR), 8 excluded, not yet run (cloud, 2026-11-19)
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
- [ ] 3.10 `dashboard-reactions`: 31 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/dashboard-reactions.spec.ts`, `tests/e2e/dashboard-reactions-ui.spec.ts`, 27 scenarios cited, 6 excluded, not yet run (cloud, 2026-11-19)
- [ ] 3.11 `tile-quick-search`: 28 scenarios, 8 cited.
  - Spec written: tests/e2e/tile-quick-search-widget.spec.ts (new) and tests/e2e/tile-quick-search.spec.ts, 33 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 3.12 `dashboard-icons`: 25 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/dashboard-icons.spec.ts (new), 21 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 3.13 `conditional-visibility-editor`: 23 scenarios, 13 cited.
  - Spec written: tests/e2e/conditional-visibility-editor.spec.ts, 23 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 3.14 `dashboard-kiosk-mode`: 23 scenarios, 0 cited.
  - Spec written: tests/e2e/dashboard-kiosk-mode.spec.ts (new), 24 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 3.15 `dashboard-switcher`: 20 scenarios, 11 cited.
  - Spec written: tests/e2e/dashboard-switcher.spec.ts (new), 19 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 3.16 `runtime-shell`: 16 scenarios, 5 cited.
  - Spec written: tests/e2e/runtime-shell.spec.ts (new), with tests/e2e/support/firstVisit.ts, 20 scenarios cited (11 by the new file), not yet run (cloud, 2026-11-19)
- [ ] 3.17 `dashboard-deeplinking`: 7 scenarios, 0 cited.
  - Written: `tests/e2e/dashboard-deeplinking.spec.ts` cites 6 scenarios (stale slug lands on a dashboard with 200, a slug address opens that dashboard, the path endpoint, one pushState per switch and none on mount, back returns to the previous dashboard, the health route); their exclusions are removed. The route-table order keeps a reasoned exclusion. The NULL-slug half of the path endpoint stays with DashboardApiControllerComputePathTest (a created dashboard always gets a derived slug). Open: the CI run that shows they ran, and the seen-to-fail mutation pass.
- [ ] 3.18 `default-widget-bundle`: 6 scenarios, 0 cited.
  - Written: `tests/e2e/default-widget-bundle.spec.ts` cites 4 scenarios (the create response and the rendered grid, the preset tile columns, the envelope, the loading shim held on the active-dashboard read). The 2 left keep a reasoned exclusion naming their PHPUnit tests: the seedDefaults flag of the bootstrap (not visible in a browser) and the admin-template override (no e2e yet). Open: the CI run that shows they ran, and the seen-to-fail mutation pass.
- [ ] 3.19 `effective-default-marker`: 5 scenarios, 0 cited.
  - Written: `tests/e2e/effective-default-marker.spec.ts` cites all 5 scenarios, and their exclusions are removed from the spec. Open: the CI run that shows they ran, and the seen-to-fail mutation pass.

## 4. Tier 3, widgets

24 specs, 817 scenarios.

- [ ] 4.1 `text-display-widget`: 90 scenarios, 9 cited.
  - Spec written: `tests/e2e/text-display-widget.spec.ts`, `text-display-widget-markdown.spec.ts`, `text-display-widget-table.spec.ts`, `text-display-widget-table-editor.spec.ts`; 1 scenario excluded with a reason, 89 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.2 `news-widget`: 58 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/news-widget.spec.ts`, `news-widget-server.spec.ts`, `news-widget-rendering.spec.ts`; 19 scenarios excluded with reasons, 39 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.3 `files-widget`: 57 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/files-widget.spec.ts`, `files-widget-listing.spec.ts`, `files-widget-navigation.spec.ts`, `files-widget-actions.spec.ts`, `files-widget-access.spec.ts`; 6 scenarios excluded with reasons, 51 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.4 `header-widget`: 56 scenarios, 0 cited.
  - Spec written: `tests/e2e/header-widget.spec.ts`, `header-widget-content.spec.ts`, `header-widget-a11y.spec.ts`, `header-widget-form.spec.ts`; 7 scenarios excluded with reasons, 49 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.5 `calendar-widget`: 52 scenarios, 0 cited, blanket.
  - Spec written: `tests/e2e/calendar-widget.spec.ts`, `calendar-widget-sources.spec.ts`, `calendar-widget-rendering.spec.ts`, `calendar-widget-config.spec.ts`; 13 scenarios excluded with reasons, 39 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.6 `links-widget`: 52 scenarios, 0 cited.
- [ ] 4.7 `video-widget`: 51 scenarios, 0 cited.
- [ ] 4.8 `link-button-widget`: 50 scenarios, 0 cited.
- [ ] 4.9 `people-widget`: 49 scenarios, 0 cited, blanket.
- [ ] 4.10 `image-widget`: 45 scenarios, 8 cited.
  - Spec written: `tests/e2e/image-widget-scenarios.spec.ts` (new) with `image-widget.spec.ts` and `image-widget-clickthrough.spec.ts`; 20 scenarios excluded with reasons, 25 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.11 `menu-widget`: 37 scenarios, 0 cited.
- [ ] 4.12 `quicklinks-widget`: 36 scenarios, 0 cited.
- [ ] 4.13 `divider-widget`: 29 scenarios, 3 cited.
- [ ] 4.14 `clock-weather-widgets`: 21 scenarios, 0 cited.
- [ ] 4.15 `launchpad-spend-analytics-widget`: 21 scenarios, 0 cited, blanket.
- [ ] 4.16 `launchpad-ai-dashboard-assistant`: 18 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/launchpad-ai-dashboard-assistant.spec.ts, 8 scenarios cited, 3 excluded with a reason, not yet run (cloud, 2026-11-19)
- [ ] 4.17 `iframe-embed-widget`: 15 scenarios, 0 cited.
- [ ] 4.18 `launchpad-mobile-remote-access`: 15 scenarios, 0 cited, blanket.
- [ ] 4.19 `live-data-tile-widget`: 15 scenarios, 0 cited.
  - Spec written: tests/e2e/live-data-tile-widget.spec.ts, 14 scenarios cited, not yet run (cloud, 2026-11-19), 4 excluded with a reason
- [ ] 4.20 `launchpad-meeting-calendar-actions`: 14 scenarios, 0 cited, blanket.
- [ ] 4.21 `label-widget`: 12 scenarios, 11 cited.
  - Spec written: tests/e2e/label-widget-content-edit.spec.ts, 1 scenarios cited, not yet run (cloud, 2026-11-19)
- [ ] 4.22 `launchpad-file-access-widget`: 12 scenarios, 0 cited, blanket.
- [ ] 4.23 `container-widget`: 8 scenarios, 0 cited, blanket.
- [ ] 4.24 `nc-dashboard-widget-proxy`: 4 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/nc-dashboard-widget-proxy.spec.ts, 4 scenarios cited, not yet run (cloud, 2026-11-19)

## 5. Tier 4, admin, operations and integrations

14 specs, 419 scenarios.

- [ ] 5.1 `dashboard-view-analytics`: 65 scenarios, 0 cited, blanket.
- [ ] 5.2 `background-job-feed-refresh`: 56 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/background-job-feed-refresh.spec.ts, 6 scenarios cited, not yet run (cloud, 2026-11-19), 50 excluded with reasons
- [ ] 5.3 `cli-commands`: 53 scenarios, 0 cited, blanket.
  - Spec written: none (tests/e2e/cli-commands.spec.ts not created), 0 scenarios cited, 60 excluded with reasons, not yet run (cloud, 2026-11-19)
- [ ] 5.4 `activity-feed-integration`: 52 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/activity-feed-integration.spec.ts, 21 scenarios cited, not yet run (cloud, 2026-11-19), 39 excluded with reasons
- [ ] 5.5 `nc-unified-search-integration`: 48 scenarios, 0 cited, blanket.
- [ ] 5.6 `confluence-html-import`: 41 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/confluence-html-import.spec.ts, 37 scenarios cited, not yet run (cloud, 2026-11-19), 4 excluded with reasons
- [ ] 5.7 `prometheus-metrics`: 23 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/prometheus-metrics.spec.ts, 15 scenarios cited, not yet run (cloud, 2026-11-19), 10 excluded with reasons
- [ ] 5.8 `launchpad-adopt-or-abstractions`: 20 scenarios, 1 cited.
- [ ] 5.9 `infrastructure-helpers`: 17 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/infrastructure-helpers.spec.ts, 14 scenarios cited, 3 excluded with reasons, not yet run (cloud, 2026-11-19)
- [ ] 5.10 `service-health-ping`: 15 scenarios, 0 cited.
  - Spec written: tests/e2e/service-health-ping.spec.ts, 14 scenarios cited, not yet run (cloud, 2026-11-19), 1 excluded with reasons
- [ ] 5.11 `initial-state-contract`: 11 scenarios, 0 cited, blanket.
  - Spec written: tests/e2e/initial-state-contract.spec.ts, 2 scenarios cited, 9 excluded with reasons, not yet run (cloud, 2026-11-19)
- [ ] 5.12 `runtime-or-consumption`: 11 scenarios, 0 cited, blanket.
- [ ] 5.13 `license-header-consistency`: 7 scenarios, 0 cited, blanket.
  - Spec written: no spec file, 0 scenarios cited, 7 excluded with reasons, one per requirement; the blanket is gone. Every scenario reads checked-in files or CI behaviour, not a running instance. REQ-LIC-003 does not hold: 12 files under `src/` still name `LaunchPad Contributors` (cloud, 2026-11-19)
- [ ] 5.14 `groupfolder-storage-backend`: 0 scenarios, 0 cited. Has no scenarios. Decide whether the spec is still wanted.
  - Decided: keep it. The spec is marked `status: withdrawn` and records why the capability was retired (launchpad#87) and what was removed. With no requirements and no scenarios it owes gate-19 nothing; archiving or deleting it is a separate change (cloud, 2026-11-19)

## 6. Close the programme

- [ ] 6.1 Re-measure every spec with the same count, and publish the before and after totals in this change.
- [ ] 6.2 Confirm no spec-level `@e2e exclude` remains in `openspec/specs/`.
- [ ] 6.3 Archive `add-dashboard-sharing-e2e-coverage` once tier 0 covers it, repointing any citation into it first.
