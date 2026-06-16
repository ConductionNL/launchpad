# Tasks: LaunchPad Adopts OpenRegister AppHost

## 0. Baseline

- [ ] 0.1 Capture baseline on a seeded dev instance (dashboards with NULL, `''`, `'personal'`, and `'template'` types; widget placements; tiles): `curl /apps/<appid>/api/health` JSON + `/api/metrics` Prometheus text; store both as fixtures for the parity diff
- [ ] 0.2 Verify the actual metric prefix in the baseline is `launchpad_` (NOT `mydash_`) and record which runtime app id served the endpoints — `info.xml` id is `mydash`, `Application::APP_ID` is `launchpad`; the adoption must preserve the `launchpad_` prefix exactly

## 1. Manifest descriptors

- [ ] 1.1 Add the `observability` block to `src/manifest.json`:

  ```json
  "observability": {
    "health": {
      "checks": [
        { "id": "database", "type": "database" },
        { "id": "openregister", "type": "orAvailable", "severity": "degraded" }
      ]
    },
    "metrics": [
      { "name": "dashboards_total", "type": "gauge", "help": "Total dashboards by type",
        "source": { "kind": "tableCount", "table": "launchpad_dashboards",
                    "groupBy": ["type"], "labelDefaults": { "type": "personal" } } },
      { "name": "widgets_total", "type": "gauge", "help": "Total number of widget placements",
        "source": { "kind": "tableCount", "table": "launchpad_widget_placements" } },
      { "name": "tiles_total", "type": "gauge", "help": "Total number of tiles",
        "source": { "kind": "tableCount", "table": "launchpad_tiles" } }
    ]
  }
  ```

  (`launchpad_info` / `launchpad_up` are implicit — never declared.)
- [ ] 1.2 Validate via ManifestService diagnostics (no errors) and gate-22 manifest validation

## 2. Wiring and deletion

- [ ] 2.1 Add `Bootstrap::register($context, self::APP_ID)` to `lib/AppInfo/Application.php::register()` — keep ALL existing domain wiring (Notifier, search provider, cascade listeners, jobs, XXE boot guard) intact
- [ ] 2.2 Delete `lib/Controller/HealthController.php` and `lib/Controller/MetricsController.php`; confirm `health#index` / `metrics#index` route names resolve through the AppHost aliases (no `appinfo/routes.php` edits — URLs/names/ordering unchanged, incl. the `page#deepLink` catch-all staying last)
- [ ] 2.3 Delete `lib/Controller/PreferencesController.php`; alias to `GenericPreferencesController`; verify `preferences#getPreference`/`setPreference` keep the same URL, response shape, and key-sanitisation behaviour for keys already written by deployed instances
- [ ] 2.4 Delete the dead `lib/Service/MetricsCollector.php` + `lib/Service/MetricsQueryService.php` (zero callers — verify with a final reference sweep)
- [ ] 2.5 Replace `lib/Service/ActionAuthService.php` with the `GenericActionAuthService` alias; shrink `lib/Repair/InitializeActions.php` to a one-line stub `extends GenericInitializeActions` (both `<repair-steps>` entries in info.xml must keep resolving; `lib/actions.seed.json` semantics unchanged)
- [ ] 2.6 Shrink `lib/Settings/LaunchPadAdminSection.php` to a one-line stub `extends GenericSettingsSection`; leave `lib/Settings/LaunchPadAdmin.php` untouched (domain `getForm()` initial state)
- [ ] 2.7 Sweep remaining references (tests, `@spec` tags, docs) to the deleted classes

## 3. Parity verification

- [ ] 3.1 Diff `/api/metrics` output vs the 0.1 baseline: identical metric names (incl. the pinned `launchpad_` prefix), types, and label sets for `launchpad_info`, `launchpad_up`, `launchpad_dashboards_total{type}`, `launchpad_widgets_total`, `launchpad_tiles_total`
- [ ] 3.2 Verify the NULL→`personal` `labelDefaults` mapping on `launchpad_dashboards_total` against the seeded NULL-type rows; also verify empty-string `type` rows map to `personal` (today's controller treats `''` like NULL) — if the engine only remaps NULL, fix the engine or record the delta with a follow-up
- [ ] 3.3 Document intentional deltas: (a) `template`/`personal` series no longer zero-filled when absent (Prometheus absent≅0), (b) health response gains `app`/`version` fields, (c) metrics endpoint now admin-only per ADR-006 — note for scrape-config ops
- [ ] 3.4 Diff `/api/health` vs baseline: HTTP 200 with `checks.database = "ok"` on a healthy instance; OR-disabled simulation yields `status: degraded` (not 503) via the `orAvailable` severity-degraded check
- [ ] 3.5 OR AppHost Newman contract collection green against LaunchPad's endpoints
- [ ] 3.6 Existing LaunchPad Playwright e2e + PHPUnit suites green (preferences-backed UI flows, e.g. CnSupportDialog, still work)

## 4. Docs

- [ ] 4.1 Update LaunchPad observability/admin docs: declarative descriptors, the pinned `launchpad_` prefix vs the `mydash` store id, the documented deltas from 3.3

## 5. Quality gates

- [ ] 5.1 `composer check:strict` green; fix any pre-existing issues encountered in touched files
- [ ] 5.2 All 18 hydra gates green (incl. gate-16 `@spec` coverage on touched methods) + gate-22 manifest validation green
