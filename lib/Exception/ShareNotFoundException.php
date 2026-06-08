<?php

/**
 * ShareNotFoundException
 *
 * Thrown when a public share token does not exist, is revoked, or has expired.
 * Always maps to HTTP 404 — never leaks whether the token ever existed.
 *
 * @category  Exception
 * @package   OCA\LaunchPad\Exception
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-4
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Exception;

/**
 * Public share not found (or revoked/expired).
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-4
 */
class ShareNotFoundException extends ResourceException
{

    /**
     * Stable error code.
     *
     * @var string
     */
    protected string $errorCode = 'share_not_found';

    /**
     * HTTP status.
     *
     * @var integer
     */
    protected int $httpStatus = 404;

    /**
     * Constructor.
     *
     * @param string $message Display message.
     */
    public function __construct(string $message='Share not found')
    {
        parent::__construct(message: $message);
    }//end __construct()
}//end class
