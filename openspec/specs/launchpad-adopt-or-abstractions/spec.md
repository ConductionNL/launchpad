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
- `dependencies: []` (explicit empty array)
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

@e2e exclude the subject is a build script's exit code and stderr — `scripts/check-manifest.js`, exposed as `npm run check:manifest` and chained into `npm run lint`, which the shared quality workflow runs with `enable-eslint: true`. A browser cannot observe a non-zero exit status, and by construction the scenario describes a tree that never builds and therefore never reaches an instance. The hydra `manifest-validation` gate enforces the same shape independently.

#### Scenario: Manifest declares no install-time OR dependency

- GIVEN the file `src/manifest.json`
- WHEN `manifest.dependencies` is read
- THEN it MUST be an empty array `[]`
- AND no review MAY add `"openregister"`, `"openconnector"`, or any
  other Conduction app ID to the array

@e2e exclude a static assertion about one key in a checked-in JSON file (`src/manifest.json` currently reads `"dependencies": []`), plus a rule addressed to reviewers rather than to the product. Neither has a browser surface: the runtime manifest served to the page is assembled server-side and replaces this file's contents outright, so a rendered page cannot report what the bundled literal said.

### Requirement: LaunchPad MUST NOT declare an install-time dependency on OpenRegister or OpenConnector

This is a structural policy. LaunchPad MUST be installable and runnable
on a Nextcloud instance that has neither OR nor OC enabled.

@e2e exclude two of these read checked-in files (`appinfo/info.xml`, `composer.json`) and have no runtime surface at all. The third needs an instance with OpenRegister ABSENT, and the Playwright fixture is deliberately the opposite: `.github/workflows/code-quality.yml` installs `ConductionNL/openregister` via `additional-apps` precisely so the suite exercises the OR-backed paths, and one CI job cannot be two instances. The OR-less boot path does have a running regression floor — `tests/e2e/ci/boot-and-manifest.spec.ts` exists because every route once returned 500 on an OR-less instance — but it necessarily runs against the OR-present fixture, so it cannot stand behind this scenario's GIVEN.

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

- GIVEN `appinfo/info.xml`
- WHEN reading `<dependencies>` and `<types>` blocks
- THEN no `<dependencies><app>openregister</app>...</app>` or
  `openconnector` MUST be present
- AND no soft-fail "recommended app" string MUST imply that OR is
  required

#### Scenario: Composer dependency check

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

@e2e exclude there is nothing to observe yet: `useOrFeatureDetect` appears nowhere under `src/`, and no LaunchPad widget fetches OpenRegister from the browser at all — the string `apps/openregister/api` does not occur in any `.js` or `.vue` source outside tests. Every OR round-trip LaunchPad makes is server-side, through AppHost. A Playwright test written against these scenarios today could only assert that an absent code path stays absent. The first scenario additionally requires an OR-less instance, which the CI fixture is not. This is a spec-ahead-of-code divergence, reported rather than annotated away.

#### Scenario: OR-backed widget on OR-less install

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

@e2e exclude both scenarios assert the shape of an outbound request a LaunchPad widget makes to OpenRegister from the browser — a `?_lang=` query parameter, an `X-Translation-Target-Language` header. No such request exists to inspect: `apps/openregister/api` does not appear in any `.js` or `.vue` file under `src/` outside tests, so there is no browser-issued OR fetch for a network assertion to attach to. Spec ahead of code, reported rather than annotated away.

#### Scenario: Locale stamping on OR fetch

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

@e2e exclude the requirement is explicitly conditional on an upstream release that has not happened — neither `useTenantContext` nor `X-OpenRegister-Organisation` occurs anywhere under `src/`, and the pinned dependency is `@conduction/nextcloud-vue` 2.2.0-vue3.9. There is no tenant switcher on the instance to drive and no header to inspect. The second scenario asserts a guarded import, i.e. that nothing happens when a symbol is missing, which is a bundler-level property with no rendered consequence.

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

@e2e exclude the first scenario requires an instance with OpenRegister ABSENT, and the Playwright fixture installs it on purpose (`additional-apps` in `.github/workflows/code-quality.yml`). The second describes a MAY, not a MUST, and its trigger does not exist: no `permissions.delegate` field and no call to OpenRegister's `/can?action=read` occurs anywhere in `lib/` or `src/`. The OR-independent half of the sharing model — that a recipient sees a shared dashboard at the granted permission level — is the subject of `tests/e2e/dashboard-sharing.spec.ts`, which is currently withheld from the CI config because the fixture seeds only `e2e-grantee` and the spec needs a `recipient` account; that is recorded against the file in `playwright.config.ts`, not hidden here.

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

@e2e exclude both scenarios assert things a browser cannot reach. The first is a database-row assertion — one row in `oc_launchpad_admin_settings` under a specific key, and the ABSENCE of any row in any OpenRegister table plus the absence of any `/api/objects/...` call; a rendered template list looks identical whichever store produced it. The second is about a regex, `lib/Service/FileService.php::FILENAME_PATTERN`, and its rejection path is an HTTP 400 on import, which is Newman's contract territory here. `FILENAME_PATTERN` is directly unit-tested in `tests/Unit/Service/FileServiceTest.php`, which pins both the accepted set (including `template_v2.json`) and the rejected set (including `../../etc/passwd`) through data providers.

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

@e2e exclude the scenario is a source-organisation rule — where a string literal is declared, and that no second file declares a duplicate for the same logical key. It is satisfied today by the `AdminSettingKey` enum in `lib/Db/AdminSettingKey.php`, which carries 20 cases. Two files agreeing on where a constant lives produces no observable difference in a rendered page; the settings behave identically whether the key is defined once or twice.

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

@e2e exclude the requirement names its own coverage mechanism — "covered by a unit test" — and both scenarios end in "AND the unit test MUST assert true/false". They are assertions about `preg_match()` against a class constant, with no HTTP request and no rendered output between the regex and the verdict. That unit test exists and was opened before this exclusion was written: `tests/Unit/Service/FileServiceTest.php` calls `preg_match(FileService::FILENAME_PATTERN, $filename)` under data providers covering the allowed set (`template_v2.json` among them) and the rejected set (`../../etc/passwd` among them).

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

