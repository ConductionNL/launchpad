# Tasks: workspace-api-reads-from-initial-state

- [x] 1. A builder that answers the Workspace's first API reads in-process through their controller actions (announcements, dismissed notices, the active dashboard's reactions, its personal layer when somebody else owns it, the first page of up to four visible Files widgets), leaving out a read that is refused, fails or is not 200
- [x] 2. The workspace page carries `workspaceApiReads`, built by that builder
- [x] 3. The app's HTTP client answers the first matching GET of each carried read from the page, once; every other request goes out
- [x] 4. Unit tests: the builder (all reads, own dashboard, refused and failing read, anonymous, Files widget cap), the builder key, the client (key, once, query match, adapter, other requests, nothing carried, installed once)
- [x] 5. Measure on the sandbox reference stack, before/after (numbers in the PR)
- [x] 6. e2e initial-state-first-loads.spec.ts covers the three scenarios (REQ-INIT-011; renumbered from REQ-INIT-009, which the kept-manifest requirement already holds): 8/8 live on the sandbox reference stack (NC 34.0.6, MariaDB, Redis, APCu, nginx + php-fpm), REQ-INIT-011 tests 6/6 with repeat-each 2, the first one seen to fail with the client's carried-read interceptor removed
