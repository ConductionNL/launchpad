# Proposal: sharing-policy-board-sync

## Why

Board LpBeheerDelen draws "Wie mag delen, en met wie" with three choices for "Delen toegestaan voor": Iedereen, Alleen
deze groepen, Niemand; "Delen met": Iedereen in de organisatie or Alleen eigen groepen; and a switch "Openbare links
toestaan" with the note "Alleen voor wie dashboards mag delen. Een link heeft altijd een vervaldatum."

Board LpBeheerOnderhoud draws, under Opruimen: "Dashboards van vertrokken medewerkers gaan naar: Hun leidinggevende,
anders Femke de Wit".

REQ-SHARE-014 covers the groups and the recipients, but not "Niemand", and nothing limits public links or requires an
expiry date. REQ-SHARE-012 hands a leaver's dashboard to the earliest person with full rights on it, or deletes it;
the administrator cannot send it to the leaver's manager.

## What changes

- `dashboard-sharing`: "Nobody" as a sharing choice, and an administrator switch for public links that only people who
  may share can use, always with an expiry date (REQ-SHARE-015).
- `dashboard-sharing`: an administrator sets where a leaver's dashboards go: their manager, else a named person
  (REQ-SHARE-016). Without that setting REQ-SHARE-012 applies as before.

## Impact

- `DashboardSharingPolicy.vue` and the sharing settings, the public share create endpoint, the user deletion listener
  (it reads the manager before the account is gone), the Opruimen block of the operations tab, translations.
