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

Built only from widgets that exist on `development`:

| Design section | In the template | Widget |
| --- | --- | --- |
| Greeting | Header "Mijn werkdag" (compulsory) | `header` |
| Vandaag eerst | dossiq deadline alerts (compulsory) and overdue cases | `nc-widget` to `procest_deadline_alerts_widget`, `procest_overdue_cases_widget` |
| Mijn werk, dossiq | "Mijn zaken" and "Mijn taken" | `object-list` on the dossiq case register, `nc-widget` to `procest_my_tasks_widget` |
| Mijn werk, decidiq | "Besluitvorming" | `nc-widget` to `decidesk` |
| Agenda vandaag | Agenda, one day | `calendar` |
| Verder waar u was | Recent activity | `nc-widget` to `activity` |

No widget exists yet for:

- the greeting with the employee's name and the date. The header widget shows a fixed title.
- "Vandaag eerst" as one ranked list across apps. That is the next change, `cross-app-attention-feed`. Until then the dossiq deadline widget stands in for it.
- the "Mijn werk" cards with one number per app.
- pipelinq "my tickets". pipelinq registers six Nextcloud widgets (leads, deals, find client, start request, create lead, recent activities) and none lists the employee's tickets, so the template has no pipelinq widget.
- decidiq "to initial". The decidiq widget shows open votes and the next meeting.
- "Van de organisatie". The news widget needs a feed address that differs per organisation, so it is not in a shipped template.
- "Alle apps" as a row of pills.

The widget ids `procest_*` and `decidesk` are the ids those apps register today. They are older than the apps' current names and are used as they are.

## Impact

- `lib/Service/ShippedTemplateService.php`, `lib/Command/TemplateInstallCommand.php`, `lib/Controller/AdminShippedTemplateController.php` (new)
- `lib/Service/ImportService.php`, `lib/Service/PlacementPayloadHydrator.php`
- `src/components/admin/tabs/TemplatesPage.vue`, `src/services/api.js`
- No database migration. No change for dashboards that are not admin templates.

## Out of scope

- Updating an installed template when a later LaunchPad ships a higher `templateVersion`. The installed version is recorded so this can be built; today a forced install adds the new version next to the old one and the administrator re-syncs.
- Carrying a group dashboard's `groupId` through import. It is dropped today, for every group dashboard. Found while measuring, not changed here.
