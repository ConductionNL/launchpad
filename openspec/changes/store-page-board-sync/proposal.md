# Proposal: store-page-board-sync

## Why

Board LpStore draws the marketplace as a page of dashboard templates: a banner for the connected register ("VNG
dashboardregister, register.vng.nl/dashboards, laatst gecontroleerd vandaag 06:00, Verbonden") with the note "Alleen
beheerders installeren voor de hele organisatie"; lenses Alles 23, Geïnstalleerd 3, Update beschikbaar 1 and
categories such as Intranet 6; a filter ("Voor: gemeenten") and a search; and cards with the title, the publisher, a
status (Geïnstalleerd, Beschikbaar, Update), a description, the version, the number of widgets, how many
organisations use it, and one action (Openen, Installeren, Bijwerken). It pages 8 at a time.

The store page (`type: "store"` in `src/manifest.json`, rendered by `CnStorePage`) still carries the engine's generic
kind lenses (App template, Adapter, Source template, Configuration template, Agent template), which are not dashboard
kinds, and the spec (REQ-STORE-001 to 009) describes the routes and the install, not what the page shows.

## What changes

- `dashboard-store`: the page shows the register banner, the dashboard lenses and the cards as the board draws them
  (REQ-STORE-010), and an installed template is recognised so its card offers Openen (REQ-STORE-011).

## Not in this change

- **Bijwerken** (update an installed template) and **Dashboard publiceren** (publish a dashboard to the register) need
  a product decision. Bijwerken: REQ-STORE-006 says an install never replaces a dashboard, so does an update install the
  new version beside the old one, or update the installed dashboard in place as shipped templates do (REQ-TMPL-020)?
  Dashboard publiceren: who may publish, and to which register, now that the register side is an open OpenRegister
  change (store-over-federated-config)?

## Impact

- `src/manifest.json` store page config (lenses, card fields), `StoreController` (installed marker, last check), the
  card mapping of registry items, translations.
