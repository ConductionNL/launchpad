---
status: done
---

# Initial State Contract Specification

## Purpose

The `initial-state-contract` capability formalises the precise set of keys that PHP pushes via Nextcloud's `IInitialState::provideInitialState` for each Vue mount in LaunchPad, and the matching `provide()` calls each entry point emits to expose those keys to the rest of the component tree. Without this contract the keys drift silently — frontend reads a key the backend stopped sending, or vice versa, and the breakage only surfaces at runtime.

A typed PHP `InitialStateBuilder` service centralises the writes; a typed JS `loadInitialState(page)` reader centralises the reads; a versioned `_schemaVersion` key travels with every payload so deploy skew (PHP and JS bundle out of sync) shows up as a console warning instead of a mysterious bug; CI lint guards prevent controllers and components from bypassing the central code paths.

## Requirements

### Requirement: Centralised PHP builder for initial state (REQ-INIT-001)

The system MUST expose `lib/Service/InitialStateBuilder.php` with a constructor accepting an `IInitialState` and a `Page` enum (`Page::WORKSPACE` or `Page::ADMIN`). The builder MUST expose typed setter methods (e.g. `setWidgets(array $widgets): self`, `setLayout(array $layout): self`, `setIsAdmin(bool $isAdmin): self`) and a final `apply(): void` that writes every key to the initial-state service. `apply()` MUST throw `MissingInitialStateException` if any required key was not set for the chosen page. Controllers (`PageController`, `LaunchPadAdmin`) MUST use the builder; direct calls to `IInitialState::provideInitialState` from controllers are forbidden by code review (a grep lint MUST find such calls only inside `InitialStateBuilder`).

#### Scenario: Builder writes all keys

- **WHEN** a workspace controller calls every required setter and then `apply()` and the page renders
- **THEN** the system MUST forward each value to `IInitialState::provideInitialState` so that `loadState('launchpad', <key>)` on the JS side returns the exact values set
- **AND** the JS bundle MUST receive all 10 workspace keys declared in the Data Model

#### Scenario: Missing required key raises before render
@e2e exclude needs a controller that omits setLayout(), which only a code change produces; pinned by tests/Unit/Service/InitialStateBuilderTest.php::testWorkspaceBuilderRejectsMissingRequiredKey (MissingInitialStateException naming "layout" for page "workspace")

- **WHEN** a controller constructs the builder for `Page::WORKSPACE`, omits the required `setLayout()` call, and then invokes `apply()`
- **THEN** the system MUST throw `MissingInitialStateException` with a message naming the missing key `layout`
- **AND** the page MUST NOT render (HTTP 500 surfaces during dev; CI test catches this earlier)

#### Scenario: Direct provideInitialState call rejected
@e2e exclude a source-code lint, not product behaviour; pinned by tests/Unit/Service/InitialStateContractLintTest.php::testNoDirectProvideInitialStateCallsOutsideBuilder (greps lib/, failure message points to InitialStateBuilder) and `composer lint:initial-state`

- **WHEN** a developer adds `$initialState->provideInitialState('foo', 'bar')` directly inside `lib/Controller/PageController.php`
- **THEN** the lint test (grep against `lib/`) MUST fail with a message pointing to `InitialStateBuilder`
- **AND** the change MUST NOT be merged

### Requirement: Versioned key set per page (REQ-INIT-002)

The exact set of initial-state keys per page is part of the contract and MUST match the Data Model documented in this spec. Adding, removing, or renaming a key is a deliberate spec change and MUST be accompanied by (1) an update to this spec's Data Model, (2) a bump of the `INITIAL_STATE_SCHEMA_VERSION` constant (currently `1`), and (3) coordinated updates to both the PHP builder and the JS reader in the same commit. The schema version MUST itself be pushed as initial state under the key `_schemaVersion`. The JS reader MUST log a console warning when the received version differs from the compiled-in version (catches caching and deploy-skew bugs).

The Workspace page (mounted into `#workspace-vue`) MUST expose exactly these keys:

| Key | Type | Default (if missing) |
|---|---|---|
| `widgets` | `Array<{id, title, iconClass, iconUrl, url}>` | `[]` |
| `layout` | `Array<WorkspaceWidget>` | `[]` |
| `primaryGroup` | `string` | `'default'` |
| `primaryGroupName` | `string` | `''` |
| `isAdmin` | `boolean` | `false` |
| `activeDashboardId` | `string` | `''` |
| `dashboardSource` | `'user'\|'group'\|'default'` | `'group'` |
| `groupDashboards` | `Array<{id, name, icon, source?}>` | `[]` |
| `userDashboards` | `Array<{id, name, icon}>` | `[]` |
| `allowUserDashboards` | `boolean` | `false` |

The Admin page (mounted into `#launchpad-admin-settings`) MUST expose exactly these keys:

| Key | Type | Default |
|---|---|---|
| `allGroups` | `Array<{id, displayName}>` | `[]` |
| `configuredGroups` | `Array<string>` | `[]` |
| `widgets` | `Array<{id, title, iconClass, iconUrl, url}>` | `[]` |
| `allowUserDashboards` | `boolean` | `false` |

#### Scenario: Adding a key bumps version
@e2e exclude a code-review rule about a future change, nothing a running instance shows; the only automated part, PHP and JS constants in lockstep, is pinned by src/utils/__tests__/loadInitialState.spec.js 'exports INITIAL_STATE_SCHEMA_VERSION matching the PHP constant (3)'

- **WHEN** a developer adds a new workspace initial-state key `theme` and submits the change for review
- **THEN** the spec Data Model table MUST list `theme` with type and default
- **AND** the `INITIAL_STATE_SCHEMA_VERSION` constant MUST be bumped to `2`
- **AND** the JS reader MUST consume `theme`

#### Scenario: Schema version mismatch warning

- **WHEN** PHP pushes `_schemaVersion: 2` but the loaded JS bundle was compiled against version `1` and the JS reader runs
- **THEN** the reader MUST log a console warning of the form `LaunchPad initial-state schema mismatch: server v2 vs client v1 — refresh recommended`
- **AND** the reader MUST still attempt to load known keys (graceful degradation)

### Requirement: Centralised JS reader for initial state (REQ-INIT-003)

The system MUST expose `src/utils/loadInitialState.js` exporting `loadInitialState(page: 'workspace' | 'admin'): InitialState`. The reader MUST call `loadState('launchpad', key, default)` for every key declared for `page` in the Data Model, MUST return a typed object with default-filled fields (no `undefined` values), and MUST validate the received `_schemaVersion` against the compiled-in `INITIAL_STATE_SCHEMA_VERSION` constant per REQ-INIT-002. Entry points (`src/main.js`, `src/admin.js`) MUST use the reader; direct `loadState('launchpad', ...)` calls outside the reader are forbidden by a JS-side grep lint.

#### Scenario: Reader fills defaults
@e2e exclude the reader's returned object stays inside the bundle and the page exposes none of it, so "no field is undefined" cannot be read from a browser; pinned by src/utils/__tests__/loadInitialState.spec.js 'fills defaults for every workspace key the server omitted'

- **WHEN** PHP pushes only `widgets` (other keys missing for any reason) and `loadInitialState('workspace')` runs
- **THEN** every key declared for the workspace page MUST have a defined value taken from the Data Model defaults
- **AND** no field on the returned object MUST be `undefined`

#### Scenario: Direct loadState rejected
@e2e exclude a source-code lint, not product behaviour; enforced by scripts/lint-initial-state.js (`npm run lint:initial-state`, part of `npm run lint`), whose failure message names loadInitialState; no test exercises the lint's failure path

- **WHEN** a Vue component under `src/` calls `loadState('launchpad', 'something')` directly
- **THEN** the lint test (grep against `src/`) MUST fail
- **AND** the failure message MUST direct the developer to use `loadInitialState`

### Requirement: Provide-down-tree convention (REQ-INIT-004)

After the entry point loads the initial state, it MUST expose every key via the Vue root `provide` option (Vue 2.7) — semantically equivalent to `app.provide(key, value)` in Vue 3 — so descendant components can `inject(key, default)` without re-reading from `loadState`. The provide call MUST use the same key string as the initial-state key — no renaming at the boundary (renaming is itself a spec change and bumps the schema version).

#### Scenario: Provide names match initial-state keys
@e2e exclude provide/inject wiring inside the Vue app, not observable from the page; pinned by src/utils/__tests__/loadInitialState.spec.js 'exposes every key via provide/inject for descendant components (REQ-INIT-004)'

- **WHEN** the workspace entry loads 10 initial-state keys via the reader and exposes them through `provide`
- **THEN** the entry MUST emit exactly 10 provided keys, one per initial-state key, with identical key strings
- **AND** no key renaming MUST occur at this boundary

#### Scenario: Components inject by key name
@e2e exclude provide/inject wiring inside the Vue app, not observable from the page; pinned by src/utils/__tests__/loadInitialState.spec.js 'exposes every key via provide/inject for descendant components (REQ-INIT-004)'

- **WHEN** a deep child component calls `inject('widgets', [])`
- **THEN** the component MUST receive the value the entry point provided
- **AND** the wiring MUST work without additional plumbing

### Requirement: Reactivity boundary (REQ-INIT-005)

`provide`/`inject` distributes static snapshots — values are NOT reactive across writes. Components that need to mutate shared state (for example `layout` after editing) MUST clone the injected value into a local `ref` (or hand off to a Pinia store fed from the injected value). The entry-point provides MUST NEVER be wrapped in a `ref` to "make them reactive" — that would let component code accidentally mutate the boot snapshot and break re-mount semantics.

#### Scenario: Mutation does not leak through inject
@e2e exclude component-internal reactivity, not observable from the page; pinned by src/utils/__tests__/loadInitialState.spec.js 'mutating a cloned ref does not leak to a sibling (REQ-INIT-005)'

- **WHEN** component `A` injects `layout`, clones into `localLayout = ref([...injectedLayout])`, and mutates `localLayout`
- **THEN** any sibling component that injects `layout` MUST still see the original (unmutated) value
- **AND** no console error MUST fire about reactive mutation

#### Scenario: Entry point does not wrap provide in ref
@e2e exclude a static property of src/main.js and src/admin.js checked by code review; no test exists

- **WHEN** a code reviewer inspects `src/main.js` or `src/admin.js`
- **THEN** every provided value MUST be a plain (non-reactive) value
- **AND** no `provide(key, ref(value))` or `provide(key, reactive(value))` MUST appear at the entry-point boundary

### Requirement: The workspace carries the first dashboard load (REQ-INIT-006)

The Workspace page SHOULD carry the optional key `dashboardFirstLoad`: `{visible, active}`, where `visible` is exactly the payload `GET /api/dashboards/visible` answers for the caller and `active` is exactly the payload of `GET /api/dashboard`, or `null` when the caller has no dashboard. Both MUST be built by the same code as those endpoints. The key MUST be left out when the caller may not perform `dashboard.visible` or `dashboard.get-active`. The dashboard store MUST take the key once, on its first load, instead of requesting the two endpoints, and MUST request them on every later load and whenever the key is missing (default `null`).

#### Scenario: The workspace opens without asking for its dashboards again

- **GIVEN** Pieter may read his dashboards
- **WHEN** he opens the workspace
- **THEN** the page carries his visible dashboards and his active dashboard in `dashboardFirstLoad`
- **AND** the store shows them without requesting `/api/dashboards/visible` or `/api/dashboard`

#### Scenario: A later load asks the API

- **GIVEN** the workspace has opened from `dashboardFirstLoad`
- **WHEN** the store loads the dashboards again, for example after a dashboard limit refusal
- **THEN** it requests both endpoints

#### Scenario: A refused caller gets no first load

- **GIVEN** the action matrix refuses Pieter `dashboard.visible`
- **WHEN** he opens the workspace
- **THEN** the page carries no `dashboardFirstLoad`
- **AND** the store requests the endpoints and meets the same refusal

### Requirement: The workspace carries the org navigation (REQ-INIT-007)

The Workspace page SHOULD carry the optional key `orgNavigation`: `{position, tree, language}`, where `position` is exactly what `GET /api/admin/org-navigation/position` answers, and `tree` and `language` are exactly what `GET /api/admin/org-navigation` answers for the caller in the default language. The position MUST be read by the same code as that endpoint. The key MUST be left out when the caller may not perform `admin-org-navigation.get-org-navigation` or `admin-org-navigation.get-position`. The org navigation store MUST take the key once, on the shell's first load, instead of requesting the two endpoints, and MUST request them whenever the key is missing (default `null`).

#### Scenario: The workspace opens without asking for its org navigation

- **GIVEN** Pieter may read the org navigation
- **WHEN** he opens the workspace
- **THEN** the page carries the rail position and his filtered tree in `orgNavigation`
- **AND** the shell places the rail without requesting `/api/admin/org-navigation/position` or `/api/admin/org-navigation`

#### Scenario: A refused caller gets no org navigation

- **GIVEN** the action matrix refuses Pieter `admin-org-navigation.get-position`
- **WHEN** he opens the workspace
- **THEN** the page carries no `orgNavigation`
- **AND** the shell requests the endpoints and meets the same refusal

### Requirement: The workspace carries its view's boot reads (REQ-INIT-008)

The Workspace page SHOULD carry the optional key `workspaceBootReads`: `{tiles, pendingAcknowledgements, defaultDashboard}`, where `tiles` is exactly what `GET /api/tiles` answers for the caller, `pendingAcknowledgements` exactly what `GET /api/acknowledgements/pending` answers, and `defaultDashboard` exactly what `GET /api/dashboards/default` answers. A part MUST be left out when the caller may not perform its action (`tile.index`, `dashboard.get-default-dashboard`) or when reading it fails. The tile store, the pending-acknowledgements read and the default-dashboard read MUST each take their part once, on the first read, instead of requesting the endpoint, and MUST request it on every later read and whenever the part is missing.

#### Scenario: The workspace opens without asking for its tiles, acknowledgements and default dashboard

- **GIVEN** Pieter may read his tiles and his default dashboard
- **WHEN** he opens the workspace
- **THEN** the page carries his tiles, his pending acknowledgements and his default dashboard in `workspaceBootReads`
- **AND** the view shows them without requesting `/api/tiles`, `/api/acknowledgements/pending` or `/api/dashboards/default`

#### Scenario: A refused part is asked for

- **GIVEN** the action matrix refuses Pieter `tile.index`
- **WHEN** he opens the workspace
- **THEN** the page carries no `tiles` part
- **AND** the tile store requests `/api/tiles` and meets the same refusal

### Requirement: A warm workspace load carries the kept manifest (REQ-INIT-009)

`GET /api/manifest` SHOULD keep the manifest it builds for a user, keyed by user and installed app version, for 60 seconds, and MUST answer from the kept manifest within that time. The Workspace page SHOULD carry a kept manifest as the optional key `runtimeManifest` and MUST NOT build one itself. The key MUST be left out when the caller may not perform `manifest.index`. The page MUST take the key instead of requesting `GET /api/manifest`, and MUST request it whenever the key is missing (default `null`).

#### Scenario: A warm reload does not ask for the manifest

- **GIVEN** Pieter opened the workspace less than a minute ago
- **WHEN** he opens it again
- **THEN** the page carries his manifest in `runtimeManifest`
- **AND** the page does not request `/api/manifest`

#### Scenario: A refused caller gets no kept manifest

- **GIVEN** the action matrix refuses Pieter `manifest.index`
- **WHEN** he opens the workspace
- **THEN** the page carries no `runtimeManifest`
- **AND** the page requests `/api/manifest` and meets the same refusal
