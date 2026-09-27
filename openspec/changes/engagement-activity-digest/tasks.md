# Tasks: engagement-activity-digest

- [ ] 1. Add four `ActivitySettings` classes under `lib/Activity/Settings/` with the defaults in D1 and register them in `appinfo/info.xml`. Verify: PHPUnit per class and app-info validation.
- [ ] 2. Emit `dashboard_shared` from `DashboardShareService` on share creation to the resolved recipients. Verify: PHPUnit with a mocked publisher.
- [ ] 3. Emit `dashboard_published` from `DashboardService` on publication, including the first surfacing of a scheduled dashboard, once. Verify: PHPUnit.
- [ ] 4. Emit `dashboard_updated` for shared and group dashboards through `DebounceHelper`, once per dashboard per 24 hours. Verify: PHPUnit with a fixed clock.
- [ ] 5. Integration check: with daily batching, a share queues an activity mail row for the recipient. Verify: an integration test or a documented manual run against the dev instance.
- [ ] 6. Add `nl` and `en` strings for the setting labels, fold the delta into `openspec/specs/activity-feed-integration/spec.md` on archive, and set e-digest to `built` in the parity matrix with evidence lines.
