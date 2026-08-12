<?php

/**
 * ShareExpiredException
 *
 * Thrown when the public share's expiresAt timestamp is in the past.
 * Maps to HTTP 404 per REQ-PSHR-008 (no leaking of expiry vs non-existence).
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
 * Public share has passed its expiry date.
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-4
 */
class ShareExpiredException extends ResourceException {

	/**
	 * Stable error code.
	 *
	 * @var string
	 */
	protected string $errorCode = 'share_expired';

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
	public function __construct(string $message = 'Share not found') {
		parent::__construct(message: $message);
	}//end __construct()
}//end class
