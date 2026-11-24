# Tasks: dashboard-first-load-from-initial-state

- [x] 1. One builder for the visible-dashboards list and the active-dashboard envelope, used by `GET /api/dashboards/visible` and `GET /api/dashboard`
- [x] 2. The workspace page carries `dashboardFirstLoad` (`{visible, active}`) when the caller may read both, built by that builder
- [x] 3. The dashboard store's first load takes it from the initial state instead of fetching; later loads fetch
- [x] 4. Unit tests: the builder (list, quota envelope, active envelope, refusal), the page key, the builder key, the reader (once, missing, malformed) and the store (no fetch first, fetch after)
- [ ] 5. Measure on a live instance: the workspace opens without the two dashboard requests (perf spec, before/after)
