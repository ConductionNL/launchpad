# Tasks — harden-activity-debounce-cache-reliability

## DebounceHelper

- [ ] Task 1: In `lib/Activity/DebounceHelper.php`, add a constructor
  parameter `private readonly ?\OCP\ICache $cache = null` (nullable so
  existing unit-test call sites that construct `new DebounceHelper($clock)`
  keep working), alongside the existing `?callable $clock` parameter.
- [ ] Task 2: In `claim()` (currently lines 142-167), when
  `$this->realClock === true` and APCu is unusable
  (`apcuUsable() === false`) and `$this->cache !== null`, use
  `$this->cache->add($key, $now, self::TTL_SECONDS)`-equivalent semantics:
  `OCP\ICache` doesn't expose an atomic `add()`, so implement the claim as
  `if ($this->cache->hasKey($key)) { return false; } $this->cache->set($key, $now, self::TTL_SECONDS); return true;`
  and document the small race window this introduces (acceptable for a
  15-minute debounce UX guard, not a security control).
- [ ] Task 3: Keep the existing `$this->memory` array path as the final
  fallback only when `$this->cache === null` (defensive) or when
  `$this->realClock === false` (test-clock path, unchanged from today).
- [ ] Task 4: Update the class docblock (lines 14-17) and the `claim()`
  docblock (lines 132-141) to describe the three-tier fallback: APCu →
  `ICache` (distributed) → in-memory array (test-clock only).

## Application.php wiring

- [ ] Task 5: In `lib/AppInfo/Application.php` (lines 98-107), change the
  `DebounceHelper` factory to resolve `OCP\ICacheFactory` from the
  container and call `createDistributed('launchpad_activity_debounce')`,
  passing the resulting `ICache` into the `DebounceHelper` constructor:
  `factory: static fn(\Psr\Container\ContainerInterface $c): DebounceHelper => new DebounceHelper(cache: $c->get(\OCP\ICacheFactory::class)->createDistributed('launchpad_activity_debounce'))`
  (adjust to match the existing container-callback signature used by
  neighbouring registrations in the same file).
- [ ] Task 6: Update the inline comment at line 98 ("register
  DebounceHelper as a shared singleton") to also note that the shared
  distributed cache — not the PHP singleton — is what makes the debounce
  guarantee hold across requests.

## Tests

- [ ] Task 7: Add a PHPUnit test that constructs `DebounceHelper` with a
  fake `ICache` implementation (no APCu, real clock) and asserts that
  `allowReaction()` called twice in immediate succession for the same
  `(actor, dashboard)` pair returns `true` then `false` — i.e. the
  distributed-cache path is exercised, not just the in-memory fallback.
- [ ] Task 8: Add a regression test that simulates "APCu absent, cache
  absent" (both `apcuUsable()` false and `$cache === null`) and asserts the
  helper still falls back to the in-memory array without throwing, to
  confirm the defensive tier is not a hard dependency.
- [ ] Task 9: Re-run the existing `activity-feed-integration` PHPUnit
  suite referenced by `openspec/specs/activity-feed-integration/spec.md`
  to confirm REQ-ACT-007/REQ-ACT-008 scenarios still pass unchanged.
