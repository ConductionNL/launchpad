# Tasks — dashboard-quota-limits

## Tasks

- [ ] Task 1: Extend `lib/Service/AdminSettingsService` with `max_dashboards_per_user` and `max_widgets_per_dashboard` — code-level defaults (`0` = unlimited), integer validation with clamp `[0, 10000]`, camelCase API keys (`maxDashboardsPerUser`, `maxWidgetsPerDashboard`) in `GET`/`PUT /api/admin/settings`; no migration (existing key-value table)
- [ ] Task 2: Add `lib/Exception/QuotaExceededException` carrying quota kind (`dashboards`|`widgets`), `limit`, and `current`; map to HTTP 409 with body `{"error": "quota_exceeded", "quota", "limit", "current"}` in the controller exception handling
- [ ] Task 3: Implement `lib/Service/QuotaService` — `assertCanCreateDashboard(string $userId)` (live COUNT of personal-scope dashboards; most-restrictive-wins with `allow_multiple_dashboards`/`allow_user_dashboards`), `assertCanAddPlacement(string $dashboardUuid)`, `getQuotaStatus(string $userId)`, and an explicit provisioning-context bypass (`runProvisioning(callable)` or equivalent flag) — bypass tied to the call path, never to admin group membership
- [ ] Task 4: Wire `assertCanCreateDashboard()` into every user-initiated creation path in `DashboardService` — create, duplicate, fork, import, user-initiated template instantiation — and audit for any creation surface that bypasses the service choke point (REST, CLI commands, import); template-rollout/provisioning paths use the bypass
- [ ] Task 5: Wire `assertCanAddPlacement()` into every placement-creation path in `PlacementService` — `addWidget`, `addTileFromArray`, duplicate, import (reject, never truncate) — with the compulsory-widget push path using the provisioning bypass
- [ ] Task 6: Add the `quota: {maxDashboards, dashboardsUsed, maxWidgetsPerDashboard}` envelope to the dashboards list response (additive, `0` = unlimited)
- [ ] Task 7: Admin settings UI — two numeric inputs with "0 = unlimited" helper text and validation feedback, following the existing admin settings form patterns
- [ ] Task 8: Frontend quota consumption — store the quota envelope, disable "New dashboard" / "Add widget" affordances at the limit with localised tooltip, render 409 `quota_exceeded` responses as clear messages and refresh the envelope (race case); zero quota UI when both settings are `0`
- [ ] Task 9: PHPUnit coverage — settings (defaults, clamp, persistence, admin guard), QuotaService (at/below/over limit, live recount after delete, scope filtering personal-vs-group, most-restrictive-wins, provisioning bypass on/off, admin-bound-personally), service wiring (all creation paths throw, import rejects not truncates), controller 409 body shape
- [ ] Task 10: Playwright coverage — admin sets a limit of 2; user creates 2 dashboards, sees the disabled "New dashboard" affordance + tooltip; deletes one and creates again; widget-limit equivalent on a single dashboard
- [ ] Task 11: Newman/Postman — quota settings round-trip on `/api/admin/settings`; 409 contract on dashboard and placement creation at the limit; quota envelope on the list response
- [ ] Task 12: Quality gates — `composer check:strict` green, SPDX-in-docblock on new PHP, `@spec` annotations on all new/changed methods, hydra gates (semantic-auth on the provisioning bypass, orphan-auth: QuotaService asserts MUST be invoked from every creation path)
- [ ] Task 13: Documentation — admin guide section "Governance quotas" (what counts, grandfathering semantics, interaction with the boolean flags) + changelog entry
- [ ] Task 14: i18n — `en` + `nl` for the two settings labels/helper texts, tooltips, and quota error messages (English source strings as keys)

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
