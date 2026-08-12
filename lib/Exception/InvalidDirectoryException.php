<?php

/**
 * InvalidDirectoryException
 *
 * Raised when the supplied target directory for `POST /api/files/create`
 * contains a path-traversal sequence (`..`) or a null byte. Maps to
 * HTTP 400 + error code `invalid_directory`.
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

/**
 * Directory failed strict validation (path-traversal or null byte).
 */
class InvalidDirectoryException extends ResourceException {

	/**
	 * Stable error code.
	 *
	 * @var string
	 */
	protected string $errorCode = 'invalid_directory';

	/**
	 * HTTP status.
	 *
	 * @var integer
	 */
	protected int $httpStatus = 400;

	/**
	 * Constructor.
	 *
	 * @param string $message Display message.
	 */
	public function __construct(string $message = 'Invalid directory') {
		parent::__construct(message: $message);
	}//end __construct()
}//end class
