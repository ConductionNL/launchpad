# Tasks: shell-boot-reads-from-initial-state

- [x] 1. A builder for `setup_status` (the status document for an admin, `{forbidden: true}` for a caller the endpoint refuses, nothing for a delegated admin or on failure) and `walkthrough_preferences` (`{seen, progress}` as stored)
- [x] 2. The Workspace page carries both keys, built by that builder
- [x] 3. Unit tests: the builder (admin, refused caller, delegated admin, failing read, no user, stored and unset preferences, the key the preference endpoint stores) and the builder keys
- [ ] 4. Raise `@conduction/nextcloud-vue` to the first release carrying nextcloud-vue#2378 (it reads both keys); until then the keys are carried and unused
- [ ] 5. Measure on a live instance: the Workspace opens without `/api/setup/status` and the two walkthrough preference requests (perf spec, before/after)
