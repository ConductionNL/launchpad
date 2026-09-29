---
kind: code
depends_on: []
---

# Roll a dashboard back to an earlier version from the workspace page

## Why

`openspec/features.overlay.json` advertises `dashboard-versioning` as stable with the promise "You roll a dashboard back to last week in one step", and the service exists (`lib/Service/DashboardVersionService.php`: list, fetch, explicit snapshot, restore), but no page calls `/api/dashboards/{uuid}/versions`. A promise on the feature page with no screen behind it is a defect, and the row sits in the core area `building`, so the missing half is built.

This change covers 1 row(s) of the parity matrix (`openspec/parity/capabilities.json`), decided `build` on 2026-09-28.

**build-rollback** (matrix `launchpad`, area `building`), "Roll a dashboard back to how it looked last week." Rated `no` for us, `built.state` `building`.
- Demand: own-code. No demand row; the row comes from our own code or our own matrix.
- Microsoft Viva Connections, partial: https://learn.microsoft.com/en-us/sharepoint/homesites/create-dashboard 'The dashboard details contain settings for your dashboard, page versioning'; https://learn.microsoft.com/en-us/sharepoint/homesites/create-multilingual-dashboard 'view the version history of the default dashboard'; restoring a version is not described (read
- Dashy, partial: source read at 4.7.0: services/endpoints/save-config.js:61-75 every save copies the old conf.yml to user-data/config-backups/<name>-<timestamp>.backup.yml; searched src for backup restore: no UI lists or restores them, an admin copies a file back on the server
- Matrix note: openspec/features.overlay.json advertises dashboard-versioning as stable ("You roll a dashboard back to last week in one step"); no page reaches it.

## What changes

- A "Version history" entry in the dashboard menu, visible to the owner and to administrators, opens a list of saved versions with number, date, author and note.
- "Save this version now" creates a named snapshot.
- "Restore" on a version asks for confirmation, restores it, and reloads the dashboard. The service already snapshots the current state as `pre-restore` first, so a restore can itself be undone.
- Where versioning is not available for the dashboard (a groupfolder-backed one), the entry is hidden, not disabled.

## Capabilities

### Modified capabilities
- `dashboard-versioning`: adds the requirements for the history list, the explicit snapshot and the restore, as screens.
