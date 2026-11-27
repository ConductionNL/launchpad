# Tasks: dashboard-switch-reads-early

- [x] 1. The app's HTTP client keeps early GETs in flight (5 s, once) and answers the first matching GET from one; a later or expired GET goes out; the early GET itself is never answered from its own entry
- [x] 2. `switchDashboard()` starts the target's reactions read and, when the target's row says the caller does not own it, its personal-layer read, beside the dashboard read, after dropping the previous switch's early reads
- [x] 3. Unit tests: the client (answered once, expiry, failure answers with the error, dropped on the next switch, own request not self-answered, non-GET untouched) and the store (reads started for a shared and an owned dashboard, none without a uuid)
- [ ] 4. Measure on the sandbox reference stack, before/after (live; numbers in the PR or the nightly)
