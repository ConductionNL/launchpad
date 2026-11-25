# Tasks: org-navigation-from-initial-state

- [x] 1. One reader for the rail position, used by `GET /api/admin/org-navigation/position` and the page; a first-load builder that returns `{position, tree, language}` only when the caller may perform both actions
- [x] 2. The workspace page carries `orgNavigation`, built by that builder
- [x] 3. The org navigation store's first load takes it from the initial state instead of fetching; without it, it fetches both
- [x] 4. Unit tests: the builder (payload, unknown position, refusal, no actor or user), the builder key, the reader (once, missing, malformed) and the store (no fetch from the key, fetch without it)
- [ ] 5. Measure on a live instance: the workspace opens without the two org-navigation requests (perf spec, before/after)
