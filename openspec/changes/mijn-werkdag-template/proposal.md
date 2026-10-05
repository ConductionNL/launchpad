---
kind: code
depends_on: []
---

# Ship a "Mijn werkdag" start page as a template an administrator can install, export and import

## Why

The Zuiddrecht design (`LpStart`, "launchpad: mijn werkdag") asks for a start page for a municipal employee, rolled out as an admin template per group with compulsory widgets.

Measured on `development` at `da724518`:

- Admin templates per group exist (`admin-templates`, REQ-TMPL-001..017). A template names its groups, can be the default, and its widgets carry `isCompulsory`. First access copies it, compulsory flags included.
- Export and import exist (`dashboard-export-import`): the `launchpad-export-v1` archive, `POST /api/admin/export|import`, `occ launchpad:export` and `occ launchpad:import`.
- No template ships with the app. The six bundled showcases install as view-only group dashboards, not as templates.
- A template did not survive the round trip. The importer dropped every `isCompulsory` flag (REQ-EXIM-004 said so for all dashboards), dropped the template's category and description, and made the importing administrator its owner. For a template whose point is its compulsory widgets, export then import produced a different template.
- The Templates page had no download for one template. The admin page exports the whole site only.

## What changes

- LaunchPad ships `data/templates/mijn-werkdag.json`: a versioned template definition, readable JSON in the shape an export writes.
- An administrator adds it from the Templates page ("Ready-made templates") or with `occ launchpad:template:install mijn-werkdag [--group=...] [--default] [--force]`.
- The install goes through the existing importer, so a shipped template and an uploaded archive take one path.
- An imported admin template keeps its compulsory flags, category and description, and has no owner.
- The Templates page gets a download per template.

## Capabilities

### Modified capabilities

- `admin-templates`: adds REQ-TMPL-018, templates that ship with LaunchPad.
- `dashboard-export-import`: adds REQ-EXIM-012, an admin template travels with its definition. REQ-EXIM-004 names the exception and the import result names the dashboards it created.
- `cli-commands`: adds REQ-CLI-012, `launchpad:template:install`.

## What the template holds, and what the design asks that it cannot hold yet

The template must work as installed. A live check on 5 October 2026 showed that the first version did not: four of its widgets proxied Nextcloud dashboard widgets of dossiq and decidiq. Those widgets paint through their own script, which LaunchPad only loads when the legacy widget bridge is on (off by default, because it loads every app's widget script on the page). As installed they showed "This widget can only be shown on the Nextcloud dashboard itself." under their raw id. With the bridge on, the dossiq widgets rendered, still under their raw id, and listed cases that were not the signed-in user's. So the template now uses lists on the case register instead.

| Design section | In the template | Widget |
| --- | --- | --- |
| Greeting | Header "Mijn werkdag" (compulsory) | `header` |
| Vandaag eerst | "Zaken over de termijn": the employee's cases whose deadline is today or earlier (compulsory) | `object-list` on dossiq `case` |
| Mijn werk, dossiq | "Mijn zaken": the employee's open cases, by deadline | `object-list` on dossiq `case` |
| Verder waar u was | Recent activity | `nc-widget` to `activity`, which has an items API |

No widget that renders as installed exists yet for:

- the greeting with the employee's name and the date. The header widget shows a fixed title.
- "Vandaag eerst" as one ranked list across apps. That is the next change, `cross-app-attention-feed`.
- the "Mijn werk" cards with one number per app.
- dossiq "my tasks". Tasks are not objects in the dossiq register, and the dossiq tasks widget needs the bridge.
- pipelinq "my tickets" and decidiq "to initial". pipelinq registers six Nextcloud widgets and decidiq one; none has both an items API and that content.
- "Agenda vandaag". LaunchPad's calendar widget shows "No calendar is selected yet" until each user picks a calendar, and the Calendar app's own widget is only there when that app is installed.
- "Van de organisatie". The news widget needs a feed address that differs per organisation.
- "Alle apps" as a row of pills.

## Updating an instance that installed an earlier version

There is no in-place update. `occ launchpad:template:install mijn-werkdag --force` adds the current version as a second template next to the first. The administrator then moves the groups to the new one and deletes the old one. Dashboards members already received keep their widgets: a copy does not follow a new template. Replacing the installed template's widgets in place and re-syncing the copies (REQ-RESYNC-001) is the missing piece and is not in this change.

## Impact

- `lib/Service/ShippedTemplateService.php`, `lib/Command/TemplateInstallCommand.php`, `lib/Controller/AdminShippedTemplateController.php` (new)
- `lib/Service/ImportService.php`, `lib/Service/PlacementPayloadHydrator.php`
- `src/components/admin/tabs/TemplatesPage.vue`, `src/services/api.js`
- No database migration. No change for dashboards that are not admin templates.

## Out of scope

- Updating an installed template when a later LaunchPad ships a higher `templateVersion`. The installed version is recorded so this can be built; today a forced install adds the new version next to the old one and the administrator re-syncs.
- Carrying a group dashboard's `groupId` through import. It is dropped today, for every group dashboard. Found while measuring, not changed here.
