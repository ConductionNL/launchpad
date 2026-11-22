# Demo seed: the boards as a running demo

The demo seed fills a LaunchPad instance with the people, dashboards and data of the LaunchPad boards on
[identity.conduction.nl/screens](https://identity.conduction.nl/screens/). The seed itself is data, in
`data/demo-seed/zuiddrecht-boards.json`. Its source is `screens-src/launchpad/DEMO-SEED.md` in the design-system
repository.

```bash
occ launchpad:demo:load
occ launchpad:demo:load --retire-showcases            # also uninstall the bundled showcases
occ launchpad:demo:load --password='another-password' # password for users this run creates
```

You can run it as often as you like. Every dashboard has a fixed UUID, so a second run updates the same rows and
leaves the same state. Users that already exist keep their password. The first-run user `nieuw` loses its own
dashboards on every run, so the first visit can be shown again.

The bundled showcases (Gemeente Duin, Intranet Zuiddrecht and the others) are visible to everyone. While one is
installed, no user sees the first-visit page "U heeft nog geen dashboard". Use `--retire-showcases` on a demo instance
that must show it.

## Demo accounts

These accounts are for demo instances only. Never load the seed on an instance with real users.

Password of every account the seed creates: `Zuiddrecht-demo-2026!`

| User | Name | Groups | What you see |
|---|---|---|---|
| `pieter` | Pieter Jansen | vergunningen, medewerkers, bedrijfsvoering | Vandaag (opens first), Mijn werkplek, Intranet, Team Vergunningen, Managementinformatie (view only) |
| `marieke` | Marieke Jansen | vergunningen, teamleiders, medewerkers | owns Team Vergunningen, shared with vergunningen |
| `sanne` | Sanne de Vries | vergunningen, medewerkers | Team Vergunningen as a member who may add widgets |
| `thomas` | Thomas Brouwer | vergunningen, bestuurszaken, medewerkers | |
| `yasmina` | Yasmina El Amrani | vergunningen, medewerkers | |
| `hanneke` | Hanneke Visser | directie, bedrijfsvoering, medewerkers | Managementinformatie |
| `communicatie` | Team Communicatie | communicatie, medewerkers | Intranet Zuiddrecht |
| `beheer` | Functioneel beheerder | admin, launchpad-beheer | the admin pages |
| `nieuw` | Noor Nieuw | none | the first visit |

Managementinformatie is a view-only dashboard of the group bedrijfsvoering, so Pieter sees it as a member of that
group. `nieuw` is in no group on purpose. Intranet Zuiddrecht is a dashboard of the group medewerkers, and a member of that
group never sees the first-visit page.

## What the seed writes

- Groups and users above, with their memberships of the seeded groups.
- The app catalogue: one entry per app tile (Zaaksysteem, Outlook web, TOPdesk, Raadsinformatie, Kadasterviewer,
  Declaraties, Talk, Agenda, Word, Bestanden, Smoelenboek, Verlof en rooster, Personeelsgids).
- The allowed sites for embedded pages, news feeds and live tiles.
- Two announcements: the new way of handling Woo requests, and the maintenance banner of the case system.
- The folder Vergunningen in Marieke's files with four example files, shared with the group vergunningen.
- Five dashboards: Vandaag, Mijn werkplek, Intranet Zuiddrecht, Team Vergunningen and Managementinformatie.

A tile of an enabled app becomes an app tile once that widget type ships; until then it is a launcher tile in the
tile's colour. A widget whose type does not exist yet is placed as its fallback (Mijn zaken as a case list) or
skipped, and the command lists it. Run the seed again after a release with new widgets to place them.

Lists and counters that read dossiq, pipelinq or decidiq stay empty until those apps and their demo data are
installed: run their own demo seeds, for example `occ dossiq:demo:seed`, then open the dashboards again.
