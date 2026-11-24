# Proposal: setup-five-steps-and-admin-tour

## Why

Board LpInstellen draws the setup at step 3 of 5, with the steps Welkom, Groepen, Voorbeelden, Rollen and Klaar, and
says "de opslagstap is weg". Board LpBeheerAlgemeen describes the setup as "Groepen, voorbeelddashboards en rollen, in
vijf stappen van welkom tot klaar" and draws the admin tour at "stap 1 van 6", with the stops Algemeen, Sjablonen,
Rollen en rechten, Delen, Kiosk and Demogegevens.

The spec and the code still have six setup steps, the fifth being the footer editor, and an admin tour of five stops
(general settings, templates, roles, sharing, operations). The footer has its own place on the admin tab
Organisatiemenu (board LpBeheerNavigatie), so the setup does not need to ask for it.

## What changes

- `setup-wizard`: the wizard has five steps: Welcome, Groups, Examples, Roles, Done. The footer step goes. The step
  counter reads "n / 5". The welcome text names groups, example dashboards and roles.
- `runtime-shell`: the admin tour has six stops, in the order of the board: General, Templates, Roles and rights,
  Sharing, Kiosk, Demo data.

## Out of scope

- `occ launchpad:setup` keeps accepting `footer_config` in its YAML file (cli-commands owns that schema); the footer
  stays configurable on the Organisatiemenu tab.

## Impact

- `src/modals/SetupWizardModal.vue`, `lib/Service/SetupWizardService.php` (step count and statuses), the admin tour
  declaration, translations.
