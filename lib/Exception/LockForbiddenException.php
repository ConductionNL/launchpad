<?php

/**
 * LockForbiddenException
 *
 * Raised by {@see \OCA\LaunchPad\Service\DashboardLockService} when the
 * caller attempts an owner-restricted action (heartbeat, release,
 * force-release) without the required ownership or admin role. Maps to
 * HTTP 403 (REQ-LOCK-002, REQ-LOCK-003, REQ-LOCK-006).
 *
 * @category  Exception
 * @package   OCA\LaunchPad\Exception
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Exception;

use Exception;

/**
 * Lock operation refused — caller is neither owner nor admin
 * (REQ-LOCK-002, REQ-LOCK-003, REQ-LOCK-006).
 */
class LockForbiddenException extends Exception {
	/**
	 * Stable error code returned in the response envelope.
	 *
	 * @var string
	 */
	public const ERROR_CODE = 'lock_forbidden';
}//end class
