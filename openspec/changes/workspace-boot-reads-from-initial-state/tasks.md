# Tasks: workspace-boot-reads-from-initial-state

- [x] 1. A builder for `{tiles, pendingAcknowledgements, defaultDashboard}` with the payloads of the three endpoints, leaving out a part the action matrix refuses or that fails
- [x] 2. The workspace page carries `workspaceBootReads`, built by that builder
- [x] 3. The tile store, the pending-acknowledgements read and the default-dashboard read take their part once; without it they fetch
- [x] 4. Unit tests: the builder (all parts, refused part, failing part, nothing left), the builder key, the reader (once per part, missing) and the three readers (no fetch from the part, fetch without it)
- [ ] 5. Measure on a live instance: the workspace opens without the three requests (perf spec, before/after)
