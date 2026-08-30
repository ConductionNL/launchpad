# Tasks — fix-group-dashboard-admin-auth-attribute

## DashboardApiController

- [ ] Task 1: In `lib/Controller/DashboardApiController.php`, import
  `OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting` and
  `OCA\LaunchPad\Settings\LaunchPadAdmin` (or
  `OCA\LaunchPad\AppInfo\Application` for `Application::APP_ID`) alongside
  the existing `NoAdminRequired` import.
- [ ] Task 2: Replace `#[NoAdminRequired]` with
  `#[AuthorizedAdminSetting(Application::APP_ID)]` on `createGroup()`
  (currently line 916).
- [ ] Task 3: Replace `#[NoAdminRequired]` with
  `#[AuthorizedAdminSetting(Application::APP_ID)]` on `updateGroup()`
  (currently line 1026).
- [ ] Task 4: Replace `#[NoAdminRequired]` with
  `#[AuthorizedAdminSetting(Application::APP_ID)]` on `deleteGroup()`
  (currently line 1090).
- [ ] Task 5: Replace `#[NoAdminRequired]` with
  `#[AuthorizedAdminSetting(Application::APP_ID)]` on `setGroupDefault()`
  (currently line 1143).
- [ ] Task 6: Remove the redundant in-body `$this->dashboardService->isAdmin(...)`
  + `ResponseHelper::forbidden(message: DashboardService::ERR_FORBIDDEN_NOT_ADMIN)`
  block from each of the four methods above, now that the attribute-level
  middleware enforces the same check. If PHPUnit coverage asserts the
  in-body 403 message text, update those tests to assert on the
  framework's `AuthorizedAdminSetting` 403 response instead.
- [ ] Task 7: Update the docblocks above `createGroup()` (lines ~902-905),
  `updateGroup()`, `deleteGroup()`, and `setGroupDefault()` that currently
  describe the `#[NoAdminRequired]` + in-body-check pattern as the
  intentional authorization point — remove the "gate-route-auth /
  gate-semantic-auth" workaround language now that the attribute itself
  carries the authorization.
- [ ] Task 8: Confirm `$this->dashboardService->isAdmin()` still has at
  least one other caller (e.g. `PageController` per the existing grep) so
  it is not left dead after removing these four call sites; if it becomes
  unused, remove the method per `hydra-gate-stub-scan`.

## AdminSettingsController

- [ ] Task 9: In `lib/Controller/AdminSettingsController.php`, import
  `OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting` and the app's
  `Application` class (or `LaunchPadAdmin` settings class, matching the
  pattern in `AdminController.php`).
- [ ] Task 10: Add `#[AuthorizedAdminSetting(Application::APP_ID)]` above
  `listGroups()` (currently line 89).
- [ ] Task 11: Add `#[AuthorizedAdminSetting(Application::APP_ID)]` above
  `updateGroupOrder()` (currently line 162).
- [ ] Task 12: Leave the existing private `assertAdmin()` helper and its
  call sites in place as defense-in-depth (matching the pattern already
  used in `AdminOrgNavigationController.php` and `AdminController.php`,
  where `AuthorizedAdminSetting`-annotated methods also call the inline
  guard) — do not remove it.

## Verification

- [ ] Task 13: Run `hydra-gate-semantic-auth` (or the equivalent
  `run-hydra-gates.sh` invocation) against the diff and confirm all six
  methods now pass with no findings.
- [ ] Task 14: Re-run the existing PHPUnit suite for
  `DashboardApiControllerTest` / `AdminSettingsControllerTest` (or their
  equivalents) and update any assertions that depended on the removed
  in-body 403 message.
- [ ] Task 15: Manually verify via curl/Newman that a non-admin
  authenticated user still receives HTTP 403 on
  `POST /api/dashboards/group/{groupId}`,
  `PUT /api/dashboards/group/{groupId}/{uuid}`,
  `DELETE /api/dashboards/group/{groupId}/{uuid}`,
  `POST /api/dashboards/group/{groupId}/default`,
  `GET /api/admin/settings/groups`, and the group-order update endpoint.
