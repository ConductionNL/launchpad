# Tasks: competitor-parity-2026-09

- [x] 1.1 Build `dashboards-and-who-may-see-them` (discovery cluster 12, decisions D6 and D21). Archive it on merge and tick it here.
  - Archived 19 Nov as `archive/2026-11-19-dashboards-and-who-may-see-them`, spec synced to `openspec/specs/dashboards-and-who-may-see-them` (10 REQ-DWMS requirements).
- [x] 2.1 Hand dossiq its half of cluster 12: case widgets contributed to a launchpad dashboard, each declaring which roles may see it. Give the lane candidate C-reporting-22 and ledger row 10.1 with it.
  - Handed over 19 Nov as the dossiq openspec change `case-widgets-declare-who-may-see-them` ([ConductionNL/dossiq#6672](https://github.com/ConductionNL/dossiq/pull/6672), merged 19 Nov), with candidate C-reporting-22 and ledger row 10.1.
- [x] 2.2 Ask the openregister lane which aggregations a shipped report may rely on, so the report library does not query objects directly.
  - Handed over 19 Nov as the openregister openspec change `aggregations-a-shipped-report-may-rely-on` ([ConductionNL/openregister#10869](https://github.com/ConductionNL/openregister/pull/10869), open against main).
- [x] 3.1 Keep the already-covered table current: C-reporting-14 on `iframe-embed-widget`, C-reporting-22 on `dashboards` and `role-feature-permissions`.
  - Checked 20 Nov against `openspec/specs/`: REQ-IFRAME-002 (admin allow-list of embeddable hosts, fail-closed), REQ-DASH-011 (group-shared dashboard type), REQ-DASH-013 (visible-to-user resolution) and REQ-RFP-001 (feature presence visibility by role) all still exist under those names, so the table stands as written.
- [x] 3.2 Add any later launchpad change that cites the gap register or the discovery sweep to the index above, in the same PR that opens it.
  - Checked 20 Nov: no launchpad change other than `dashboards-and-who-may-see-them` (already indexed, archived 19 Nov) cites C-reporting candidates, the round 4 discovery sweep or the gap register. With nothing left to index, the umbrella is archived; a later change that cites the sweep names this archived umbrella in its own proposal.
