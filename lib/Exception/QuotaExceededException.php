<?php

/**
 * QuotaExceededException
 *
 * Raised when a user-initiated creation would exceed an admin-configured
 * governance quota (maximum personal dashboards per user, or maximum
 * widget placements per dashboard). Carries the quota kind, the configured
 * limit, and the current count so the controller can render the structured
 * HTTP 409 body
 * `{"error": "quota_exceeded", "quota": <kind>, "limit": N, "current": N}`.
 *
 * @category  Exception
 * @package   OCA\LaunchPad\Exception
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Exception;

/**
 * Quota ceiling reached for a user-initiated creation
 * (dashboard-quota-limits REQ-QUOTA-002 / REQ-QUOTA-003). Maps to HTTP 409
 * with a stable, machine-readable body.
 */
class QuotaExceededException extends ResourceException
{

    /**
     * Quota kind: per-user personal dashboard count.
     *
     * @var string
     */
    public const QUOTA_DASHBOARDS = 'dashboards';

    /**
     * Quota kind: per-dashboard widget placement count.
     *
     * @var string
     */
    public const QUOTA_WIDGETS = 'widgets';

    /**
     * Stable error code returned in the response envelope.
     *
     * @var string
     */
    protected string $errorCode = 'quota_exceeded';

    /**
     * HTTP status code — 409 Conflict: the request conflicts with the
     * current count-based state of the resource (dashboard-quota-limits
     * design D4).
     *
     * @var integer
     */
    protected int $httpStatus = 409;

    /**
     * The quota kind (`dashboards` or `widgets`).
     *
     * @var string
     */
    private string $quota;

    /**
     * The configured limit that was reached.
     *
     * @var integer
     */
    private int $limit;

    /**
     * The current count at the time of rejection.
     *
     * @var integer
     */
    private int $current;

    /**
     * Constructor.
     *
     * @param string $quota   The quota kind (`dashboards` | `widgets`).
     * @param int    $limit   The configured limit.
     * @param int    $current The current count.
     * @param string $message Display message (translatable English string).
     */
    public function __construct(
        string $quota,
        int $limit,
        int $current,
        string $message='Quota limit reached'
    ) {
        $this->quota   = $quota;
        $this->limit   = $limit;
        $this->current = $current;
        parent::__construct(message: $message);
    }//end __construct()

    /**
     * Get the quota kind.
     *
     * @return string The quota kind (`dashboards` | `widgets`).
     */
    public function getQuota(): string
    {
        return $this->quota;
    }//end getQuota()

    /**
     * Get the configured limit.
     *
     * @return int The limit.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }//end getLimit()

    /**
     * Get the current count.
     *
     * @return int The current count.
     */
    public function getCurrent(): int
    {
        return $this->current;
    }//end getCurrent()

    /**
     * Build the machine-readable response body for the HTTP 409
     * (dashboard-quota-limits REQ-QUOTA-002 / REQ-QUOTA-003).
     *
     * @return array{error: string, quota: string, limit: int, current: int}
     *   The structured error body.
     */
    public function toResponseBody(): array
    {
        return [
            'error'   => $this->errorCode,
            'quota'   => $this->quota,
            'limit'   => $this->limit,
            'current' => $this->current,
        ];
    }//end toResponseBody()
}//end class
