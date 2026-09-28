<?php

/**
 * FileAlreadyExistsException
 *
 * Raised when a create call asks not to overwrite (`overwrite: false`)
 * and a file with that name already exists in the target folder, so the
 * UI can warn before replacing it (issue #712, REQ-LBN-004).
 * Maps to HTTP 409 + error code `file_exists`.
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
 * A file with the requested name already exists.
 */
class FileAlreadyExistsException extends ResourceException {

	/**
	 * Stable error code.
	 *
	 * @var string
	 */
	protected string $errorCode = 'file_exists';

	/**
	 * HTTP status.
	 *
	 * @var integer
	 */
	protected int $httpStatus = 409;

	/**
	 * Constructor.
	 *
	 * @param string $message Display message.
	 */
	public function __construct(
		string $message = 'A file with this name already exists',
	) {
		parent::__construct(message: $message);
	}//end __construct()
}//end class
