# Tasks: launchpad-demo-seed

- [x] Seed definition `data/demo-seed/zuiddrecht-boards.json` for the boards in DEMO-SEED.md
- [x] `DemoSeedService` with accounts, files and dashboards helpers; `DemoSeedWidgets` expands tiles and fallbacks
- [x] `occ launchpad:demo:load [--seed] [--password] [--retire-showcases]`
- [x] Unit tests: idempotent load, expected dashboards, tile and fallback expansion, accounts
- [x] Documentation of the demo accounts in `docs/demo-seed.md`
- [ ] Re-run the seed after the new widgets (wiki, work packages, my cases, pie chart, graph) land on development
