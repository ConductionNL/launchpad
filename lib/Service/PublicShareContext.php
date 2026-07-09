<?php

/**
 * PublicShareContext
 *
 * Request-scoped marker singleton that records whether the current PHP
 * request was authenticated via a public-share bearer token (i.e. one that
 * matches a row in `oc_launchpad_public_shares.token`). Mutation services
 * call `requireMutable()` to assert that the active request is NOT a
 * read-only bearer context and otherwise raise `ShareReadOnlyException`
 * (HTTP 403).
 *
 * Task-7 of the dashboard-public-share change. Pattern is intentionally
 * minimal — one boolean flag + token capture for audit. Set by
 * `PublicShareController::renderShare()` after `PublicShareService`
 * verifies the bearer; never set elsewhere. The DI container hands out the
 * SAME instance to every service in the same request (`shared: true` in
 * the registration), so the flag travels naturally without middleware
 * plumbing.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-7
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Exception\ShareReadOnlyException;

/**
 * Request-scoped public-share bearer marker.
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-7
 */
class PublicShareContext
{

    /**
     * Whether the current request is a public-share bearer.
     *
     * @var boolean
     */
    private bool $isBearer = false;

    /**
     * The bearer token (kept for audit logging only, never echoed back).
     *
     * @var string|null
     */
    private ?string $token = null;

    /**
     * Mark the current request as authenticated via a public-share bearer.
     *
     * Called exactly once by `PublicShareController::renderShare()` after
     * `PublicShareService::renderShareContent()` returns successfully.
     *
     * @param string $token The verified bearer token.
     *
     * @return void
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-7
     */
    public function markBearer(string $token): void
    {
        $this->isBearer = true;
        $this->token    = $token;
    }//end markBearer()

    /**
     * Whether the current request was authenticated via a public-share
     * bearer token (read-only context).
     *
     * @return bool True when the request is a public-share bearer.
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-7
     */
    public function isBearer(): bool
    {
        return $this->isBearer;
    }//end isBearer()

    /**
     * The verified bearer token, or null when not in a bearer context.
     *
     * @return string|null
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-7
     */
    public function getToken(): ?string
    {
        return $this->token;
    }//end getToken()

    /**
     * Guard a mutation path. Throws ShareReadOnlyException when the
     * current request is a public-share bearer.
     *
     * @return void
     *
     * @throws ShareReadOnlyException When the request is a bearer (HTTP 403).
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-7
     */
    public function requireMutable(): void
    {
        if ($this->isBearer === true) {
            throw new ShareReadOnlyException();
        }
    }//end requireMutable()
}//end class
