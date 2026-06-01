# Tasks — fork-current-as-personal

## 1. Backend

- [x] Add `WidgetPlacementMapper::cloneToDashboard(int $sourceDashboardId, int $targetDashboardId): void` (bulk INSERT … SELECT with new IDs)
- [x] Add `DashboardService::forkAsPersonal(string $userId, string $sourceUuid, ?string $name): Dashboard` wrapped in `IDBConnection::beginTransaction`
- [x] Add admin-setting check on `allow_user_dashboards`
- [x] Add 404 path for sources the user cannot read (reuse REQ-DASH-013 visibility resolver)
- [x] Default name uses `IL10N::t('My copy of {name}', ['name' => $source->getName()])`
- [x] Add `DashboardController::fork` mapped to `POST /api/dashboards/{uuid}/fork`

## 2. Frontend

- [x] Wire "+ New Dashboard" button in `DashboardSwitcherSidebar` to `forkAsPersonal(activeDashboardUuid, t('My Dashboard'))`
- [x] On 403, surface the toast "Personal dashboards are not enabled by your administrator"
- [x] Optimistic add to `userDashboards` store; rollback on error

## 3. Tests

- [x] PHPUnit: deep-copy preserves all placement fields including tile-* and styleConfig
- [x] PHPUnit: rollback on placement insert failure
- [x] PHPUnit: gating returns 403 when admin setting disabled
- [x] PHPUnit: 404 on source you cannot read
- [x] PHPUnit: forking your own personal dashboard works (independent duplicate)
- [ ] Playwright: fork → switch → edit → original group dashboard untouched

## 4. Quality

- [x] `composer check:strict` passes
- [x] OpenAPI updated for new endpoint
- [x] Document in `dashboards/spec.md` REQ-DASH-005 NOTE that personal dashboards from forks share resource URLs (REQ-DASH-022 cross-reference)
