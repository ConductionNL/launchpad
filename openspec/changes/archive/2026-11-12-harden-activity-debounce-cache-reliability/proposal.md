---
kind: code
---

# Replace `DebounceHelper`'s per-request in-memory fallback with Nextcloud's distributed cache

## Why

`lib/Activity/DebounceHelper.php` backs the 900-second debounce windows
required by REQ-ACT-007 ("at most one reaction activity row per actor per
dashboard per 900-second window") and REQ-ACT-008 (per-dashboard,
per-event-type fan-out debounce), documented in
`openspec/specs/activity-feed-integration/spec.md` lines 233 and 267. Both
requirements are worded as unconditional MUSTs with no "when APCu is
available" qualifier.

The implementation's primary backend is APCu (`apcu_add()`, line 150).
When APCu is unusable — `apcuUsable()` at lines 186-204 returns `false`
whenever `apcu_add`/`apcu_exists` don't exist, or `apcu_enabled()`/
`apc.enabled` report disabled — `claim()` (lines 142-167) falls back to a
**per-instance PHP array**, `private array $memory = []` (line 53).

`DebounceHelper` is registered in `lib/AppInfo/Application.php` (lines
98-107) as what the inline comment calls a "shared singleton" via a DI
container factory (`new DebounceHelper()`). In Nextcloud's standard
PHP-FPM deployment model, the DI container itself is rebuilt from scratch
on every HTTP request — there is no cross-request PHP object persistence
without APCu (or another out-of-process store). "Shared singleton" here
only means *one instance per request*, not one instance across requests.
Consequently, on any Nextcloud install where the APCu extension is not
installed, or where `apc.enable_cli=1`/`apc.enabled=0` in the relevant
SAPI, every single `claim()` call across every request starts from an
empty `$memory` array and returns `true` — the debounce guarantee
silently degrades to "always allow," not merely "allow once per
worker." A burst of 5 rapid reactions from the same actor within the
900-second window (the exact scenario `openspec/specs/activity-feed-integration/spec.md`
line 245 requires to be debounced to one) would instead write 5 rows.

This is the same defect class ADR-029 documents for `FileLockHandler`
("Per-instance state not persisted... Per-request unit tests are green;
real PHP-FPM workers see an empty map after each request boundary")
— caught there by moving the private array to `ICache`. `DebounceHelper`
has not had the equivalent fix.

## What Changes

- Replace the `private array $memory` fallback in
  `lib/Activity/DebounceHelper.php` with `OCP\ICache` obtained from
  `OCP\ICacheFactory::createDistributed('launchpad_activity_debounce')`,
  injected via the constructor, so the debounce claim survives across
  requests and PHP-FPM workers regardless of APCu availability.
- Keep the existing `apcu_add()` fast path when APCu genuinely is usable
  (no behavioural change there), but make `ICache` the fallback instead of
  a per-request array — Nextcloud's distributed cache abstraction already
  selects an appropriate backend (Redis/Memcached/APCu/file) per instance
  config, so this closes the gap without LaunchPad needing to detect the
  backend itself.
- Keep the test-clock branch (`realClock === false`) using the in-memory
  array exactly as today — deterministic unit tests do not exercise
  cross-request persistence and should not depend on a real cache backend.
- Update `lib/AppInfo/Application.php`'s DI factory for `DebounceHelper` to
  inject the cache factory.
- **BREAKING**: none — this only changes the storage backend for a
  best-effort debounce guard; no public API or route changes.

## Capabilities

### Modified Capabilities

- `activity-feed-integration`: strengthens REQ-ACT-007/REQ-ACT-008 so the
  debounce guarantee holds on every deployment topology, not only ones
  where APCu happens to be installed and enabled.

## Impact

**Affected code:**

- `lib/Activity/DebounceHelper.php` (constructor, `claim()`,
  `apcuUsable()`)
- `lib/AppInfo/Application.php` (lines 98-107, the `DebounceHelper` DI
  factory registration)

**Affected APIs:** none — internal service only.

**Dependencies:** `OCP\ICacheFactory` (Nextcloud core, no new external
dependency).
