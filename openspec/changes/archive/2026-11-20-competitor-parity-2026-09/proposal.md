---
kind: umbrella
depends_on: []
---

# Proposal: competitor-parity-2026-09

## Summary

This is the launchpad half of the dossiq competitor parity programme. The
source of record is the round 4 discovery sweep,
`procest/_round4/discovery/` in ConductionNL/market-intelligence, written
2026-09-14: 36 systems read, 636 candidates, 71 capability clusters, beside
the gap register at `procest/_gaps/`.

Ruben's ownership rule governs the split. dossiq reaches full
comparability with the competition, and logic that belongs to another app
is specified in that app. dossiq then consumes it. Launchpad owns the
dashboard, so the dashboard half of the sweep is launchpad's.

Nothing here is implemented. The change indexed below carries its own
`proposal.md`, `design.md`, `specs/` and `tasks.md`.

## Coverage confirmation

The gap register puts no ledger row on launchpad. The discovery sweep
moves 11 of its 636 candidates here, all of them in one cluster, and
launchpad carried no change for it until this umbrella.

Two of the eleven are already shipped in this repo and are not rebuilt.
Both are named with the requirement that carries them, so neither is
rediscovered as a gap.

| candidate | already covered by |
|---|---|
| C-reporting-14, an external page embedded with the host allowed centrally | `iframe-embed-widget` REQ-IFRAME-002, an administrator allow-list of hosts that fails closed, with an empty list meaning embed nothing |
| C-reporting-22, a dashboard scoped to the roles that may see it | `dashboards` REQ-DASH-011 and REQ-DASH-013, group-shared dashboards resolved per user, with `role-feature-permissions` REQ-RFP-001 for feature presence per role |

C-reporting-22 is the cluster's only `must`. The sweep rates it against
dossiq, which has manifest widgets and no role scoping. That is dossiq's
half, not launchpad's, and it is named in the consumer column below.

## The changes under it

| change | cluster | candidates | size | decision | dossiq consumer |
|---|---|---|---|---|---|
| `dashboards-and-who-may-see-them` | 12, dashboards, widgets and who may see them | C-reporting-2, C-reporting-3, C-reporting-10, C-reporting-13, C-reporting-19, C-reporting-21, C-reporting-25, C-reporting-26, C-reporting-28 | M | D6, D21 | needs a dossiq change: dossiq contributes case widgets to a launchpad dashboard and declares which roles may see each one, instead of shipping one manifest layout for everybody |

The cluster's own mechanism line reads "extend launchpad's widget model and
openregister's aggregations; dossiq contributes widgets". The change above
is launchpad's part of that sentence. The row the candidate notes name is
10.1.

## The decisions these rest on

- **D6, relevance-led promotion.** Every `must` enters whatever its passer
  count. The cluster's one `must` is already covered here, so D6 changes
  nothing in launchpad's scope and is recorded for completeness.
- **D21, documented candidates admitted and labelled.** One of the nine,
  C-reporting-25, rests on a vendor page rather than on a run system. The
  spec labels it, and it is never counted in a driven tally.

## Two capabilities that need a stated purpose

C-reporting-13 and C-reporting-19 read one named person's work back to an
administrator. The lane said so itself: "in a gemeente it is an
OR-onderwerp before it is a feature", and "useful for workload balancing
and uncomfortable as surveillance, so it needs a stated purpose". The
change treats that as a requirement, not as a note.

## Affected projects

- `launchpad`: one change, listed above.
- `openregister`: owns the aggregations every report reads. Unchanged here.
- `dossiq`: contributes case widgets and declares who may see them. Needs a
  change of its own.
- `humaniq`: owns hours. The activity reporting below counts events, never
  hours, so it does not become a second time model.
