# Tasks: workspace-api-reads-from-initial-state

- [x] 1. A builder that answers the Workspace's first API reads in-process through their controller actions (announcements, dismissed notices, the active dashboard's reactions, its personal layer when somebody else owns it, the first page of up to four visible Files widgets), leaving out a read that is refused, fails or is not 200
- [x] 2. The workspace page carries `workspaceApiReads`, built by that builder
- [x] 3. The app's HTTP client answers the first matching GET of each carried read from the page, once; every other request goes out
- [x] 4. Unit tests: the builder (all reads, own dashboard, refused and failing read, anonymous, Files widget cap), the builder key, the client (key, once, query match, adapter, other requests, nothing carried, installed once)
- [x] 5. Measure on the sandbox reference stack, before/after (numbers in the PR)
