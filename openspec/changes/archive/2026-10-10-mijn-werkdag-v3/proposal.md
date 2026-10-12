---
kind: code
depends_on: [shipped-template-update]
---

# Mijn werkdag version 3: tickets, decisions waiting for a vote, and lists that hide when their app is not here

## Why

The Zuiddrecht design asks the start page for pipelinq "my tickets" and decidiq "to initial / waiting for your vote". Version 2 left both out: at the time the only way to show them was a Nextcloud dashboard widget that needs the legacy bridge. Object lists on the apps' registers render as installed, so they can go in now that pipelinq (`ticket`, with `assignee`, `status`, `slaDeadline`) and decidiq (`decision`, with `lifecycle`) carry the fields.

Two things had to be true first:

- An installed template can be brought to a newer version in place (`shipped-template-update`, REQ-TMPL-020). Version 3 is the first version that path carries.
- A list of an app that is not installed must not show an error line to the employee. Measured on 5 October 2026: OpenRegister answers 404 "Register not found" and the list widget then shows "Could not load these records" under its title. The install command already names Nextcloud widgets no app provides; registers get the same idea, with one difference: LaunchPad's server does not know an instance's registers, so the page asks and hides (REQ-TMPL-022).

## What changes

- `data/templates/mijn-werkdag.json` is version 3: "Mijn tickets" (pipelinq `ticket`), "Wacht op uw stem" (decidiq `decision`), every list sets `hideWhenUnavailable`, recent activity moves down.
- The workspace page leaves out an object-list that set `hideWhenUnavailable` when OpenRegister answers 404 for its source, outside edit mode (`src/services/sourceAvailability.js`, `Views.vue`).
- The shipped-template listing, the Templates page and the install command name the registers a template's lists read.
- `tests/fixtures/registers/pipelinq-ticket.json` and `decidiq-decision.json` (copies of the schemas at the apps' development heads), and the renders-as-installed test holds the new lists' fields and enum values against them.

## What an instance that installed version 2 sees

Nothing changes by itself. The Templates page shows "Update to version 3" and `occ launchpad:template:install mijn-werkdag --update --dry-run` prints: added "Mijn tickets (object-list)" and "Wacht op uw stem (object-list)", changed "Zaken over de termijn", "Mijn zaken" (settings: the hide flag) and "Verder waar u was" (position, order), unchanged 2. After the update every member's copy follows (REQ-TMPL-020).

## Out of scope

- Knowing on the server which registers an instance has. The page asks OpenRegister; the server names the registers.
- A list for learniq. The design does not ask for one on the employee's start page.
