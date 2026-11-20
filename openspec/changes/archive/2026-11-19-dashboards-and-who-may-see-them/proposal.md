---
kind: code
depends_on: []
---

# Proposal: dashboards-and-who-may-see-them

## Summary

Launchpad builds dashboards well and ships none. A new instance opens on an
empty grid, a shared dashboard is either yours or the organisation's with
nothing in between, and nobody can ask what the storage is doing or what
one team got done last month. This change adds the reports the product
ships, the personal layer over a shared dashboard, and the activity and
storage reporting the sweep found everywhere else.

## Candidates and cluster

Cluster 12 of `procest/_round4/discovery/build-plan.md`
(ConductionNL/market-intelligence, 2026-09-14), "Dashboards, widgets and
who may see them". Owner launchpad, size M, no cluster decision. Eleven
candidates, one `must`, 15 passers of which 11 driven, no matrix holes.
Proving system gitlab. Two of the eleven are already shipped here and are
listed in `competitor-parity-2026-09` rather than rebuilt.

| candidate | relevance | dossiq | what it asks |
|---|---|---|---|
| C-reporting-10 | should | no | a personal arrangement over a shared dashboard, resettable to the organisation's |
| C-reporting-2 | could | partial | reports the product ships, run without building them |
| C-reporting-3 | could | no | a dated status report with a health rating, prefilled |
| C-reporting-13 | could | no | one named person's activity across the product, for an administrator |
| C-reporting-19 | could | no | a per person activity heatmap |
| C-reporting-21 | could | no | search inside a widget's results without changing its saved search |
| C-reporting-25 | could, documented | no | geographic trend and risk analysis |
| C-reporting-26 | could | no | recent colleague activity, filtered by what you may see |
| C-reporting-28 | could | partial | storage use per workspace and service over time, exportable |

## The evidence, verbatim

Quoted from `procest/_round4/discovery/candidates.md`, best-evidence
column.

- **C-reporting-10**, "should, one team view plus each handler's own,
  without a second object". Best evidence: "itop: Every console group opens
  on a dashboard the administrator edits in place and any user overrides
  for themselves (menu-tree.md)". Two driven passers, itop and
  xxllnc-zaken. Its note: "One names the override, the other the reset.
  Neither works without the other".
- **C-reporting-2**, "could, most gemeenten want their own". Best evidence:
  "glpi: Reports (Tools, Reports, front/report.default.php,
  report.dynamic.php, report.year.php, report.state.php,
  report.reservation.php, report.contract.php)".
- **C-reporting-3**, "could, a bestuurlijke voortgangsrapportage on a
  programme; a gemeente writes these in Word today". Best evidence: "odoo:
  project_update.py (status, health, generated description)".
- **C-reporting-13**, "could, and in a gemeente it is an OR-onderwerp
  before it is a feature". Best evidence: "plane:
  workspace-user-activity, export-workspace-user-activity,
  workspace-user-stats, user-activity-graph, completed-graph". Its note:
  "Three passers already. Needs a stated purpose, because it reads as
  surveillance".
- **C-reporting-19**, "could, useful for workload balancing and
  uncomfortable as surveillance, so it needs a stated purpose". Best
  evidence: "gitlab: Analyze, Contributor analytics (menu-tree.md)". Three
  driven passers, forgejo, gitea and gitlab.
- **C-reporting-21**, "could". Best evidence: "xxllnc-zaken: Dashboard,
  Mijn openstaande zaken (dashboard-anatomy.md)".
- **C-reporting-25**, "could, it is a reporting claim rather than a screen,
  and it is the only place in this file where a map is a management tool".
  Best evidence: "mozard: documented,
  /functionaliteiten/geografische-informatie-en-kaartbeheer". Documented,
  never counted in a driven tally (D21).
- **C-reporting-26**, "could". Best evidence: "zammad: Activity stream
  (app/models/activity_stream.rb, permission-scoped)".
- **C-reporting-28**, "could, it decides a hosting invoice, not a tender".
  Best evidence: "tuleap: plugins/statistics, /admin disk usage". Its note:
  "Both answer where the disk went and what is safe to delete".

## What launchpad already ships, and does not rebuild

- **The external page and its allow-list.** `iframe-embed-widget`
  REQ-IFRAME-002 already restricts embeddable hosts to an administrator
  allow-list and fails closed, with an empty list meaning embed nothing.
  C-reporting-14 is covered.
- **Role scoping.** `dashboards` REQ-DASH-011 and REQ-DASH-013 give
  group-shared dashboards resolved per user, and `role-feature-permissions`
  REQ-RFP-001 gives feature presence per role. C-reporting-22 is covered on
  launchpad's side. Its dossiq side is not, and that is dossiq's change.
- **The activity events themselves.** `activity-feed-integration` already
  publishes launchpad events with audience targeting. The colleague
  activity widget below reads that feed, it does not build a second one.
- **Admin templates and re-sync.** `admin-templates` distributes a layout
  per group and re-syncs it. The personal layer below sits over a shared
  dashboard the user does not own, which a template copy does not answer.

## What launchpad builds

- **A personal layer over a shared dashboard.** A user reorders, resizes
  and hides widgets on a dashboard they do not own. The layer is theirs
  alone, and one action removes it.
- **A report library.** Report definitions the product ships, versioned
  with the app, listed in a gallery and run without composing anything.
- **A status report.** A dated report on a body of work with a health
  rating, prefilled from what the product already knows and editable before
  it is kept.
- **Activity reporting with a declared purpose.** Per person and per team,
  as a figure and as a calendar of intensity, behind an administrator right
  that names why it exists and logs every read.
- **Recent colleague activity.** A widget over the existing activity feed,
  filtered to what the reader may see.
- **In-widget search.** A filter over one widget's rendered rows that never
  writes back to its saved search.
- **Geographic trend reporting.** Counts and rates per area over a period,
  from objects that carry a location.
- **Storage reporting.** Use per workspace and per service over time, with
  what can be removed named, and an export.

## How dossiq consumes it

There is no dossiq change for cluster 12 yet. dossiq needs one, and it
consumes rather than models: it contributes its case widgets to a launchpad
dashboard and declares which roles may see each one, instead of shipping a
single manifest layout for everybody. Ledger row 10.1 is the composition
half of that.

## Affected projects

- [x] `launchpad`: this change.
- [ ] `dossiq`: contributes widgets and declares who sees them. Needs a
      change of its own.
- [ ] `openregister`: the aggregations the reports read. Unchanged here.

## Out of scope

- Hours. Activity reporting counts events, never hours. Hours are
  humaniq's under decision D19.
- A query builder. The report library ships definitions, it does not
  replace the dashboard editor.
