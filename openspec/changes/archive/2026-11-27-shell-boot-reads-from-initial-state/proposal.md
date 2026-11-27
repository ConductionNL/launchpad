# Proposal: shell-boot-reads-from-initial-state

## Why

The live measure of 25 Nov 2026 (PERF-HANDOFF launchpad, lever 1) found the
Workspace usable in ~1.9 s against a 300 ms budget. Each launchpad API answers
in 50-70 ms, but requests of one session are served one at a time, so every
boot read adds to the wait. `GET /api/setup/status` was asked twice per load
and the walkthrough asked two preferences (`walkthrough_completed_version` and
its `-progress` twin) on every load.

## What changes

- nextcloud-vue (#2378) reads the setup status once per app, and takes
  `setup_status` and `walkthrough_preferences` from the app's initial state
  when the page carries them.
- The Workspace page carries both: `setup_status` is the document
  `GET /api/setup/status` answers for an admin, or `{forbidden: true}` for a
  caller the endpoint refuses; a delegated LaunchPad admin gets no key and asks
  the endpoint. `walkthrough_preferences` is `{seen, progress}`, the stored
  values `GET /api/preferences/{key}` answers.
- `GET /api/setup/status` and the preference endpoints are unchanged.
