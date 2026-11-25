# Proposal: launchpad-demo-seed

## Why

Every LaunchPad board on identity.conduction.nl/screens should be reproducible in a running demo environment, with
its widgets, its people and its data. The spec of that seed lives in ConductionNL/design-system,
`screens-src/launchpad/DEMO-SEED.md`. The demo showcases install one dashboard for everyone, so they cannot show a
personal start page, a department page shared by a team leader, a view-only management page or a first visit.

## What changes

- A demo seed, `data/demo-seed/zuiddrecht-boards.json`, describes the users and groups, the app tile catalogue, the
  allowed sites, two announcements, the shared team folder and one dashboard per board (Vandaag, Mijn werkplek,
  Intranet Zuiddrecht, Team Vergunningen, Managementinformatie). The first-run user `nieuw` gets no dashboard.
- `occ launchpad:demo:load` writes it. Every dashboard has a fixed UUID, so a second run leaves the same state.
- The seed reuses the showcase rules: the same placement hydrator and the same check for which widget types this
  instance renders. A widget whose type is not registered yet is placed as its fallback or skipped, and appears on the
  next run once its type ships.
- `--retire-showcases` uninstalls the bundled showcases, which are visible to everyone and so hide the first visit.

## Out of scope

- Data in other apps' registers (dossiq cases, OpenProject work packages, XWiki pages, mail, calendar events).
- Dashboard versions, public links, scheduled exports, the kiosk set and the store registry stub.
