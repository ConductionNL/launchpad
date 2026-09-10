# Store plane dashboard sharing

LaunchPad shares dashboards well inside one instance and not at all between two.
`DashboardShareService` reaches users and groups, `PublicShareService` publishes a
read-only link, and templates seed new dashboards. Every one of them stops at the
instance boundary. Moving a dashboard to another Nextcloud means an administrator
exports a ZIP, carries the file, and imports it on the other side.

This change adopts OpenRegister's AppHost store plane so that carrying step
disappears. An administrator points LaunchPad at a registry, browses the dashboard
templates it publishes, and installs one. The fetched payload goes to LaunchPad's
existing `ImportService`, so an installed dashboard and an imported ZIP produce
the same rows through the same code.

It also fixes a surface that is broken on `development`. LaunchPad declares a
`type: "store"` page, which renders `CnStorePage`, which calls
`/apps/launchpad/api/store/items`. That route does not exist. The page is in the
navigation and its only request 404s. The `store` block beside it declares
OpenRegister configuration sets rather than dashboards, so even with routes it
would have offered the wrong thing.

## Affected code units

- **NEW** `lib/Service/StoreService.php` — descriptor construction, discovery delegation, payload to ZIP materialisation, install
- **NEW** `lib/Controller/StoreController.php` — the three store endpoints and the registry config endpoints
- **NEW** `tests/Unit/Service/StoreServiceTest.php`
- **NEW** `tests/Unit/Controller/StoreControllerTest.php`
- **MODIFY** `appinfo/routes.php` — register the store routes the page already calls
- **MODIFY** `lib/AppInfo/Application.php` — bind `StoreService` with an optional `GenericStoreService`
- **MODIFY** `src/manifest.json` — replace the configuration-set store block with a dashboard-template one

## Capabilities

### New Capabilities

- `dashboard-store`: browse a remote registry of dashboard templates and install one into this instance, over the engine's guarded discovery client.

### Modified Capabilities

- `dashboard-export-import`: the ZIP container gains a second producer. A store install builds one in memory and imports it, so the container contract now has to hold for payloads that never touched a disk an administrator chose.

## Why the leaf owns install

The store plane's spec carries a requirement that a leaf app declares its store
and ships no controller, aliasing the engine's `GenericStoreController` instead.
That requirement amends ADR-080 Decision 3 on the grounds that "the only thing
that actually varied was which schemas an install may write".

For LaunchPad that is not what varies. The engine offers two install ops,
`writeObject` and `setAppConfig`, plus configuration bundles through
`FederatedStoreCatalog`. A dashboard is none of them: it lives in LaunchPad's own
tables behind `DashboardMapper`, and no OpenRegister schema holds one. So there is
no allowlist entry that makes the engine's installer able to do this.

The engine treats a leaf-owned controller as a supported state.
`Bootstrap::aliasControllerUnlessLeafDefinesIt()` is a no-op when the leaf ships
its own `Controller\StoreController`, and the comment beside the store alias names
dossiq as an app in exactly this position. Discovery stays engine-owned, which is
where the SSRF guarding, the redirect refusal and the token handling live.

## What this change does not do

It does not publish. The plane is a read-only client and nothing in it writes to a
registry. A registry is populated out of band until a publish path exists, which
needs an authenticated outbound write, a wider token scope, and a moderation
posture. `design.md` says what that would take.

It does not touch in-instance sharing. Nothing about who may see an existing
dashboard changes.
