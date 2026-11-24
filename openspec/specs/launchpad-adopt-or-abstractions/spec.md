---
status: done
---

# launchpad-adopt-or-abstractions Specification

## Purpose
Keeps LaunchPad installable and runnable without OpenRegister or OpenConnector while letting its widgets consume OR data when present. It mandates an architectural manifest, forbids install-time OR/OC dependencies, requires runtime feature-detection with documented empty states, locale and tenant-context stamping on OR fetches, a local-first dashboard permission model, and several code-hygiene rules (typed admin-setting keys, a named filename-pattern constant, and documented column-type constants).
## Requirements
### Requirement: LaunchPad MUST ship an architectural manifest at `src/manifest.json`

LaunchPad MUST add `src/manifest.json` conforming to the JSON Schema
published by `@conduction/nextcloud-vue` at
`src/schemas/app-manifest.schema.json`. The manifest MUST be loaded
via `useAppManifest('launchpad', bundledManifest)` in `src/main.js`.

The manifest MUST set:
- `$schema` to the published nc-vue schema URL (for editor
  validation)
- `version` to a semver string, bumped on shape change
- `dependencies` listing OpenRegister only as optional
  (`[{"id": "openregister", "required": false, "name": "OpenRegister"}]`)
- a `menu` array with at least the Dashboards entry
- a `pages` array including the per-dashboard page
  (`type: "dashboard"`)

#### Scenario: Manifest loads on app boot

- GIVEN LaunchPad is installed and enabled
- AND a user navigates to `/index.php/apps/launchpad`
- WHEN `src/main.js` runs
- THEN `useAppManifest('launchpad', bundledManifest)` MUST be called
  before the vue-router instance mounts
- AND the bundled manifest MUST be the immediate value of the
  reactive `manifest` ref returned by the composable
- AND the loader MUST attempt an async fetch of
  `/index.php/apps/launchpad/api/manifest`
- AND on any non-200 response the loader MUST silently fall back to
  the bundled value with a `console.warn` log entry

#### Scenario: Manifest validation fails build

- GIVEN a developer edits `src/manifest.json` and introduces an
  unknown `type` value (e.g. `type: "kanban"`)
- WHEN the developer runs `npm run check:manifest`
- THEN the script MUST exit non-zero
- AND the error MUST identify the offending JSON path
- AND CI MUST fail on the same script

@e2e exclude the subject is a build script's exit code and stderr — `scripts/check-manifest.js`, exposed as `npm run check:manifest` and chained into `npm run lint`, which the shared quality workflow runs with `enable-eslint: true`. A browser cannot observe a non-zero exit status, and by construction the scenario describes a tree that never builds and therefore never reaches an instance. The hydra `manifest-validation` gate enforces the same shape independently. Re-checked 2026-11-19: `scripts/check-manifest.js` validates only `$schema`, a semver `version`, that `dependencies`, `menu` and `pages` are arrays, and that `dependencies` holds no bare `"openregister"`/`"openconnector"` string; it does not check a page's `type` against an allowed set and prints no JSON path, so the THEN describes behaviour the script does not have. No unit test pins the script.

#### Scenario: Manifest declares no install-time OR dependency

- GIVEN the file `src/manifest.json`
- WHEN `manifest.dependencies` is read
- THEN it MUST be an array whose only entry is OpenRegister marked
  optional (`required: false`)
- AND no review MAY add a required entry, a bare app-id string, or
  `"openconnector"` or any other Conduction app ID to the array

### Requirement: LaunchPad MUST NOT declare an install-time dependency on OpenRegister or OpenConnector

This is a structural policy. LaunchPad MUST be installable and runnable
on a Nextcloud instance that has neither OR nor OC enabled.

#### Scenario: Install on OR-less Nextcloud

- GIVEN a Nextcloud install with OR not present and OC not present
- WHEN an admin enables LaunchPad via the apps page
- THEN the enable action MUST succeed
- AND the LaunchPad menu entry MUST appear
- AND opening LaunchPad MUST render the dashboards index without error
- AND any pre-installed dashboards that reference OR data MUST
  render with a documented empty state (per the runtime consumption
  requirement below)

#### Scenario: appinfo.xml dependency check

@e2e exclude reads a checked-in file (`appinfo/info.xml`) and has no runtime surface at all; nothing on a running instance can show what the file declares. The runtime consequence, LaunchPad enabling and running without OpenRegister or OpenConnector, is cited as `install-on-or-less-nextcloud` in `tests/e2e/or-absent.spec.ts`.

- GIVEN `appinfo/info.xml`
- WHEN reading `<dependencies>` and `<types>` blocks
- THEN no `<dependencies><app>openregister</app>...</app>` or
  `openconnector` MUST be present
- AND no soft-fail "recommended app" string MUST imply that OR is
  required

#### Scenario: Composer dependency check

@e2e exclude reads a checked-in file (`composer.json`) and the PHP import graph, neither of which has a runtime surface. The runtime consequence, no LaunchPad class failing for want of an `OCA\OpenRegister` class, is asserted on an OR-less instance by `install-on-or-less-nextcloud` and by `runtime-or-consumption::fresh-install-without-or-boots-normally` (its log check) in `tests/e2e/or-absent.spec.ts`.

- GIVEN `composer.json`
- WHEN reading `require` and `require-dev`
- THEN no `conduction/openregister*` package MUST be required
- AND no transitive PHP class import path MUST resolve through OR's
  namespace (`OCA\OpenRegister\...`) outside of optional runtime
  service-locator lookups

### Requirement: LaunchPad widgets that surface OR data MUST feature-detect OR at runtime

Any LaunchPad widget that reads or writes OR objects MUST guard the
call with `useOrFeatureDetect()` and render a documented empty
state when OR is absent or returns 5xx.

#### Scenario: OR-backed widget on OR-less install

@e2e exclude the THEN is unbuilt: `useOrFeatureDetect` occurs nowhere under `src/` (only in `docs/widgets/or-data.md` and `docs/architecture.md`; `git log --all -S useOrFeatureDetect -- src lib` finds no commit), so there is no `enabled.value` to read and nothing stops the request to OpenRegister; no unit test pins it either. The GIVEN itself can be staged: `tests/e2e/or-absent.spec.ts` disables OpenRegister and cites the widget's empty state as `runtime-or-consumption::or-absent-empty-state-shown` and in `install-on-or-less-nextcloud`. The OR-present half of this requirement (a 503) is cited in `tests/e2e/launchpad-adopt-or-abstractions.spec.ts`.

- GIVEN OR is not installed on the Nextcloud instance
- AND an operator has placed an OR-backed widget on a dashboard
- WHEN the dashboard renders
- THEN `useOrFeatureDetect()` MUST return `enabled.value === false`
- AND the widget MUST render the empty state defined in
  `docs/widgets/or-data.md`
- AND no network call to `/index.php/apps/openregister/api/...`
  MUST be issued
- AND no console error MUST be raised

#### Scenario: OR returns 5xx

- GIVEN OR is installed and enabled
- AND a transient OR error causes
  `GET /index.php/apps/openregister/api/objects/{r}/{s}` to return
  503
- WHEN an OR-backed widget polls
- THEN the widget MUST render the empty state with an
  `aria-live="polite"` "Temporarily unavailable" message
- AND MUST retry on the next poll interval (default 60 s)
- AND MUST NOT crash the dashboard

### Requirement: LaunchPad widgets MUST pass `?_lang=` when fetching translatable OR data

Widgets MUST stamp `?_lang={BCP47}` on every OR fetch: when a widget
fetches an OR object that exposes translatable properties, the request
URL MUST include the `?_lang={BCP47}` query parameter set to the user's
current Nextcloud locale.

This is a downstream consumer of
`openregister/openspec/changes/i18n-api-language-negotiation/`.

#### Scenario: Locale stamping on OR fetch

@e2e exclude the WHEN is a widget fetching ONE OpenRegister object by uuid (`/objects/cases/melding/abc`), and no widget LaunchPad can place does that from the browser: the browser-side OR reads in `src/` and in nc-vue's `CnObjectListWidget` are collection reads (`/objects/{register}/{schema}?_limit=...`), and the single-object paths they build are only PATCH/DELETE writes. The `?_lang=` contract on the collection read a widget does issue is cited as `runtime-or-consumption::widget-passes-current-user-locale` and `::widget-falls-back-to-en-when-lang-attribute-is-absent`. No unit test pins `_lang` stamping; no source file under `src/` sends it.

- GIVEN the user's Nextcloud locale is `en_GB`
- AND a widget fetches OR object `register=cases, schema=melding,
  uuid=abc`
- WHEN the request is built
- THEN the URL MUST be
  `/index.php/apps/openregister/api/objects/cases/melding/abc?_lang=en`
- AND no `Accept-Language` header MUST be relied upon as the sole
  signal

#### Scenario: Translation target on OR write

- GIVEN the user is editing an OR object's `title` property in
  English from a LaunchPad dashboard
- WHEN the widget PATCHes the object
- THEN the request MUST include the header
  `X-Translation-Target-Language: en`
- AND the body MUST send the English string under the `title` key
  without language wrapping

### Requirement: LaunchPad widgets MUST consume `useTenantContext()` from nc-vue when surfacing tenant-scoped OR data

LaunchPad widgets MUST adopt `useTenantContext()` once it is available:
once the `multi-tenancy-context` change in
`nextcloud-vue/openspec/changes/` is released in a versioned package,
LaunchPad widgets MUST use the composable to refetch on tenant switch
and to stamp `X-OpenRegister-Organisation` on writes.

@e2e exclude no tenant switcher exists for a test to drive, and nothing would react to it. Re-checked 2026-11-19: the upstream release has now happened — the installed `@conduction/nextcloud-vue` is 2.180.0 and its `src/index.js` exports `useTenantContext` — but no LaunchPad source imports it (`useTenantContext`, `provideTenantContext` and `X-OpenRegister-Organisation` occur nowhere under `src/` or `lib/`; `git log --all -S useTenantContext -- src` finds no commit). `CnAppRoot` mounts a tenant provider and a `CnTenantBadge`, which is display-only, and nc-vue's `CnObjectListWidget` does not subscribe to a switch. OpenRegister itself reads no tenant header: it scopes by the user's stored `active_organisation`. The second scenario's GIVEN (nc-vue NOT exporting the composable) can no longer be arranged against the pinned package, and its THEN is a guarded import, a bundler property with no rendered consequence. No unit test pins either scenario.

#### Scenario: Tenant switch refetches widget data

- GIVEN a dashboard with two OR-backed widgets and the user is
  active in tenant A
- WHEN the user switches to tenant B via the nc-vue tenant switcher
- THEN both widgets MUST detect the change via
  `useTenantContext().activeOrganisationUuid`
- AND clear their cached collections
- AND refetch their data using B's session

#### Scenario: Pre-release fallback

- GIVEN nc-vue does not yet export `useTenantContext`
- WHEN LaunchPad imports the composable
- THEN the import MUST be guarded with try/catch (or feature
  detection) so that absence does not crash the app
- AND the widget MUST behave as if a single-tenant install (no
  refetch on switch, no header stamping)

### Requirement: Dashboard sharing MUST keep the local permission model

LaunchPad dashboard sharing MUST continue to use the
`oc_launchpad_dashboards.permissions` column with values
`view_only`, `add_only`, `full`. Sharing MUST work without OR.

OR per-object RBAC delegation MAY be wired as an OPTIONAL runtime
delegation when a dashboard's `permissions.delegate` field
references an OR object UUID and OR is enabled.

#### Scenario: Sharing on OR-less install

- GIVEN OR is not installed
- AND a dashboard owner shares with permission level `add_only` to
  group `kcc-team`
- WHEN a `kcc-team` member opens the dashboard
- THEN they MUST see the dashboard
- AND they MUST be able to add new widgets
- AND they MUST NOT be able to delete the dashboard or change its
  permissions

#### Scenario: Optional delegation when OR enabled

@e2e exclude a MAY whose trigger was never built: no `permissions.delegate` field and no call to OpenRegister's `/can?action=read` occurs in `lib/` or `src/` (`permissions.delegate` appears only in `docs/architecture.md`). No test pins it.

- GIVEN OR is enabled
- AND a dashboard's `permissions.delegate.objectUuid = "abc-123"`
  (referencing an OR object)
- WHEN a member of group `kcc-team` opens the dashboard
- THEN LaunchPad MUST call
  `GET /index.php/apps/openregister/api/objects/abc-123/can?action=read`
- AND MUST AND the OR result with the local `view_only` check
- AND MUST render the dashboard only if both pass
- AND if the OR call fails, MUST fall back to the local check
  silently

### Requirement: Admin templates MUST persist in `oc_launchpad_admin_settings`

Admin templates MUST persist locally: admin dashboard templates (the
named, exportable dashboard configurations admins distribute to groups)
MUST persist in the local `oc_launchpad_admin_settings` table.

Admin templates MUST NOT be persisted in OR.

@e2e exclude neither scenario describes what the product does, and neither outcome is readable over HTTP. Admin templates do not persist in `oc_launchpad_admin_settings` at all: they are `oc_launchpad_dashboards` rows of type admin-template (`lib/Service/AdminTemplateService.php` inserts through `dashboardMapper`; `DashboardMapper::findAdminTemplates()` reads them back), and no API exposes raw table rows, so the first scenario's row-and-key assertion has no read to stand on and no test pins it. The second was never built as written: the only export is `POST /api/admin/export`, which streams a ZIP (`ExportService::streamZipResponse`), not a JSON file, and `AdminController::import` never looks at the uploaded filename, so no path rejects a name with 400. `FILENAME_PATTERN` guards `FileService::createFile` only, and is unit-tested in `tests/Unit/Service/FileServiceTest.php::testFilenamePatternAllowsValidNames` (with `template_v2.json`) and `::testFilenamePatternRejectsInvalidNames`.

#### Scenario: Template persistence

- GIVEN an admin creates a template `Service Desk default v2`
- WHEN the admin clicks save
- THEN the template MUST insert one row into
  `oc_launchpad_admin_settings` with `key = "template:service-desk-default-v2"`
  and a JSON blob in `value`
- AND no row MUST be inserted into any OR table
- AND no call to OR's `/api/objects/...` MUST be issued

#### Scenario: Template export filename

- GIVEN an admin exports the template `Service Desk default v2`
- WHEN the file is generated
- THEN the filename MUST match
  `lib/Service/FileService.php::FILENAME_PATTERN`
- AND the file MUST be a JSON document with the template body
- AND on import, a filename that does not match the pattern MUST
  be rejected with a 400 response

### Requirement: ColumnTypeRegistry constants document their non-OR scope

`lib/Db/ColumnTypeRegistry.php` MUST carry a class-level docblock
explaining that its `TYPE_INTEGER`, `TYPE_BOOLEAN`, `TYPE_STRING`
constants model UI rendering affordances and are deliberately
independent of JSON-schema `type` semantics.

@e2e exclude the subject is the text of a PHP docblock, read by a future static audit — there is no runtime behaviour and therefore nothing for a browser to observe. Note also that the file this requirement names does not exist: `lib/Db/ColumnTypeRegistry.php` is absent and the identifier `ColumnTypeRegistry` occurs nowhere in `lib/` or `src/`. A stale requirement is reported, not annotated into looking satisfied.

#### Scenario: Future audit silenced

- GIVEN a future OR-abstraction audit
- WHEN it scans `lib/Db/ColumnTypeRegistry.php` for "hardcoded
  constants"
- THEN the docblock MUST satisfy the audit's "documented rationale"
  exemption
- AND the audit MUST NOT re-flag the constants

### Requirement: AdminSetting keys MUST live under a single typed list

AdminSetting keys MUST be unified: the eight `KEY_*` admin-config keys
in `lib/Db/AdminSetting.php` MUST be collected into a single
`AdminSettingKey` const-list (or PHP 8.1 `enum`). Existing constants MAY
be kept as aliases for BC.

@e2e exclude the scenario is a source-organisation rule — where a string literal is declared, and that no second file declares a duplicate for the same logical key. It is satisfied today by the `AdminSettingKey` enum in `lib/Db/AdminSettingKey.php`, which carries 33 cases (re-counted 2026-11-19). Two files agreeing on where a constant lives produces no observable difference in a rendered page; the settings behave identically whether the key is defined once or twice.

#### Scenario: Key list is single-source-of-truth

- GIVEN the `AdminSettingKey` const-list
- WHEN a new key is needed
- THEN it MUST be added to the list and to the docblock
- AND no other file in `lib/` MUST define a duplicate string literal
  for the same logical key

### Requirement: FILENAME_PATTERN regex MUST be a named class constant with a unit test

`lib/Service/FileService.php::FILENAME_PATTERN` MUST be:

- a named class constant (not an inline regex literal at line 62)
- documented in the docblock with the accepted/rejected sets
- covered by a unit test pinning at least:
  - allowed: `dashboard-export.json`, `template_v2.json`,
    `template-with-dashes.json`
  - rejected: `../etc/passwd`, `name.with.two.dots.json`,
    paths containing slashes (`a/b.json`), paths starting with `.`

@e2e exclude the requirement names its own coverage mechanism — "covered by a unit test" — and both scenarios end in "AND the unit test MUST assert true/false". They are assertions about `preg_match()` against a class constant, with no HTTP request and no rendered output between the regex and the verdict. Re-checked 2026-11-19 in `tests/Unit/Service/FileServiceTest.php`: `testFilenamePatternAllowsValidNames` calls `preg_match(FileService::FILENAME_PATTERN, ...)` with `template_v2.json` in its data provider, which pins the second scenario. The rejected-set provider does NOT contain a traversal path; `../../etc/passwd` is pinned one layer up by `testPathTraversalIsRejected` (through `createFile`, where the `..` substring check fires before the regex) and `testForwardSlashIsRejected`. No test calls the regex on `../etc/passwd` directly.

#### Scenario: Path traversal rejected

- GIVEN `FILENAME_PATTERN`
- WHEN the regex matches `../etc/passwd`
- THEN the result MUST be no match
- AND the unit test MUST assert `false`

#### Scenario: Allowed filename

- GIVEN `FILENAME_PATTERN`
- WHEN the regex matches `template_v2.json`
- THEN the result MUST be a match
- AND the unit test MUST assert `true`

