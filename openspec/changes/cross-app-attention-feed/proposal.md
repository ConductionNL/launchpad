---
kind: code
depends_on: [mijn-werkdag-template]
---

# "First today" across apps: each app says what needs attention, LaunchPad shows one ranked list

## Why

The Zuiddrecht start page (`LpStart`) opens with "Vandaag eerst": a few lines from all the employee's apps that need attention today, each with a link into the app. The design notes say this contract does not exist yet.

Measured on 5 October 2026:

- dossiq, pipelinq, decidiq and learniq each show a "First today" card on their own simple dashboard. Each is a count of objects in an OpenRegister register with a filter (`visibleWhen.source` with `register`, `schema`, `filter`), a title, a reason and a link to the list.
- Those four declarations live in each app's `src/menu-layout.simple.json`. That file is compiled into the app's JavaScript. Nothing serves it: pipelinq and decidiq have no manifest endpoint, and the ones dossiq and learniq have serve the base manifest without this layout. LaunchPad cannot read them from a running instance.
- Nextcloud's dashboard widget interface (`OCP\Dashboard\IWidget`, `IAPIWidgetV2`) gives one widget per app. It has no way to rank items from several apps in one list.

So LaunchPad needs a small declaration it can read, in the words the apps already use.

## What changes

- An app declares its attention items in one data file, `appinfo/attention.json`. No PHP interface and no registration call. The vocabulary is the count source the apps already write.
- LaunchPad reads the file of every app that is enabled for the user and offers the list at `GET /api/attention/sources`.
- A new widget, "First today" (`attention`), runs each count in the browser as the signed-in user, ranks the items that need attention and shows them with a link into the owning app.
- The link's query is built from the same filter as the count, so the number and the list it opens cannot drift.
- An app that is not installed, or declares nothing, does not appear. A count that fails is shown as failed. It is never shown as "nothing to do".
- The shipped "Mijn werkdag" template (version 2) puts the widget at the top, compulsory.

## Capabilities

### New capabilities

- `attention-feed`: the declaration, the sources endpoint, the ranking and the widget.

### Modified capabilities

- `admin-templates`: REQ-TMPL-018, the shipped template gains the widget.

## What each app adds (not in this change)

One file, `appinfo/attention.json`, and a test that its filter equals the filter of the app's own "First today" card. Nothing else. The four files, written from what the apps ship today, are in `design.md`.

## Impact

- `lib/Service/AttentionSourceService.php`, `lib/Controller/AttentionController.php`, `appinfo/routes.php` (new)
- `src/components/Widgets/Renderers/AttentionWidget.vue`, `AttentionWidgetForm.vue`, `src/services/attentionFeed.js` (new), `src/constants/widgetRegistry.js`, `lib/widget-types.json`
- `data/templates/mijn-werkdag.json` (version 2)
- No database migration. No change to an app other than LaunchPad.

## Out of scope

- One line per object ("Parkeervergunningen binnenstad: the term ends today"), as the design draws it. Version 1 shows one line per declared item with its count ("3 of your cases are past their deadline"). A per-object line needs a title field and a detail link per schema; the declaration leaves room for it (`version`).
- Items that are not an OpenRegister count (for example unread mail).
- A personal order or hiding items.
