<?php

/**
 * DebounceHelper
 *
 * APCu-backed debounce guard used by the Activity Feed Integration
 * capability (REQ-ACT-007, REQ-ACT-008). Two windows are exposed:
 *
 * - `allowReaction(actor, dashboard)` — at most one reaction per actor
 *   per dashboard per 900-second window.
 * - `allowGlobalFanout(dashboard, eventType)` — at most one default-group
 *   fan-out per dashboard per event type per 900-second window.
 *
 * The claim is resolved through a three-tier fallback so the debounce
 * guarantee holds across requests and PHP-FPM workers on every
 * deployment topology (not only ones where APCu happens to be installed
 * and enabled):
 *
 * 1. APCu (`apcu_add`) — the race-free fast path when APCu is usable.
 * 2. `OCP\ICache` (distributed) — the cross-request fallback when APCu
 *    is unusable. Nextcloud's distributed cache abstraction selects an
 *    appropriate backend (Redis/Memcached/APCu/file) per instance
 *    config, so the debounce survives the per-request rebuild of the DI
 *    container that PHP-FPM performs. `ICache` has no atomic `add()`, so
 *    the claim is a `hasKey()`-then-`set()` — a small race window that is
 *    acceptable for a 15-minute UX debounce guard (not a security
 *    control).
 * 3. In-memory array — the final fallback used ONLY when no cache is
 *    injected (defensive) or when a test clock is active (the
 *    deterministic unit-test path, where a real cache backend's
 *    wall-clock TTL would not move with the fake clock).
 *
 * @category  Activity
 * @package   OCA\LaunchPad\Activity
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Activity;

use OCP\ICache;

/**
 * Per-window debounce guard for Activity emission.
 */
class DebounceHelper
{
    /**
     * Debounce TTL in seconds (15 minutes). REQ-ACT-007, REQ-ACT-008.
     */
    public const TTL_SECONDS = 900;

    /**
     * In-memory fallback store used when APCu is not available.
     *
     * Keyed by the same APCu key string; the value is the unix
     * timestamp at which the entry expires.
     *
     * @var array<string, int>
     */
    private array $memory = [];

    /**
     * Optional clock callable returning the current unix timestamp.
     *
     * Injected by tests to advance time deterministically without
     * sleeping for 15 minutes.
     *
     * @var callable():int
     */
    private $clock;

    /**
     * True when the helper is using the real wall-clock and may delegate
     * to APCu. False when a test clock is injected — in that case APCu's
     * own TTL would not move with the test clock, so the in-memory
     * fallback is the only correct backend.
     *
     * @var boolean
     */
    private bool $realClock;

    /**
     * Distributed cache used as the cross-request fallback when APCu is
     * not usable. Nullable so existing unit-test call sites that build
     * `new DebounceHelper($clock)` keep working without a cache backend.
     *
     * @var ICache|null
     */
    private ?ICache $cache;

    /**
     * Constructor.
     *
     * @param (callable():int)|null $clock Optional clock callable; defaults to `time()`.
     * @param ICache|null           $cache Optional distributed cache used as the
     *                                     cross-request fallback when APCu is
     *                                     unusable. Null falls back to the
     *                                     in-memory array.
     */
    public function __construct(?callable $clock=null, ?ICache $cache=null)
    {
        $this->realClock = ($clock === null);
        $this->clock     = ($clock ?? static fn(): int => time());
        $this->cache     = $cache;
    }//end __construct()

    /**
     * Check whether a reaction event from `$actorUserId` on
     * `$dashboardUuid` may be emitted now.
     *
     * Returns true on the first call and again after the 900-second
     * window has elapsed; returns false for any call inside an active
     * window.
     *
     * @param string $actorUserId   The acting user ID.
     * @param string $dashboardUuid The dashboard UUID.
     *
     * @return bool True when emission is allowed.
     */
    public function allowReaction(
        string $actorUserId,
        string $dashboardUuid
    ): bool {
        $key = sprintf(
            'launchpad_act_react_%s_%s',
            $actorUserId,
            $dashboardUuid
        );
        return $this->claim(key: $key);
    }//end allowReaction()

    /**
     * Check whether a default-group fan-out for `(dashboardUuid, eventType)`
     * may be performed now (REQ-ACT-008).
     *
     * @param string $dashboardUuid The dashboard UUID.
     * @param string $eventType     The event type constant value.
     *
     * @return bool True when fan-out is allowed.
     */
    public function allowGlobalFanout(
        string $dashboardUuid,
        string $eventType
    ): bool {
        $key = sprintf(
            'launchpad_act_global_%s_%s',
            $dashboardUuid,
            $eventType
        );
        return $this->claim(key: $key);
    }//end allowGlobalFanout()

    /**
     * Claim the key for the configured TTL.
     *
     * Three-tier fallback:
     *  1. APCu (`apcu_add`) — race-free claim, TTL enforced by APCu.
     *  2. `ICache` (distributed) — cross-request fallback when APCu is
     *     unusable and a cache backend is injected. Implemented as
     *     `hasKey()`-then-`set()` because `ICache` exposes no atomic
     *     `add()`; the resulting small race window is acceptable for a
     *     15-minute UX debounce guard (it is not a security control).
     *  3. In-memory array — only when no cache is injected (defensive)
     *     or under a test clock (deterministic unit-test path).
     *
     * @param string $key The full cache key.
     *
     * @return bool True when the caller successfully claimed the window.
     */
    private function claim(string $key): bool
    {
        $now = ($this->clock)();

        if ($this->apcuUsable() === true) {
            // `apcu_add` returns false when the key already exists,
            // which is exactly the semantics we want for a debounce
            // claim. The TTL is enforced by APCu itself.
            return (bool) apcu_add($key, $now, self::TTL_SECONDS);
        }

        // APCu is unusable. When a real clock is in effect and a
        // distributed cache is injected, use it so the debounce claim
        // survives across requests and PHP-FPM workers regardless of
        // APCu availability. The test-clock path deliberately skips the
        // cache (its wall-clock TTL cannot follow a fake clock).
        if ($this->realClock === true && $this->cache !== null) {
            if ($this->cache->hasKey($key) === true) {
                return false;
            }

            $this->cache->set($key, $now, self::TTL_SECONDS);
            return true;
        }

        // Purge expired entries opportunistically to keep the in-memory
        // store from growing without bound.
        foreach ($this->memory as $existingKey => $expiresAt) {
            if ($expiresAt <= $now) {
                unset($this->memory[$existingKey]);
            }
        }

        if (array_key_exists(key: $key, array: $this->memory) === true) {
            return false;
        }

        $this->memory[$key] = ($now + self::TTL_SECONDS);
        return true;
    }//end claim()

    /**
     * True when APCu is actually usable for debounce claims.
     *
     * `function_exists('apcu_add')` alone is not enough — the function
     * is loaded by the extension even when APCu is disabled at runtime
     * (most notably under CLI when `apc.enable_cli=0`), in which case
     * `apcu_add()` silently returns false on every call. That breaks
     * the debounce semantics: the helper would treat every claim as
     * "already taken" and reject every emission.
     *
     * `apcu_enabled()` was introduced in APCu 4.0.5 specifically for
     * this gate; when it's available we trust it. When it isn't (very
     * old APCu builds), fall back to the existence check + an
     * `ini_get('apc.enabled')` probe.
     *
     * @return bool True when APCu is loaded AND enabled at runtime.
     */
    private function apcuUsable(): bool
    {
        // A test-injected clock cannot move APCu's wall-clock TTL, so
        // the in-memory store is the only backend that produces
        // deterministic results when the clock is fake.
        if ($this->realClock === false) {
            return false;
        }

        if (function_exists(function: 'apcu_add') === false || function_exists(function: 'apcu_exists') === false) {
            return false;
        }

        if (function_exists(function: 'apcu_enabled') === true) {
            return (bool) apcu_enabled();
        }

        return (bool) ini_get(option: 'apc.enabled');
    }//end apcuUsable()
}//end class
