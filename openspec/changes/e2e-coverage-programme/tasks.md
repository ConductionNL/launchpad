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
- [x] 1.8 `launchpad-enterprise-security-access`: 15 scenarios, 0 cited, blanket. Done 2026-09-11: the blanket is replaced by 15 per-scenario verdicts, all `never built`. `launchpad_security_access` appears in no commit on any branch under src, lib, appinfo or templates (`git log --all -S`).

## 2. Tier 1, data integrity

11 specs, 341 scenarios.

- [ ] 2.1 `dashboard-bulk-operations`: 50 scenarios, 0 cited, blanket.
- [ ] 2.2 `resource-uploads`: 46 scenarios, 0 cited, blanket.
- [ ] 2.3 `dashboard-versioning`: 38 scenarios, 0 cited, blanket.
- [ ] 2.4 `orphaned-data-cleanup`: 36 scenarios, 0 cited, blanket.
- [ ] 2.5 `dashboard-cascade-events`: 35 scenarios, 1 cited, blanket.
- [ ] 2.6 `dashboard-locking`: 29 scenarios, 0 cited, blanket.
- [ ] 2.7 `dashboard-metadata-fields`: 28 scenarios, 0 cited, blanket.
- [ ] 2.8 `dashboard-quota-limits`: 24 scenarios, 0 cited, blanket.
- [ ] 2.9 `legacy-widget-bridge`: 21 scenarios, 0 cited, blanket.
- [ ] 2.10 `launchpad-compliance-audit-panel`: 18 scenarios, 0 cited, blanket.
- [ ] 2.11 `dashboard-acknowledgements`: 16 scenarios, 3 cited.

## 3. Tier 2, core surfaces

19 specs, 823 scenarios.

- [ ] 3.1 `dashboards`: 167 scenarios, 0 cited, blanket. The largest spec. Split it by requirement across several PRs.
- [ ] 3.2 `widgets`: 110 scenarios, 0 cited, blanket. Split it by requirement across several PRs.
- [ ] 3.3 `admin-templates`: 86 scenarios, 0 cited, blanket.
- [ ] 3.4 `grid-layout`: 56 scenarios, 16 cited.
- [ ] 3.5 `setup-wizard`: 55 scenarios, 0 cited, blanket.
- [ ] 3.6 `footer-customization`: 47 scenarios, 0 cited, blanket.
- [ ] 3.7 `navigation-editor-org`: 45 scenarios, 0 cited, blanket.
- [ ] 3.8 `tiles`: 38 scenarios, 0 cited.
- [ ] 3.9 `dashboard-language-content`: 35 scenarios, 0 cited, blanket.
- [ ] 3.10 `dashboard-reactions`: 31 scenarios, 0 cited, blanket.
- [ ] 3.11 `tile-quick-search`: 28 scenarios, 8 cited.
- [ ] 3.12 `dashboard-icons`: 25 scenarios, 0 cited, blanket.
- [ ] 3.13 `conditional-visibility-editor`: 23 scenarios, 13 cited.
- [ ] 3.14 `dashboard-kiosk-mode`: 23 scenarios, 0 cited.
- [ ] 3.15 `dashboard-switcher`: 20 scenarios, 11 cited.
- [ ] 3.16 `runtime-shell`: 16 scenarios, 5 cited.
- [ ] 3.17 `dashboard-deeplinking`: 7 scenarios, 0 cited.
- [ ] 3.18 `default-widget-bundle`: 6 scenarios, 0 cited.
- [ ] 3.19 `effective-default-marker`: 5 scenarios, 0 cited.

## 4. Tier 3, widgets

24 specs, 817 scenarios.

- [ ] 4.1 `text-display-widget`: 90 scenarios, 9 cited.
- [ ] 4.2 `news-widget`: 58 scenarios, 0 cited, blanket.
- [ ] 4.3 `files-widget`: 57 scenarios, 0 cited, blanket.
- [ ] 4.4 `header-widget`: 56 scenarios, 0 cited.
- [ ] 4.5 `calendar-widget`: 52 scenarios, 0 cited, blanket.
- [ ] 4.6 `links-widget`: 52 scenarios, 0 cited.
- [ ] 4.7 `video-widget`: 51 scenarios, 0 cited.
- [ ] 4.8 `link-button-widget`: 50 scenarios, 0 cited.
- [ ] 4.9 `people-widget`: 49 scenarios, 0 cited, blanket.
- [ ] 4.10 `image-widget`: 45 scenarios, 8 cited.
- [ ] 4.11 `menu-widget`: 37 scenarios, 0 cited.
- [ ] 4.12 `quicklinks-widget`: 36 scenarios, 0 cited.
- [ ] 4.13 `divider-widget`: 29 scenarios, 3 cited.
- [ ] 4.14 `clock-weather-widgets`: 21 scenarios, 0 cited.
- [ ] 4.15 `launchpad-spend-analytics-widget`: 21 scenarios, 0 cited, blanket.
- [ ] 4.16 `launchpad-ai-dashboard-assistant`: 18 scenarios, 0 cited, blanket.
- [ ] 4.17 `iframe-embed-widget`: 15 scenarios, 0 cited.
- [ ] 4.18 `launchpad-mobile-remote-access`: 15 scenarios, 0 cited, blanket.
- [ ] 4.19 `live-data-tile-widget`: 15 scenarios, 0 cited.
- [ ] 4.20 `launchpad-meeting-calendar-actions`: 14 scenarios, 0 cited, blanket.
- [ ] 4.21 `label-widget`: 12 scenarios, 11 cited.
- [ ] 4.22 `launchpad-file-access-widget`: 12 scenarios, 0 cited, blanket.
- [ ] 4.23 `container-widget`: 8 scenarios, 0 cited, blanket.
- [ ] 4.24 `nc-dashboard-widget-proxy`: 4 scenarios, 0 cited, blanket.

## 5. Tier 4, admin, operations and integrations

14 specs, 419 scenarios.

- [ ] 5.1 `dashboard-view-analytics`: 65 scenarios, 0 cited, blanket.
- [ ] 5.2 `background-job-feed-refresh`: 56 scenarios, 0 cited, blanket.
- [ ] 5.3 `cli-commands`: 53 scenarios, 0 cited, blanket.
- [ ] 5.4 `activity-feed-integration`: 52 scenarios, 0 cited, blanket.
- [ ] 5.5 `nc-unified-search-integration`: 48 scenarios, 0 cited, blanket.
- [ ] 5.6 `confluence-html-import`: 41 scenarios, 0 cited, blanket.
- [ ] 5.7 `prometheus-metrics`: 23 scenarios, 0 cited, blanket.
- [ ] 5.8 `launchpad-adopt-or-abstractions`: 20 scenarios, 1 cited.
- [ ] 5.9 `infrastructure-helpers`: 17 scenarios, 0 cited, blanket.
- [ ] 5.10 `service-health-ping`: 15 scenarios, 0 cited.
- [ ] 5.11 `initial-state-contract`: 11 scenarios, 0 cited, blanket.
- [ ] 5.12 `runtime-or-consumption`: 11 scenarios, 0 cited, blanket.
- [ ] 5.13 `license-header-consistency`: 7 scenarios, 0 cited, blanket.
- [ ] 5.14 `groupfolder-storage-backend`: 0 scenarios, 0 cited. Has no scenarios. Decide whether the spec is still wanted.

## 6. Close the programme

- [ ] 6.1 Re-measure every spec with the same count, and publish the before and after totals in this change.
- [ ] 6.2 Confirm no spec-level `@e2e exclude` remains in `openspec/specs/`.
- [ ] 6.3 Archive `add-dashboard-sharing-e2e-coverage` once tier 0 covers it, repointing any citation into it first.
