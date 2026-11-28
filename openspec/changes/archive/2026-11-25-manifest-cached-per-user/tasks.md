# Tasks: manifest-cached-per-user

- [x] 1. `GET /api/manifest` keeps the manifest it builds per user and app version for 60 seconds and answers from it
- [x] 2. The workspace page carries the kept manifest as `runtimeManifest` when the caller may perform `manifest.index`; it never builds one
- [x] 3. The page's one manifest request answers from the carried manifest instead of asking
- [x] 4. Unit tests: the cache (kept per user, no cache, not a manifest), the page's first load (allowed, refused, no actor or user), the endpoint (answers from the kept manifest, keeps what it builds), the reader and the request
- [x] 5. Measure on a live instance: a warm workspace load asks no manifest (perf spec, before/after): measured live 25 Nov 2026 (sandbox Nextcloud 34 with Redis as distributed cache, launchpad development): a warm workspace load sent no `GET /api/manifest` (11 launchpad requests, none of them the manifest). Without a distributed cache (plain sqlite install) the manifest is still requested, as designed: nothing keeps it
