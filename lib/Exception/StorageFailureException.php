<?php

/**
 * StorageFailureException
 *
 * Raised when writing the validated bytes to the IAppData folder
 * fails for reasons unrelated to validation (disk full, permission
 * denied, etc.). Maps to HTTP 500 + error code `storage_failure`.
 *
 * @category  Exception
 * @package   OCA\LaunchPad\Exception
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
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
 * Persisting the resource to app data failed.
 */
class StorageFailureException extends ResourceException {

	/**
	 * Stable error code.
	 *
	 * @var string
	 */
	protected string $errorCode = 'storage_failure';

	/**
	 * HTTP status.
	 *
	 * @var integer
	 */
	protected int $httpStatus = 500;

	/**
	 * Constructor.
	 *
	 * @param string $message Display message.
	 */
	public function __construct(
		string $message = 'Failed to store resource',
	) {
		parent::__construct(message: $message);
	}//end __construct()
}//end class
