## MODIFIED Requirements

### Requirement: REQ-DEMO-001 Bundled showcase ZIP archives

The system MUST ship a showcase ZIP archive per bundled id under
`data/demo-showcases/{id}/{id}.zip`, and `DemoShowcasesService::BUNDLED_IDS` is the
list of record. Each ZIP MUST carry a `manifest.json` plus one
`dashboards/{uuid}.json`, and MAY carry `metadata-fields.json` and an `assets/`
tree. Each `manifest.json` MUST contain:
- `schemaVersion`: schema version integer
- `scope`: `"dashboard"`
- `dashboardCount`: number of dashboards in the archive
- `showcaseId`: the bundled id, matching the directory and file name
- `showcaseName`: the name an admin sees in the gallery
- `showcaseDescription`: one sentence saying what the dashboard shows
- `showcaseLanguage`: locale code of the dashboard's own copy

Each `dashboards/{uuid}.json` MUST declare `type: "group_shared"`, a null `userId` and a `showcase-{id}` slug, so an installed showcase is a group dashboard rather than someone's personal one. Its `permissionLevel` MUST be `view_only`, except for a showcase that shows a shared intranet colleagues add to, which MAY be `add_only`.

**This paragraph previously described an `export.json` plus a `nl/` locale tree, and
neither has ever been in any bundled ZIP.** The archives have always been
`manifest.json` + `dashboards/`, which is what `ExportService` writes and
`ImportService` reads. The spec described a format the code does not produce, so a
reader implementing against it would have built something no bundled archive matches.

Two kinds of showcase are bundled, and the difference is what a reader takes away.

**Organisation showcases** answer "what does an intranet built on this look like".
They are Dutch, and they mirror the reference source dataset so existing copy and
screenshots stay reusable:
- `de-bron`, a healthcare and nursing organisation
- `de-linden`, a university
- `gemeente-duin`, a municipality
- `horizon-labs`, a tech startup
- `van-der-berg`, a law firm
- `zuiddrecht-intranet`, the intranet of the demo municipality Zuiddrecht, which every colleague may add widgets to (`add_only`)
- `zuiddrecht-management`, the management information of Zuiddrecht (`view_only`)

**Role showcases** answer a different question: what one person's working day looks
like on one page.
- `case-handler`, a case handler's cases, tasks, agenda and unread mail

A role showcase MAY be in English. `case-handler` places the fleet's own widgets (a
dossiq case list, the Tasks app, a calendar, unread mail) and those carry English
labels, so a Dutch shell around English content would read as a half-translation.
The five organisation showcases stay NL-only. Multi-locale variants of any showcase
remain a v2 goal.

#### Scenario: Showcase ZIP archives exist and load without error
- **GIVEN** LaunchPad is installed and enabled
- **WHEN** the system initializes
- **THEN** every id in `BUNDLED_IDS` MUST be readable from `data/demo-showcases/{id}/{id}.zip`
- **AND** each ZIP MUST contain a `manifest.json` parseable as JSON, carrying the required fields
- **AND** each `manifest.json` `showcaseId` MUST equal the directory and file name it was read from

@e2e exclude Archive shape is a packaging contract with no browser surface; pinned for every bundled id by DemoShowcasesServiceTest::testEveryBundledIdShipsAReadableArchive.

#### Scenario: export.json version is checked against minimum app version
- **GIVEN** showcase `de-bron` with `"requiresMinVersion": "0.8.11"` in `export.json`
- **WHEN** the running app version is older than `0.8.11`
- **THEN** the system MUST reject the install with HTTP 422 and a clear version mismatch message
- **AND** the showcase MUST still appear in the list endpoint with `isInstalled: false`

@e2e exclude Not implemented: the archives carry a manifest.json with no requiresMinVersion, and the install path has no version check or 422 response.

#### Scenario: A showcase declares the language of its own copy
- **GIVEN** showcase `gemeente-duin` and showcase `case-handler`
- **WHEN** an admin lists the available showcases
- **THEN** `gemeente-duin` MUST declare `showcaseLanguage: "nl"`
- **AND** `case-handler` MUST declare `showcaseLanguage: "en"`, because a role showcase places widgets whose labels are English

#### Scenario: A role showcase installs as a read-only group dashboard
- **GIVEN** showcase `case-handler`
- **WHEN** an admin installs it
- **THEN** the installed dashboard MUST carry `type: "group_shared"` and `permissionLevel: "view_only"`
- **AND** it MUST place exactly four widgets: the case list, tasks, today's agenda and unread mail

#### Scenario: Invalid showcase ZIP is rejected
- **GIVEN** a showcase ZIP whose `manifest.json` is malformed or missing
- **WHEN** the system attempts to load it
- **THEN** the system MUST log an error
- **AND** the showcase MUST NOT appear in the available list
- **AND** installation attempts MUST return HTTP 500

@e2e exclude The bundled archives are all valid, so a browser run cannot arrange a broken one; a missing archive is pinned by DemoShowcasesServiceTest::testDescribeShowcaseReturnsNullWhenZipMissing, a malformed one by no test yet.

#### Scenario: The Zuiddrecht intranet lets colleagues add widgets
- **GIVEN** an administrator installed the `zuiddrecht-intranet` showcase
- **WHEN** a colleague opens "Intranet Zuiddrecht"
- **THEN** the dashboard is a group dashboard the colleague may add widgets to, and not edit or delete

#### Scenario: The management information is read only
- **GIVEN** an administrator installed the `zuiddrecht-management` showcase
- **WHEN** a colleague opens "Managementinformatie"
- **THEN** the dashboard shows the counts from the registers and offers no editing
