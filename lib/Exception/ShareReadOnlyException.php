<?php

/**
 * ShareReadOnlyException
 *
 * Thrown when a mutation is attempted via a public-share bearer context.
 * Maps to HTTP 403 with message "Cannot modify dashboard via public share".
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
 * Dashboard mutation blocked — caller is a public-share bearer.
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-4
 */
class ShareReadOnlyException extends ResourceException {

	/**
	 * Stable error code.
	 *
	 * @var string
	 */
	protected string $errorCode = 'cannot_modify_public_share';

	/**
	 * HTTP status.
	 *
	 * @var integer
	 */
	protected int $httpStatus = 403;

	/**
	 * Constructor.
	 *
	 * @param string $message Display message.
	 */
	public function __construct(string $message = 'Cannot modify dashboard via public share') {
		parent::__construct(message: $message);
	}//end __construct()
}//end class
