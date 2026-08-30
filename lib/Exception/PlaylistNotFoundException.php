<?php

/**
 * PlaylistNotFoundException
 *
 * Thrown when a kiosk playlist token does not exist or has been revoked.
 * Always maps to HTTP 404 — never leaks whether the token ever existed,
 * matching the dashboard-public-share 404 semantics (REQ-PSHR-008).
 *
 * @category  Exception
 * @package   OCA\LaunchPad\Exception
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-3
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Exception;

/**
 * Kiosk playlist not found (or revoked).
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-3
 */
class PlaylistNotFoundException extends ResourceException {

	/**
	 * Stable error code.
	 *
	 * @var string
	 */
	protected string $errorCode = 'playlist_not_found';

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
	public function __construct(string $message = 'Not found') {
		parent::__construct(message: $message);
	}//end __construct()
}//end class
