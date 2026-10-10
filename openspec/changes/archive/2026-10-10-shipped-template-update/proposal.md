---
kind: code
depends_on: [mijn-werkdag-template]
---

# Update an installed ready-made template in place, and bring the members' copies along

## Why

Issue #781, found while live-checking `mijn-werkdag-template` and `cross-app-attention-feed` on 5 October 2026.

- An installed ready-made template could not follow a newer version. `occ launchpad:template:install <id> --force` added the new version as a second template. The administrator had to move the groups over and delete the old one by hand.
- Dashboards members had already received kept the old widgets. A compulsory widget added in a later version never reached them.
- While the old and the new template both targeted a group, `TemplateService::getApplicableTemplate()` returned "the first match" of a list sorted by name. Both carry the same name, so which one a new member got depended on the order the database returned them in.

Measured on `development` at `a65bde07`: the installed version is recorded (`shipped_template_version_<id>`), re-sync exists and matches a copy's widget to its template widget by row id (`TemplateResyncService`, REQ-RESYNC-001..005). The missing piece was replacing the template's widgets without replacing its rows.

## What changes

- `ShippedTemplateUpdateService` brings an installed template to the shipped version in place. It keeps the template (id, UUID, name, groups, default flag) and pairs the installed widgets with the new ones, so a widget in both versions keeps its row. Then it runs the existing `merge` re-sync.
- `occ launchpad:template:install <id> --update [--dry-run]` prints every widget added, removed and changed.
- `POST /api/admin/templates/shipped/{id}/update` (`dryRun` supported), and on the Templates page an "Update to version N" button that shows the dry run in a dialog before anything is written.
- `getApplicableTemplate()` picks by one rule when several templates target the same group: installed shipped template first (newest version first), then the lowest id.

## Capabilities

### Modified capabilities

- `admin-templates`: adds REQ-TMPL-020 (update in place) and REQ-TMPL-021 (one rule picks the template).
- `cli-commands`: REQ-CLI-012 gains `--update` and `--dry-run`.

## What a member notices

The re-sync contract is the existing one and is not changed here:

- a compulsory or other widget the new version adds arrives in the copy;
- a widget the new version drops leaves the copy;
- a widget the member added stays;
- a template widget the member moved or changed is set back to the template's, and one the member removed comes back (this is what `merge` does for every template widget, see REQ-RESYNC-003);
- the member gets the "your dashboard was updated" notification (REQ-RESYNC-005).

## What changes on an existing instance

- Nothing happens on upgrade by itself. An installed template stays at its version until an administrator updates it.
- `getApplicableTemplate()`: only instances where two or more templates target the same group see a difference, and only for users without a dashboard of their own (or, with personal dashboards off, for every member of such a group on their next visit). REQ-TMPL-021 spells it out.

## Impact

- `lib/Service/ShippedTemplateUpdateService.php`, `lib/Exception/TemplateNotInstalledException.php` (new)
- `lib/Service/ShippedTemplateService.php` (`findInstalled()`, `updateAvailable` in the listing), `lib/Service/TemplateService.php`
- `lib/Command/TemplateInstallCommand.php`, `lib/Controller/AdminShippedTemplateController.php`, `appinfo/routes.php`
- `src/dialogs/ShippedTemplateUpdateDialog.vue` (new), `src/components/admin/tabs/TemplatesPage.vue`, `src/services/api.js`
- No database migration.

## Out of scope

- Updating the installed template's description and category. They stay as installed; only the widgets follow.
- A key per widget in the definition. The three-pass pairing needs none for the templates that ship today; a definition with several untitled widgets of one type that all change at once would have them removed and added again instead of changed in place, which members do not notice.
- Removing the earlier copy a forced install left behind. The administrator deletes it on the Templates page.
