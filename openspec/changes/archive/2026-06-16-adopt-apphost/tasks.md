# Tasks — Adopt AppHost observability

## 1. Manifest observability block

- [x] 1.1 Add an `observability` block to `src/manifest.json` reproducing the
      bespoke Health + Metrics output: `health.checks=[{database,critical}]`,
      `statusCodePolicy=adr006`, and `metrics=[dashboards_total (tableCount
      launchpad_dashboards groupBy type, labelDefaults type=personal),
      widgets_total (tableCount launchpad_widget_placements), tiles_total
      (tableCount launchpad_tiles)]`. Implicit `info`/`up` come from the engine.
- [x] 1.2 Validate the manifest against the `@conduction/nextcloud-vue`
      app-manifest schema (gate-22).

## 2. Observability controller wiring

- [x] 2.1 Reduce `lib/Controller/HealthController.php` to
      `class HealthController extends GenericHealthController` re-declaring only
      `index()` with `#[PublicPage]` + `#[NoCSRFRequired]` → `parent::index()`.
- [x] 2.2 Reduce `lib/Controller/MetricsController.php` to
      `class MetricsController extends GenericMetricsController` re-declaring only
      `index()` with `#[NoCSRFRequired]` (no `#[NoAdminRequired]`) →
      `parent::index()`.
- [x] 2.3 In `Application::register()`, register the two controllers as lazy
      factory closures injecting `appName: self::APP_ID` and the engine
      collaborators (`ManifestLoader`, `HealthCheckExecutor`, `MetricsEngine`)
      resolved from the container — kept lazy so NC bootstrap stays fatal-free
      when OpenRegister is absent.
- [x] 2.4 Leave the `health#index` / `metrics#index` route entries unchanged.

## 3. Delete bespoke observability code

- [x] 3.1 Delete `lib/Service/MetricsCollector.php` and
      `lib/Service/MetricsQueryService.php` (no remaining callers).
- [x] 3.2 Remove any bespoke Health/Metrics unit tests asserting the hand-rolled
      internals. (None existed — the bespoke controllers shipped untested.)

## 4. Parity + verification

- [x] 4.1 Verify parity statically: the engine emits the same five metric
      families (`launchpad_info`, `launchpad_up`, `launchpad_dashboards_total`,
      `launchpad_widgets_total`, `launchpad_tiles_total`) with identical types,
      HELP lines and labels as the bespoke output, plus the ADR-006 health shape.
      (A runtime engine unit test cannot live in the leaf — the engine ships in
      OpenRegister and is not on the leaf test classpath; it is covered by OR's
      own AppHost test suite.)
- [x] 4.2 Run PHPUnit (`vendor/bin/phpunit`) — green.
- [x] 4.3 `npm ci && npm run build` — succeeds (Vue 2.7 pins intact).
- [x] 4.4 Run hydra gates — diff-clean (gate-27 generics verified real; gate-9
      PublicShareController false-positive pre-existing).

## 5. Spec + identity

- [x] 5.1 Update the `prometheus-metrics` spec to reflect declarative engine
      adoption (public health, engine-owned exposition).
- [x] 5.2 Confirm `mydash` App Store id, `OCA\LaunchPad` namespace, `launchpad`
      l10n domain, and Vue 2.7 / pinia ~2.1 / @vueuse ~10 pins all unchanged.
