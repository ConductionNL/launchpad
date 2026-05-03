<?php

/**
 * PermissionDeniedException
 *
 * Raised by {@see BulkOperationService} when the all-or-nothing
 * permission pre-check finds at least one unauthorised dashboard in a
 * batch (REQ-BULK-011) — or when the caller is not a Nextcloud admin.
 * The controller maps this to HTTP 403 with the offending UUID list
 * in the response body.
 *
 * @category  Service
 * @package   OCA\MyDash\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MyDash\Service;

use Exception;

/**
 * Permission failure during a bulk operation pre-check (REQ-BULK-011).
 */
class PermissionDeniedException extends Exception
{
    /**
     * Constructor.
     *
     * @param string   $message     The error message surfaced to the
     *                              caller.
     * @param string[] $deniedUuids The UUIDs the caller could not act on.
     *                              Empty when the caller is not an admin
     *                              at all (no per-uuid breakdown applies).
     */
    public function __construct(
        string $message,
        private readonly array $deniedUuids=[]
    ) {
        parent::__construct(message: $message);
    }//end __construct()

    /**
     * The UUIDs that caused the permission denial.
     *
     * @return string[] The denied UUID list.
     */
    public function getDeniedUuids(): array
    {
        return $this->deniedUuids;
    }//end getDeniedUuids()
}//end class
