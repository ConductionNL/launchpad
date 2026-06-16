# Adopt the OpenRegister AppHost engine (observability)

## Problem

LaunchPad hand-maintains its `/api/health` and `/api/metrics` plumbing in two
bespoke controllers (`lib/Controller/HealthController.php`,
`lib/Controller/MetricsController.php`) plus two helper services
(`lib/Service/MetricsCollector.php`, `lib/Service/MetricsQueryService.php`).
This is ~540 lines of near-identical boilerplate that every Conduction leaf app
re-implements, with two real defects:

1. **Health is login-gated, not public.** The bespoke `HealthController::index()`
   carries only `@NoCSRFRequired`, so a Kubernetes liveness/readiness probe or
   load balancer cannot reach it without a session — defeating the documented
   purpose of REQ-PROM-007.
2. **Metrics format is hand-rolled.** The exposition string is assembled by
   string concatenation per metric, so a single typo silently breaks a scrape,
   and the contract can drift per app (the failure that ADR-006 / ADR-040 exist
   to prevent across the fleet).

OpenRegister now owns the shared **AppHost** observability engine (ADR-040): a
declarative `observability` block in `src/manifest.json` describes the health
checks and metrics, and two engine-owned generic controllers
(`GenericHealthController` public, `GenericMetricsController` admin-only) render
them. OpenRegister already dogfoods this engine; LaunchPad should consume it.

## Proposed Solution

Adopt the AppHost **observability** half, decidesk-style:

- Add an `observability` block to `src/manifest.json` that reproduces the exact
  output of the current Health + Metrics controllers (implicit `launchpad_info`
  + `launchpad_up`, plus `tableCount` descriptors for dashboards-by-type,
  widget placements, and tiles over the allowlisted `launchpad_*` own-tables).
- Re-point the unchanged `/api/health` and `/api/metrics` URLs at thin leaf
  subclasses of the engine generics (`HealthController extends
  GenericHealthController`, `MetricsController extends GenericMetricsController`),
  wired through `Bootstrap`-style factory aliases in `Application::register()`.
- Delete the bespoke `HealthController`/`MetricsController` bodies, the
  `MetricsCollector` and `MetricsQueryService` services, and their unit tests
  once parity is verified.

The boilerplate half of the AppHost (Settings/Preferences/Dashboard/Init/
AdminSettings/SettingsSection/DeepLink) is **intentionally out of scope** for
this change — see `design.md` for the entanglement analysis. LaunchPad's
settings, per-user preferences (DoS-guarded), admin panel (typed
InitialStateBuilder + permission matrix), repair steps (action-matrix seed) and
SPA host are genuinely domain-specific and do not map onto the mechanical
generics; the generic preferences controller does not even exist upstream.

## Scope

In scope: `/api/health` + `/api/metrics` adoption, manifest `observability`
block, deletion of bespoke observability code + tests, parity verification.

Out of scope: boilerplate controller/settings/repair replacement (documented as
entangled-and-kept in `design.md`); the `mydash` App Store identity, the
`OCA\LaunchPad` namespace, the `launchpad` l10n domain, and the Vue 2.7 / pinia
~2.1 / @vueuse ~10 pins are all preserved unchanged.

## Success Criteria

- `GET /api/health` returns the ADR-006 `{status, app, version, checks}` JSON and
  is reachable **without a login session** (public).
- `GET /api/metrics` returns Prometheus text 0.0.4 with byte-for-byte the same
  metric names, types, HELP lines and label keys as today, and stays admin-only.
- Bespoke `HealthController`, `MetricsController`, `MetricsCollector`,
  `MetricsQueryService` (and their unit tests) are deleted; net LOC drops.
- PHPUnit + vitest green; `npm run build` succeeds; hydra gates diff-clean.
- App Store id stays `mydash`; namespace stays `OCA\LaunchPad`.
