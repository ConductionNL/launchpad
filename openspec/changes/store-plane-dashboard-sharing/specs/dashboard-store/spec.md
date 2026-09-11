---
capability: dashboard-store
status: draft
---

# Dashboard Store — New capability from change `store-plane-dashboard-sharing`

## Context

A registry is another OpenRegister instance publishing dashboard templates as
objects of a `dashboard-template` schema. LaunchPad reaches it through
OpenRegister's AppHost store plane, which owns discovery: the SSRF guard, the
redirect refusal, the Bearer-only token transport, the outcome vocabulary and card
normalisation. LaunchPad owns install, because a dashboard lives in LaunchPad's
own tables and no engine install op can write one.

Browser coverage lives in `tests/e2e/dashboard-store.spec.ts`: the store page's
not-configured state, the admin form round-trip, the token redaction, and the
admin gate probed with a non-admin account. Scenarios with no browser-visible
effect (degraded discovery with OpenRegister absent, outcome pass-through,
ZIP materialisation, additive install, temp-file cleanup) are PHPUnit-covered
and carry their own reason below.

A SUCCESSFUL remote install has no e2e test, deliberately. It needs a second
OpenRegister instance publishing a `dashboard-template` object, and CI
provisions one instance. Faking the registry inside the suite would test the
fake. `StoreServiceTest` covers the install path against the engine's real
signatures.

## NEW Requirements

### Requirement: REQ-STORE-001 The store routes MUST exist wherever the store page is declared

LaunchPad's manifest declares a `type: "store"` page, which renders `CnStorePage`,
which calls `GET /apps/launchpad/api/store/items` on mount and
`POST /apps/launchpad/api/store/items/{slug}/install` on an install click.
`appinfo/routes.php` SHALL declare both, and the `{slug}` route SHALL constrain
the slug to `[a-z0-9][a-z0-9-]*[a-z0-9]` so a malformed slug fails at the router
rather than reaching the registry URL.

#### Scenario: The declared page can reach its endpoint

- **GIVEN** `src/manifest.json` declares a page of type `store`
- **WHEN** the route table is read
- **THEN** it MUST contain `store#search` at `/api/store/items`
- **AND** it MUST contain `store#install` at `/api/store/items/{slug}/install`

@e2e exclude route-table shape — asserted by reading appinfo/routes.php in tests/Unit/Support/StoreWiringTest.php; the browser consequence is the next scenario

#### Scenario: An unconfigured store shows the not-configured state

- **GIVEN** no `registry_url` is configured
- **WHEN** an administrator opens the Store page
- **THEN** `GET /api/store/items` MUST answer 200 with outcome `not_configured`
- **AND** the page MUST show its not-configured note
- **AND** it MUST NOT show the unreachable note or an empty result grid

---

### Requirement: REQ-STORE-002 An absent OpenRegister MUST degrade, never fatal

`GenericStoreService` is resolved optionally, exactly as LaunchPad already
resolves the AppHost observability collaborators. When OpenRegister is disabled or
absent the collaborator is null. `search()` MUST then return outcome
`not_configured` with an empty card list, and `install()` MUST refuse, and neither
MUST touch an `OCA\OpenRegister\…` symbol.

An engine that is present but unconfigured behaves the same way, because
`GenericStoreService::isConfigured()` reports false for an empty `registry_url`
and makes no network call.

#### Scenario: No OpenRegister yields not_configured

- **GIVEN** the store service holds a null discovery client
- **WHEN** `search()` is called
- **THEN** the outcome MUST be `not_configured` and the card list MUST be empty

@e2e exclude requires an instance WITHOUT OpenRegister, and CI installs it; asserted in tests/Unit/Service/StoreServiceTest.php

#### Scenario: No OpenRegister refuses an install rather than half-running one

- **GIVEN** the store service holds a null discovery client
- **WHEN** `install()` is called for any slug
- **THEN** it MUST report failure and MUST NOT call `ImportService`

@e2e exclude requires an instance WITHOUT OpenRegister, and CI installs it; asserted in tests/Unit/Service/StoreServiceTest.php

---

### Requirement: REQ-STORE-003 The engine's outcome MUST reach the caller unchanged

The plane distinguishes `store_unreachable` from `store_invalid_response` so a
misconfigured registry does not read as an offline one. LaunchPad SHALL pass the
outcome through verbatim and MUST NOT collapse the two, and MUST NOT substitute
its own. Upstream error detail MUST NOT reach the caller; the engine already keeps
it server-side.

#### Scenario: An invalid response is not reported as unreachable

- **GIVEN** the engine returns outcome `store_invalid_response`
- **WHEN** the service returns
- **THEN** the outcome MUST be `store_invalid_response`

@e2e exclude needs a registry that answers malformed JSON, and CI has no registry; asserted in tests/Unit/Service/StoreServiceTest.php

---

### Requirement: REQ-STORE-004 Installing MUST require an administrator

An install writes dashboards into the instance from a third-party server. The
install endpoint SHALL carry `#[AuthorizedAdminSetting(LaunchPadAdmin::class)]`,
the posture LaunchPad already uses for its other administrative endpoints.

Search SHALL carry `#[NoAdminRequired]`, because browsing a registry addresses no
object of this instance. It forwards a query to an external registry and returns
normalised cards, so there is no local identifier to guess and no IDOR to create.

#### Scenario: Every store route declares a posture

- **GIVEN** the store controller
- **WHEN** its public methods are read
- **THEN** `search` MUST carry `#[NoAdminRequired]`
- **AND** `install` MUST carry `#[AuthorizedAdminSetting]`

@e2e exclude attribute presence — asserted by reflection in tests/Unit/Controller/StoreControllerTest.php; the two scenarios below prove the attributes hold at runtime

#### Scenario: A non-admin is refused the registry config

- **GIVEN** a signed-in account that is not an administrator, holding a valid request token
- **WHEN** it reads or writes `/api/store/config`
- **THEN** both requests MUST answer 403
- **AND** the stored registry MUST be unchanged afterwards

#### Scenario: A non-admin is refused an install

- **GIVEN** the same account
- **WHEN** it posts to `/api/store/items/{slug}/install`
- **THEN** the request MUST answer 403

---

### Requirement: REQ-STORE-005 An install MUST reuse ImportService, not reimplement it

The resolved payload SHALL be materialised into a `launchpad-export-v1` ZIP in a
temporary directory and handed to `ImportService::import()`. LaunchPad MUST NOT
gain a second code path that turns a dashboard payload into rows.

The materialised archive MUST carry a `manifest.json` with `schemaVersion` equal
to the importer's supported version and a `scope`, and one
`dashboards/{uuid}.json` entry per dashboard, because that is what
`validateZipStructure()` requires and what `ExportService` writes.

The temporary archive MUST be removed whether the import succeeds or throws.

#### Scenario: The payload reaches the existing importer

- **GIVEN** a resolved item carrying one dashboard payload
- **WHEN** it is installed
- **THEN** `ImportService::import()` MUST be called exactly once
- **AND** the path it receives MUST be an existing ZIP containing `manifest.json`

@e2e exclude needs a second OpenRegister publishing a template, and CI has one instance; asserted in tests/Unit/Service/StoreServiceTest.php

#### Scenario: An install with no registry is refused with a reason

- **GIVEN** no `registry_url` is configured
- **WHEN** an administrator posts an install for any slug
- **THEN** the request MUST answer 400 with `success` false and a message
- **AND** it MUST NOT answer 500

#### Scenario: A payload with no dashboards is refused before any import

- **GIVEN** a resolved item whose dashboards list is empty or absent
- **WHEN** it is installed
- **THEN** the install MUST fail and `ImportService::import()` MUST NOT be called

@e2e exclude needs a registry serving a malformed item, and CI has no registry; asserted in tests/Unit/Service/StoreServiceTest.php

#### Scenario: The temporary archive does not survive a failing import

- **GIVEN** `ImportService::import()` throws
- **WHEN** the install returns
- **THEN** no temporary archive from that install MUST remain on disk

@e2e exclude a temp-directory effect with no browser surface; asserted in tests/Unit/Service/StoreServiceTest.php

---

### Requirement: REQ-STORE-006 A store install MUST create dashboards, never replace them

The install SHALL call `ImportService::import()` with `preserveUuids` false. A
template published by a foreign instance carries that instance's UUIDs, and one of
them can collide with a live local dashboard. Re-mapping makes the install
additive.

This mirrors the plane's own rule that an install creates rather than replaces,
reached by a different route: the identity that would do the damage is the
dashboard UUID rather than an object `uuid`.

#### Scenario: A colliding UUID still creates

- **GIVEN** a resolved item whose dashboard UUID matches a local dashboard
- **WHEN** it is installed
- **THEN** `import()` MUST be called with `preserveUuids` false

@e2e exclude needs a second OpenRegister publishing a template, and CI has one instance; asserted in tests/Unit/Service/StoreServiceTest.php

---

### Requirement: REQ-STORE-007 The registry token MUST NOT be readable back

`registry_url`, `registry_token` and `registry_register` live in `IAppConfig`
under `launchpad`, because that is where `GenericStoreService` reads them.
LaunchPad's own admin settings write to a different store, so the change adds an
admin-gated endpoint for these three keys.

The read endpoint SHALL return the URL and the register, and for the token SHALL
return only whether one is set. The token value MUST NOT appear in any response.

#### Scenario: Reading the config never returns the token

- **GIVEN** `registry_token` holds a value
- **WHEN** the config is read
- **THEN** the response MUST report that a token is set
- **AND** the response MUST NOT contain the token value

#### Scenario: An empty token submission clears rather than blanks around

- **GIVEN** a stored token
- **WHEN** the config is written with an empty token
- **THEN** the stored token MUST be cleared

@e2e exclude the clear-versus-keep distinction is a request-payload property the browser cannot see; asserted in tests/Unit/Service/StoreServiceTest.php and src/components/admin/__tests__/DashboardRegistrySettings.spec.js

---

### Requirement: REQ-STORE-008 The manifest MUST NOT declare a store block

`src/manifest.json` MUST NOT carry a `store` key. That block exists to configure
OpenRegister's `GenericStoreController`, which LaunchPad does not use, so a block
here would declare a schema and card fields that nothing reads while
`StoreService` declares the ones that are used.

A non-empty `types` list is the specific harm, because it selects the engine's
federated configuration path. The block on `development` declared
`openregister.configset` and `openregister.flows`, which trade registers, schemas
and flows. Had the routes existed, the store page would have offered
configuration sets to somebody looking for a dashboard.

#### Scenario: No store block is declared

- **GIVEN** `src/manifest.json`
- **WHEN** it is decoded
- **THEN** it MUST NOT contain a `store` key

@e2e exclude a source-file property; asserted in tests/Unit/Support/StoreWiringTest.php

#### Scenario: The store page still declares itself

- **GIVEN** the same manifest
- **WHEN** its pages are read
- **THEN** exactly one page MUST carry `type` of `store`


@e2e exclude a source-file property; asserted in tests/Unit/Support/StoreWiringTest.php

---

### Requirement: REQ-STORE-009 An administrator MUST be able to connect a registry without a shell

The three registry keys live in `IAppConfig`, which LaunchPad's own admin
settings do not write. So Beheer ▸ Sharing SHALL carry a form for
`registry_url`, `registry_register` and the token, backed by
`/api/store/config`.

The token field SHALL be write-only. It MUST start empty on every load, and
the page MUST say whether a token is set without showing it. Saving with an
empty token field MUST leave the stored token alone; removing it is a
separate action.

#### Scenario: An administrator saves a registry and reads it back

- **GIVEN** an administrator on Beheer ▸ Sharing
- **WHEN** they enter a registry URL, a register and a token, and save
- **THEN** after a reload the URL and register fields MUST show what they entered
- **AND** the page MUST say a token is set

#### Scenario: The token is never shown back

- **GIVEN** a stored token
- **WHEN** the form loads
- **THEN** the token field MUST be empty
- **AND** neither the page nor the config response MUST contain the token

#### Scenario: The saved registry is the one the store reads

- **GIVEN** an administrator saved a registry URL whose host does not resolve
- **WHEN** they open the Store page
- **THEN** the page MUST show the unreachable note rather than the not-configured one
