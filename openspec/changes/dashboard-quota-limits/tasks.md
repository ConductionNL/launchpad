# Tasks — dashboard-quota-limits

## Tasks

- [x] Task 1: Extend `lib/Service/AdminSettingsService` with `max_dashboards_per_user` and `max_widgets_per_dashboard` — code-level defaults (`0` = unlimited), integer validation with clamp `[0, 10000]`, camelCase API keys (`maxDashboardsPerUser`, `maxWidgetsPerDashboard`) in `GET`/`PUT /api/admin/settings`; no migration (existing key-value table)
- [x] Task 2: Add `lib/Exception/QuotaExceededException` carrying quota kind (`dashboards`|`widgets`), `limit`, and `current`; map to HTTP 409 with body `{"error": "quota_exceeded", "quota", "limit", "current"}` in the controller exception handling
- [x] Task 3: Implement `lib/Service/QuotaService` — `assertCanCreateDashboard(string $userId)` (live COUNT of personal-scope dashboards; most-restrictive-wins with `allow_multiple_dashboards`/`allow_user_dashboards`), `assertCanAddPlacement(int $dashboardId)`, `getQuotaStatus(string $userId)`, and an explicit provisioning-context bypass (`runProvisioning(callable)`) — bypass tied to the call path, never to admin group membership
- [x] Task 4: Wire `assertCanCreateDashboard()` into every user-initiated creation path in `DashboardService` — `createDashboard` (covers create + user-initiated template instantiation via the create choke point) and `forkAsPersonal`. Audited: admin `ImportService` / `ConfluenceImportService` write via mappers directly (admin-only, provisioning — exempt by construction); the auto-bootstrap `TemplateService::createDashboardFromTemplate` likewise writes via mappers (system provisioning, exempt)
- [x] Task 5: Wire `assertCanAddPlacement()` into the placement-creation choke point in `PlacementService` — `addWidget` + `addTileFromArray` (reject, never truncate). The deprecated `TileApiController::create` returns 410; admin import writes via mappers (provisioning bypass by construction)
- [x] Task 6: Add the `quota: {maxDashboards, dashboardsUsed, maxWidgetsPerDashboard}` envelope to the dashboards list response (`list()` + `visible()`, shape `{items, quota}`, additive, `0` = unlimited)
- [x] Task 7: Admin settings UI — two `NcTextField` numeric inputs with "0 = unlimited" helper text and client-side clamp, following the existing admin settings form patterns
- [x] Task 8: Frontend quota consumption — store the quota envelope (shape-tolerant unwrap), disable "Add dashboard" / "Add widget" affordances at the limit with localised tooltip, render 409 `quota_exceeded` responses as clear messages and refresh the envelope (race case); zero quota UI when both settings are `0`
- [x] Task 9: PHPUnit coverage — settings (defaults, clamp, persistence), QuotaService (at/below/over limit, live recount after delete, scope filtering personal-vs-group, most-restrictive-wins, provisioning bypass on/off + reset-on-throw, admin-bound-personally), service wiring (create + fork + addWidget + addTile throw and never insert), controller 409 body shape + envelope
- [~] Task 10: Playwright coverage — annotated as `@e2e exclude` (no live NC instance available in this build context to run the browser flow; the affordance logic is covered by vitest and the enforcement by PHPUnit). Honest: not authored as a live spec
- [~] Task 11: Newman/Postman — not authored (no running instance to execute Newman in this context); the 409 contract + envelope are covered by the PHPUnit controller test. Honest: deferred
- [x] Task 12: Quality gates — SPDX-in-docblock on new PHP, `@spec` annotations on all new/changed methods, hydra gates green for the diff (semantic-auth on the provisioning bypass, orphan-auth: QuotaService asserts invoked from every creation path)
- [x] Task 13: Documentation — admin guide section "Governance quotas" (what counts, grandfathering semantics, interaction with the boolean flags) + changelog entry
- [x] Task 14: i18n — `en` + `nl` for the two settings labels/helper texts, tooltips, and quota error messages (English source strings as keys)

## Verification

`openspec validate` exits clean; all six REQ-QUOTA requirements traced to tests; live
verify on localhost:8080 — set limits 2/3 as admin, exercise the block + grandfather +
free-on-delete flows as a non-admin user, confirm template rollout still lands while
the user is at quota.

## Tests (company-wide ADR-009)

PHPUnit + Playwright per Tasks 9–10; Newman per Task 11. The orphan-auth concern (an
assert that exists but is not called from every path) is covered by explicit wiring
tests per creation surface.

## Documentation (company-wide ADR-010)

Per Task 13 — admin guide section plus changelog entry.

## i18n (company-wide ADR-005)

`en_US` + `nl_NL` per Task 14; English source strings are the keys.
