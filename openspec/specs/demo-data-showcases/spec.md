---
status: done
---

# Demo Data Showcases Specification

## Purpose

The `demo-data-showcases` capability provides administrators with one-click installation of pre-built, fully populated example dashboards that illustrate different organizational use cases. Showcases are bundled as ZIP archives containing a machine-readable `export.json` manifest plus per-locale page JSON files and media assets, loaded from disk on demand, and installed as `group_shared` dashboards visible to all users (via REQ-DASH-012 default-group sentinel). The capability includes widget type validation, graceful skip-on-missing for unknown widgets, NL-only localization in v1, and idempotent installation via API and CLI commands.

## Data Model

Showcases are stored as ZIP archives under `showcases/{id}/{id}.zip` within the app bundle. Each ZIP contains:

- `export.json` — the canonical machine-readable manifest with full page content
- `{locale}/` — per-locale directory tree (e.g. `nl/`)
- `{locale}/home.json` — home page layout and widget definitions
- `{locale}/navigation.json` — navigation configuration
- `{locale}/footer.json` — footer configuration
- `{locale}/_media/*.jpg` — bundled image assets referenced by widget `src` fields

**`export.json` top-level shape:**

```json
{
  "exportVersion": "1.3",
  "schemaVersion": "1.3",
  "exportDate": "2026-03-07T12:00:00.000Z",
  "requiresMinVersion": "0.8.11",
  "language": "nl",
  "pages": [
    {
      "_exportPath": "home",
      "uniqueId": "page-<stable-uuid>",
      "title": "...",
      "content": { }
    }
  ],
  "navigation": { "type": "megamenu", "items": [] },
  "footer": { "content": "..." },
  "comments": []
}
```

**Per-page content object shape** (from `{locale}/home.json` or inline in `export.json` pages):

```json
{
  "uniqueId": "page-<stable-uuid>",
  "title": "...",
  "language": "nl",
  "layout": {
    "columns": 1,
    "rows": [
      {
        "columns": 1,
        "backgroundColor": "",
        "collapsible": false,
        "widgets": [
          {
            "type": "heading",
            "column": 1,
            "order": 1,
            "content": "Welkom",
            "level": 2
          },
          {
            "type": "text",
            "column": 1,
            "order": 2,
            "content": "Markdowntekst hier"
          },
          {
            "type": "image",
            "column": 1,
            "order": 3,
            "src": "zorgteam.jpg",
            "alt": "Het zorgteam",
            "objectFit": "cover"
          }
        ]
      }
    ],
    "sideColumns": {
      "left":  { "enabled": false, "backgroundColor": "", "widgets": [] },
      "right": { "enabled": false, "backgroundColor": "", "widgets": [] }
    }
  }
}
```

Widget objects use **inline flat fields** — there is no `position.{x,y,w,h}` or `config` sub-object. Placement is specified by `column` (integer) and `order` (integer) within a row. The layout is row-based, not grid-coordinate-based.

The 8 widget types used across bundled showcases are:

| Type | Required fields | Notes |
|---|---|---|
| `heading` | `content`, `level` (1–5) | |
| `text` | `content` (markdown) | |
| `divider` | `style`, `color`, `height` | |
| `links` | `layout` (`tiles`\|`list`), `columns`, `items[]` | |
| `image` | `src` (bare filename), `alt`, `objectFit` | `src` resolves relative to `_media/` on install |
| `file` | `path` (relative), `name` | File may not exist in fresh install; skip-on-missing applies |
| `news` | `sourcePath`, `layout`, `columns`, `limit`, `sortBy`, `sortOrder`, `showImage`, `showDate`, `showExcerpt`, `autoplayInterval`, `filters` | Runtime resolution — no embedded data |
| `video` | `provider`, `src` (URL), `title`, `autoplay`, `loop`, `muted` | |
| `people` | `selectionMode`, `filters`, `layout`, `columns`, `limit`, `sortBy`, `showFields{}` | Queries live user data at render time |

Showcase installations create `group_shared` dashboard records with:
- `type = 'group_shared'`
- `groupId = 'default'` (visible to all users via REQ-DASH-012)
- `metadata.showcaseId = '{showcase-id}'` (for idempotency tracking)
- `metadata.sourceLanguage = '{language-code}'`

> **NOTE — LaunchPad improvement**: Per-showcase idempotency tracking via `metadata.showcaseId` is a LaunchPad design decision. The reference implementation used a single app-wide boolean flag (`demo_data_imported`) which only works for a single dataset and cannot distinguish between multiple installed showcases. LaunchPad's per-showcase approach is strictly more correct and is not a port of the reference behavior.

The system maintains a registry of installed showcases by querying existing dashboards with matching showcase ID in metadata.

## Requirements


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

Each `dashboards/{uuid}.json` MUST declare `type: "group_shared"`, a null `userId`,
`permissionLevel: "view_only"` and a `showcase-{id}` slug, so an installed showcase
is a read-only group dashboard rather than someone's personal one.

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

### Requirement: REQ-DEMO-002 List available showcases endpoint

@e2e exclude Endpoint auth and install-state flags are API contracts: 401/403 and the list shape are pinned by AdminDemoShowcasesControllerTest and Newman's GET /api/admin/demo-showcases. The case-handler gallery entry is covered in the browser under REQ-DEMO-001.

The system MUST expose `GET /api/admin/demo-showcases` returning a JSON array of available showcases. Response format:

```json
[
  {
    "id": "de-bron",
    "name": "De Bron",
    "description": "Intranet dashboard voor een zorginstelling",
    "thumbnailUrl": "/apps/launchpad/showcases/de-bron/thumbnail.png",
    "language": "nl",
    "isInstalled": true,
    "installedDashboardUuid": "9b2df4a1-2e8c-4a3b-8f1c-5d7e9a1b2c3d"
  }
]
```

The endpoint MUST be admin-only (HTTP 403 for non-admin users). Response MUST include installation status (`isInstalled` boolean) and UUID if installed.

#### Scenario: List endpoint requires admin role
- **GIVEN** a non-admin user
- **WHEN** they send `GET /api/admin/demo-showcases`
- **THEN** the system MUST return HTTP 403

#### Scenario: All showcases appear in the list
- **GIVEN** 5 showcase ZIP archives are present
- **WHEN** admin calls `GET /api/admin/demo-showcases`
- **THEN** the response MUST include 5 items
- **AND** each item MUST have `id`, `name`, `description`, `thumbnailUrl`, `language`, `isInstalled`

#### Scenario: Installation status is accurate
- **GIVEN** admin has installed showcase `de-bron`
- **WHEN** they call `GET /api/admin/demo-showcases`
- **THEN** the `de-bron` item MUST have `isInstalled: true`
- **AND** MUST include `installedDashboardUuid`

#### Scenario: Installation status is false if not yet installed
- **GIVEN** showcase `horizon-labs` has never been installed
- **WHEN** admin calls `GET /api/admin/demo-showcases`
- **THEN** the `horizon-labs` item MUST have `isInstalled: false`
- **AND** MUST NOT include `installedDashboardUuid` (or it MUST be null)

### Requirement: REQ-DEMO-003 Install showcase endpoint

@e2e exclude The endpoint contract (201, 200 when already installed, 404, 403, surfaced skips) is pinned by AdminDemoShowcasesControllerTest and Newman's unknown-id 404; the group_shared result is covered in the browser for case-handler under REQ-DEMO-001. The nl/ locale tree, metadata.sourceLanguage and media extraction scenarios describe behaviour the archive format does not have.

The system MUST expose `POST /api/admin/demo-showcases/{id}/install` creating an installed showcase as a `group_shared` dashboard with `groupId = 'default'`. An optional `?lang=` query parameter is accepted for forward compatibility but always resolves to `nl` in v1. The created dashboard MUST be visible to all users (via REQ-DASH-012). Response format:

```json
{
  "installedDashboardUuid": "9b2df4a1-2e8c-4a3b-8f1c-5d7e9a1b2c3d",
  "skippedWidgets": []
}
```

If any widget types are unknown at install time, they MUST be silently skipped and listed in `skippedWidgets` array (graceful degradation). The endpoint MUST be admin-only (HTTP 403 for non-admin). Return HTTP 201 on success, HTTP 404 if showcase not found, HTTP 422 if version check fails, HTTP 400 if validation fails.

#### Scenario: Install showcase creates visible group-shared dashboard
- **GIVEN** admin sends `POST /api/admin/demo-showcases/gemeente-duin/install`
- **WHEN** the installation completes successfully
- **THEN** the system MUST create a dashboard with `type = 'group_shared'`, `groupId = 'default'`
- **AND** all users MUST see it in their `GET /api/dashboards/visible` response
- **AND** the response MUST return HTTP 201 with `installedDashboardUuid`

#### Scenario: Language parameter is accepted but resolves to NL in v1
- **GIVEN** admin sends `POST /api/admin/demo-showcases/de-linden/install?lang=en`
- **WHEN** the installation completes
- **THEN** the system MUST load the `nl/` locale tree (the only available locale)
- **AND** the installed dashboard MUST carry `metadata.sourceLanguage = 'nl'`

#### Scenario: Media assets are extracted on install
- **GIVEN** admin installs showcase `de-bron` which contains `nl/_media/zorgteam.jpg` inside the ZIP
- **WHEN** the installation completes
- **THEN** the service MUST extract all files from `nl/_media/` and store them in an accessible location
- **AND** `image` widgets referencing `"src": "zorgteam.jpg"` MUST resolve correctly at render time
- **AND** the response MUST not return an error due to missing media

#### Scenario: Unknown showcase returns 404
- **GIVEN** admin sends `POST /api/admin/demo-showcases/unknown-id/install`
- **WHEN** no showcase with that ID exists
- **THEN** the system MUST return HTTP 404 with message "Showcase not found"

#### Scenario: Non-admin user cannot install
- **GIVEN** a non-admin user
- **WHEN** they send `POST /api/admin/demo-showcases/de-bron/install`
- **THEN** the system MUST return HTTP 403

#### Scenario: Widgets with unknown types are skipped
- **GIVEN** showcase JSON references widget type `future-widget-v2` which is not registered
- **WHEN** admin installs the showcase
- **THEN** the widget MUST NOT be created
- **AND** the response MUST include `skippedWidgets: ["future-widget-v2"]`
- **AND** the installation MUST succeed (HTTP 201) with remaining valid widgets installed

#### Scenario: Response warns of skipped widgets
- **GIVEN** a showcase installation where 2 widgets are skipped
- **WHEN** the installation completes
- **THEN** the response MUST include `skippedWidgets: ["unknown-type-1", "unknown-type-2"]`
- **AND** the frontend or CLI MUST display a warning to the user

#### Scenario: Showcase metadata preserved for idempotency
- **GIVEN** an installed showcase dashboard
- **WHEN** the system queries it by dashboard ID
- **THEN** the dashboard metadata MUST contain `showcaseId: 'de-bron'` (or the relevant showcase ID)
- **AND** MUST also contain the source language

### Requirement: REQ-DEMO-004 Idempotent installation

@e2e exclude Not observable in the browser beyond the gallery's installed state; pinned by DemoShowcasesServiceTest::testInstallIsIdempotent and AdminDemoShowcasesControllerTest::testInstallReturns200WhenAlreadyInstalled. Installation is tracked by an app-config marker, not by dashboard metadata as written.

Reinstalling an already-installed showcase MUST return the existing dashboard's UUID without creating a duplicate. The system MUST track installation state by querying existing `group_shared` dashboards with matching `showcaseId` in metadata.

> **NOTE**: The reference implementation tracked idempotency using a single app-wide boolean flag, which only supports one demo dataset. LaunchPad's per-showcase `metadata.showcaseId` approach is more granular and is the correct design for a multi-showcase system. This is a LaunchPad improvement, not a port.

#### Scenario: Reinstall returns same UUID
- **GIVEN** admin has installed showcase `van-der-berg`, receiving UUID `U1`
- **WHEN** they install `van-der-berg` again
- **THEN** the system MUST return the same UUID `U1`
- **AND** no new dashboard MUST be created
- **AND** the existing dashboard MUST NOT be modified

#### Scenario: Each showcase maintains separate installation state
- **GIVEN** admin has installed both `de-bron` and `de-linden`
- **WHEN** they query installation status for both
- **THEN** both MUST show `isInstalled: true` with different UUIDs
- **AND** each has its own showcase metadata

### Requirement: REQ-DEMO-005 Widget type validation and skip-on-missing

At install time the system MUST decide, per widget, whether LaunchPad can render it, and place only those. A widget MUST be kept when any of these holds:

- it is a tile (`tileType` set), rendered by LaunchPad's tile renderer;
- its `widgetId` is one of LaunchPad's own widget types, listed in `lib/widget-types.json` (`object-list`, `nc-widget`, `calendar`, `text` and the rest);
- its `widgetId` is a Nextcloud dashboard widget registered on this instance (`IManager::getWidgets()`).

Anything else MUST be skipped, recorded in `skippedWidgets` and logged, and the installation MUST still succeed with the widgets that were kept. The case this exists for is a bare Nextcloud widget id whose app is not installed.

`lib/widget-types.json` is the one list of LaunchPad's own types. The frontend widget registry MUST equal it (`widgetRegistry.completeness.spec.js` reads the same file), so the types the installer keeps and the types the workspace renders cannot drift. A missing or malformed list MUST fail the install loudly rather than skip every LaunchPad widget.

An `nc-widget` MUST be kept whatever Nextcloud widget it proxies. The check is on the widget's type; whether the proxied app is installed decides what the tile shows, not whether it is placed. Checking the target would make a showcase's shape depend on which apps an instance happens to have.

A kept widget MUST keep its configuration: its `content` (the register an object-list reads, the widget an nc-widget proxies, a calendar's layout) MUST be copied onto the placement.

**Why this requirement was rewritten.** It used to say widgets were validated against "the registered widget registry", and the code read that as Nextcloud's registry only. LaunchPad's own types are not in it, so the `case-handler` showcase, built entirely from them, installed as an empty dashboard and still reported success. Behind that, the installer did not copy `content`, so a kept widget would have landed unconfigured. The first was caught by the e2e in `tests/e2e/demo-showcase-case-handler.spec.ts`, not by the unit suite, which opened the archive but never ran the install. The second was found while fixing the first, and the same e2e's configuration assertions would have been its next failure.

#### Scenario: A LaunchPad widget type is installed although Nextcloud does not register it
- **GIVEN** showcase `case-handler`, whose widgets are `object-list`, `nc-widget`, `calendar` and `nc-widget`
- **AND** none of those ids is in Nextcloud's dashboard registry
- **WHEN** an admin installs it
- **THEN** all four widgets MUST be placed
- **AND** `skippedWidgets` MUST be empty

#### Scenario: A kept widget keeps its configuration
- **GIVEN** showcase `case-handler`
- **WHEN** an admin installs it
- **THEN** the `object-list` placement MUST read register `dossiq`, schema `case`
- **AND** the two `nc-widget` placements MUST proxy `tasks` and `mail-unread`
- **AND** the `calendar` placement MUST carry its `internalCalendars` setting

#### Scenario: A Nextcloud widget whose app is not installed is skipped
- **GIVEN** a showcase with a `text` widget, a `recommendations` widget and a `mail-unread` widget
- **AND** Nextcloud registers `recommendations` but not `mail-unread`, because the Mail app is not installed
- **WHEN** the showcase is installed
- **THEN** `text` and `recommendations` MUST be placed
- **AND** `skippedWidgets` MUST be `["mail-unread"]`

@e2e exclude Needs an instance where a Nextcloud widget's app is deliberately absent, and no bundled showcase carries such a widget in a stable way; pinned by DemoShowcasesServiceTest::testInstallStillSkipsANextcloudWidgetWhoseAppIsMissing.

#### Scenario: Unknown widget type is skipped
- **GIVEN** a showcase with widget type `future-timeline` (neither a LaunchPad type nor registered)
- **WHEN** the showcase is installed
- **THEN** that widget MUST NOT be created
- **AND** `skippedWidgets` MUST include `"future-timeline"`
- **AND** other widgets in the showcase MUST still be created

@e2e exclude The bundled archives contain no unknown widget type, so no browser run can arrange one; pinned by DemoShowcasesServiceTest::testInstallSkipsUnknownWidgetTypes and testPartitionWidgetsTreatsTilesAsValid.

#### Scenario: Mixed valid and invalid widgets
- **GIVEN** a showcase with 5 widgets: 3 known, 2 unknown
- **WHEN** the showcase is installed
- **THEN** 3 valid widgets MUST be created
- **AND** 2 invalid widgets MUST be skipped
- **AND** `skippedWidgets` MUST be `["unknown-1", "unknown-2"]`
- **AND** the response MUST warn the admin

@e2e exclude Same limit as the scenario above, the bundled archives carry no unknown types; the partition is pinned by DemoShowcasesServiceTest and the surfaced list by AdminDemoShowcasesControllerTest::testInstallSurfacesSkippedWidgets.

#### Scenario: The list of LaunchPad types is the list the workspace renders
- **GIVEN** `lib/widget-types.json` and the frontend widget registry
- **WHEN** the frontend unit tests run
- **THEN** the registry's keys MUST equal the types in `lib/widget-types.json`

@e2e exclude A build-time contract with no runtime surface, enforced by src/constants/__tests__/widgetRegistry.completeness.spec.js, which reads lib/widget-types.json.

#### Scenario: Skip is logged for audit
- **GIVEN** a showcase installation with skipped widgets
- **WHEN** the installation completes
- **THEN** the system MUST log the event with showcase ID, widget types skipped, and admin user ID
- **AND** logs MUST be queryable for audit purposes

@e2e exclude Log output has no browser surface. Note the install log today carries the showcase id, dashboard uuid, skipped list and language, but not the admin user id this scenario asks for.

### Requirement: REQ-DEMO-006 Uninstall showcase endpoint

@e2e exclude An API contract pinned by the DemoShowcasesServiceTest uninstall tests, the AdminDemoShowcasesControllerTest destroy tests and Newman's DELETE. The case-handler e2e uninstalls in afterAll but asserts nothing about it.

The system MUST expose `DELETE /api/admin/demo-showcases/{id}` soft-removing an installed showcase (delete the dashboard and cascade to widget placements). The endpoint MUST be idempotent: calling it twice MUST both return HTTP 204, whether the showcase was installed or not.

#### Scenario: Uninstall deletes the dashboard
- **GIVEN** admin has installed showcase `gemeente-duin`, creating dashboard `D1`
- **WHEN** they send `DELETE /api/admin/demo-showcases/gemeente-duin`
- **THEN** the system MUST soft-delete dashboard `D1` and all its widget placements
- **AND** the response MUST return HTTP 204
- **AND** `GET /api/dashboards/visible` for any user MUST no longer include `D1`

#### Scenario: Uninstall is idempotent
- **GIVEN** admin sends `DELETE /api/admin/demo-showcases/de-bron`
- **WHEN** the showcase is not installed (or was already uninstalled)
- **THEN** the system MUST return HTTP 204 (not 404)

#### Scenario: Uninstall cascades to widgets
- **GIVEN** an installed showcase with 5 widgets
- **WHEN** admin uninstalls the showcase
- **THEN** the dashboard MUST be deleted
- **AND** all 5 widget placements MUST be deleted
- **AND** no orphaned widgets MUST remain

#### Scenario: Non-admin cannot uninstall
- **GIVEN** a non-admin user
- **WHEN** they send `DELETE /api/admin/demo-showcases/de-bron`
- **THEN** the system MUST return HTTP 403

### Requirement: REQ-DEMO-007 Localization support

All v1 bundled showcases are NL-only. Each showcase ZIP contains a single `nl/` locale directory. The install endpoint accepts an optional `?lang=` query parameter for forward compatibility; in v1 this parameter is accepted but always resolves to `nl`. Multi-locale support (EN, DE, FR variants) is a v2 goal. The installed dashboard MUST record its source language in metadata.

#### Scenario: Install always uses NL locale in v1
- **GIVEN** any of the 5 bundled showcases
- **WHEN** admin installs with or without a `?lang=` parameter
- **THEN** the system MUST load and install from the `nl/` locale tree
- **AND** the dashboard metadata MUST record `sourceLanguage: 'nl'`

@e2e exclude Describes an nl/ locale tree the archive format does not have; each showcase declares its language in its manifest instead (see REQ-DEMO-001).

#### Scenario: Language parameter accepted for forward compatibility
- **GIVEN** admin sends `POST /api/admin/demo-showcases/de-linden/install?lang=en`
- **WHEN** no `en/` locale exists in the ZIP
- **THEN** the system MUST fall back to `nl/` (the only available locale)
- **AND** MUST NOT return an error — graceful fallback is required

@e2e exclude An API parameter with no browser surface; the ?lang= value is passed through as the source language and has no test of its own.

#### Scenario: List endpoint includes language code
- **GIVEN** showcase list response
- **WHEN** items are returned
- **THEN** each item MUST include a `language` field (e.g. `'nl'`)
- **AND** the frontend MUST display the language to the admin

@e2e exclude Only partly covered: the case-handler e2e asserts the language field on two entries (case-handler and gemeente-duin), not every item, and nothing checks that the gallery displays it.

### Requirement: REQ-DEMO-008 Read-only showcase source files

@e2e exclude No browser test covers this requirement. The edit rule is pinned by PermissionServiceGroupSharedTest (a member reads, an Editor or a LaunchPad admin edits) and forking by DashboardServiceForkTest; nothing yet asserts the gallery offers only Install and Uninstall.

Bundled showcase archives under `data/demo-showcases/` are read-only source definitions and MUST NOT be edited or deleted through the admin UI, which offers "Install" and "Uninstall" only.

Installing a showcase creates a `group_shared` dashboard in the `default` group with `permissionLevel: view_only` (REQ-DEMO-001). Who may change it follows the rule for every group-shared dashboard (`PermissionService::getEffectivePermissionLevel()`), not the stored level alone:

- Nextcloud admins, LaunchPad admins and users with the Editor role or higher get `full` and MUST be able to edit it: widgets, layout, name.
- Everyone else MUST see it read-only.

Anyone who wants an editable version of their own MUST be able to fork it. The sidebar's "Add dashboard" button forks the dashboard currently shown into a personal copy (REQ-DASH-020, `POST /api/dashboards/{uuid}/fork`), and the copy carries every widget with its `content` and tile fields. Forking is refused while personal dashboards are switched off (REQ-ASET-003) and is bound by the per-user dashboard quota.

**Why this was rewritten.** It used to say "Installed dashboard is fully editable", which read as a contradiction of the `view_only` the showcases install with. Both were half the rule: `view_only` is what the audience gets, and admins and Editors can still edit.

#### Scenario: Showcase source files are not listed in editable templates
- **GIVEN** admin views the template management or dashboard list
- **WHEN** they look for editable templates
- **THEN** showcase source files MUST NOT appear
- **AND** showcase installations (installed dashboards) MUST appear as regular group-shared dashboards

#### Scenario: An installed showcase is read-only for its audience
- **GIVEN** showcase `case-handler` is installed
- **AND** user "alice" holds no admin or Editor role
- **WHEN** she opens it
- **THEN** she MUST be able to see it
- **AND** she MUST NOT be able to change its widgets, layout or name

#### Scenario: Admins and Editors can edit an installed showcase
- **GIVEN** showcase `case-handler` is installed
- **WHEN** a LaunchPad admin, a Nextcloud admin or a user with the Editor role opens it
- **THEN** they MUST be able to edit it like any other group-shared dashboard

#### Scenario: A user makes an editable copy of an installed showcase
- **GIVEN** showcase `case-handler` is installed and personal dashboards are allowed
- **WHEN** a user viewing it presses "Add dashboard" in the sidebar
- **THEN** a personal copy MUST be created that they own and can edit
- **AND** the copy MUST carry every widget with its configuration

#### Scenario: Admin UI prevents editing showcase source
- **GIVEN** the admin UI displaying a showcase card
- **WHEN** admin tries to click or interact with the source template
- **THEN** the UI MUST show only "Install" and "Uninstall" buttons
- **AND** MUST NOT offer "Edit" or "Delete source" options

### Requirement: REQ-DEMO-009 CLI commands for operations

@e2e exclude occ commands have no browser surface. DemoShowcasesInstallCommand and DemoShowcasesListCommand have no tests yet.

The system MUST expose two Symfony console commands for showcase management.

1. `php occ launchpad:demo-showcases:install <showcase-id> [--lang=nl] [--force]` — installs the specified showcase. The `--force` flag bypasses the idempotency guard and reinstalls even if the showcase is already installed (creating a new dashboard). Without `--force`, reinstall returns the existing UUID. Output MUST include the installed dashboard UUID and any skipped widgets.
2. `php occ launchpad:demo-showcases:list` — lists all available showcases with installation status. Output format: table with columns `ID`, `Name`, `Status`, `Language`.

Both commands MUST validate admin role (require Nextcloud admin user credentials or skip if run as web/cron context). Commands MUST be non-interactive and suitable for automation.

#### Scenario: Install via CLI
- **GIVEN** admin runs `php occ launchpad:demo-showcases:install de-bron`
- **WHEN** the command completes
- **THEN** the system MUST output "Installed dashboard {uuid}"
- **AND** the dashboard MUST be created and visible to all users

#### Scenario: Force reinstall via CLI
- **GIVEN** showcase `de-bron` is already installed with UUID `U1`
- **WHEN** admin runs `php occ launchpad:demo-showcases:install de-bron --force`
- **THEN** the command MUST reinstall the showcase, creating a new dashboard
- **AND** the old dashboard `U1` MUST be removed or superseded
- **AND** the command MUST output the new UUID

#### Scenario: Install without --force returns existing UUID
- **GIVEN** showcase `van-der-berg` is already installed with UUID `U1`
- **WHEN** admin runs `php occ launchpad:demo-showcases:install van-der-berg` (no --force)
- **THEN** the command MUST output the existing UUID `U1` without creating a duplicate
- **AND** MUST indicate that the showcase was already installed

#### Scenario: List command shows all showcases
- **GIVEN** 5 showcases available
- **WHEN** admin runs `php occ launchpad:demo-showcases:list`
- **THEN** the output MUST show a table with 5 rows
- **AND** each row MUST include showcase ID, name, and installation status (Installed/Not installed)

#### Scenario: CLI honors the same validation as API
- **GIVEN** admin tries to install a non-existent showcase via CLI
- **WHEN** the command runs
- **THEN** the system MUST output an error "Showcase not found"
- **AND** exit with code 1
