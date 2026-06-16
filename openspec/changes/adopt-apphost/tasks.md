# Tasks — Adopt AppHost observability

## 1. Manifest observability block

- [ ] 1.1 Add an `observability` block to `src/manifest.json` reproducing the
      bespoke Health + Metrics output: `health.checks=[{database,critical}]`,
      `statusCodePolicy=adr006`, and `metrics=[dashboards_total (tableCount
      launchpad_dashboards groupBy type, labelDefaults type=personal),
      widgets_total (tableCount launchpad_widget_placements), tiles_total
      (tableCount launchpad_tiles)]`. Implicit `info`/`up` come from the engine.
- [ ] 1.2 Validate the manifest against the `@conduction/nextcloud-vue`
      app-manifest schema (gate-22).

## 2. Thin observability controllers + wiring

- [ ] 2.1 Replace `lib/Controller/HealthController.php` body with
      `class HealthController extends OCA\OpenRegister\AppHost\Controller\GenericHealthController {}`.
- [ ] 2.2 Replace `lib/Controller/MetricsController.php` body with
      `class MetricsController extends OCA\OpenRegister\AppHost\Controller\GenericMetricsController {}`.
- [ ] 2.3 In `Application::register()`, register the two observability controller
      factories (lazy closures, no top-level OR symbol) injecting
      `appName: self::APP_ID` and the engine collaborators
      (`ManifestLoader`, `HealthCheckExecutor`, `MetricsEngine`).
- [ ] 2.4 Leave the `health#index` / `metrics#index` route entries unchanged.

## 3. Delete bespoke observability code

- [ ] 3.1 Delete `lib/Service/MetricsCollector.php` and
      `lib/Service/MetricsQueryService.php` (no remaining callers).
- [ ] 3.2 Delete the bespoke Health/Metrics unit tests that assert the
      hand-rolled internals (superseded by engine + parity tests).

## 4. Parity + verification

- [ ] 4.1 Add a parity test asserting the engine renders the same metric
      names/types/HELP/labels and the ADR-006 health shape.
- [ ] 4.2 Run PHPUnit (`vendor/bin/phpunit`) — green.
- [ ] 4.3 `npm ci && npm run build` — succeeds (Vue 2.7 pins intact).
- [ ] 4.4 Run hydra gates — diff-clean (gate-27 generics verified real; gate-9
      PublicShareController false-positive pre-existing).

## 5. Spec + identity

- [ ] 5.1 Update the `prometheus-metrics` spec to reflect declarative engine
      adoption (public health, engine-owned exposition).
- [ ] 5.2 Confirm `mydash` App Store id, `OCA\LaunchPad` namespace, `launchpad`
      l10n domain, and Vue 2.7 / pinia ~2.1 / @vueuse ~10 pins all unchanged.
