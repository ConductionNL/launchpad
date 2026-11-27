# Tasks: shell-boot-reads-from-initial-state

- [x] 1. A builder for `setup_status` (the status document for an admin, `{forbidden: true}` for a caller the endpoint refuses, nothing for a delegated admin or on failure) and `walkthrough_preferences` (`{seen, progress}` as stored)
- [x] 2. The Workspace page carries both keys, built by that builder
- [x] 3. Unit tests: the builder (admin, refused caller, delegated admin, failing read, no user, stored and unset preferences, the key the preference endpoint stores) and the builder keys
- [x] 4. Raise `@conduction/nextcloud-vue` to the first release carrying nextcloud-vue#2378 (it reads both keys); until then the keys are carried and unused
  - Done (cloud, 2026-11-27): raised to `^2.233.0-beta.1`, the first published version carrying #2378 (its `readPreloadedSetupStatus()` reads `setup_status` from initial state; 2.232.2, npm latest, has neither key). vitest 4209/4209, eslint 0 errors, production build compiles. Move to 2.233.0 when it is published as latest.
- [ ] 5. Measure on a live instance: the Workspace opens without `/api/setup/status` and the two walkthrough preference requests (perf spec, before/after)
