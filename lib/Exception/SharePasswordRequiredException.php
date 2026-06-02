<?php

/**
 * SharePasswordRequiredException
 *
 * Thrown when a password-protected share is accessed without supplying the
 * correct password. Maps to HTTP 401 with body {passwordRequired: true}.
 *
 * @category  Exception
 * @package   OCA\MyDash\Exception
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

namespace OCA\MyDash\Exception;

/**
 * Password required to access this public share.
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-4
 */
class SharePasswordRequiredException extends ResourceException
{

    /**
     * Stable error code.
     *
     * @var string
     */
    protected string $errorCode = 'share_password_required';

    /**
     * HTTP status.
     *
     * @var integer
     */
    protected int $httpStatus = 401;

    /**
     * Constructor.
     *
     * @param string $message Display message.
     */
    public function __construct(string $message='Password required')
    {
        parent::__construct(message: $message);
    }//end __construct()
}//end class
