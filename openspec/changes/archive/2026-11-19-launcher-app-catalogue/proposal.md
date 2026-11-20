---
kind: code
depends_on: []
---

# Browse a catalogue of approved apps and ask IT for one that is missing

## Why

An employee who needs an app today either knows its address or asks around.
LaunchPad has no list of the applications the organisation has approved, and
no way to ask for one that is missing. The earlier "Catalog" view
(`openspec/changes/archive/2026-06-21-deprecate-catalog`) was a read-only list
of widget types, removed because nobody could add anything from it; this is a
different thing: a list of approved business applications that turn into tiles.

Matrix row **tile-catalogue** (`openspec/parity/capabilities.json`), "Browse a
catalogue of approved apps and ask IT for one that is missing", rated `no`,
`built.state` `none`, area `launcher`, LaunchPad's core area.

- Workspace 365, yes: https://support.workspace365.net/en/articles/175398-understand-and-manage-user-permissions "Request apps in the app store"; https://portal.productboard.com/iqfsnhkpzih6grzjwagif2ek/tabs/3-launched launched "Request/Approve application from App Store".
- Microsoft Viva, partial: partner cards "Request the cards ... sent to the App Catalog Admin for their approval"; for dashboard editors, not every employee.
- Homarr, partial: a central app list admins manage (`apps/nextjs/src/app/[locale]/manage/apps/`), "Select an app to add to this board"; no way for a user to request a missing app.

## What changes

- Administrators keep a catalogue of approved apps: name, short description, icon, address, and the groups that may see each one.
- In the add flow, users see a "From the app catalogue" section with the apps their groups may see. Picking one places a tile.
- "Can't find your app?" opens a short request form (app name, what you need it for). Administrators get a Nextcloud notification, see open requests on the admin page, and approve (which prefills a catalogue entry) or decline with a reason. The requester is notified either way.

## Capabilities

### New capabilities

- `app-catalogue`: the approved-app catalogue and the request flow.

## Impact

- Two new tables, `oc_launchpad_catalogue_apps` and `oc_launchpad_app_requests`, in one migration
- New `CatalogueService`, `CatalogueController` (user) and `AdminCatalogueController` (admin), new actions in `lib/actions.seed.json`
- `lib/Notification/Notifier.php` (two subjects), `src/modals/WidgetPickerModal.vue` (catalogue section), a new admin section
- Tile creation reuses `WidgetService::addTileFromArray()`

## Out of scope

- Installing or licensing the application itself. Approving a request adds a catalogue entry; provisioning the app stays with IT.
- A separate catalogue page or workspace mode, which the deprecation above rejected.
