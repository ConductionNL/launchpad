<?php

/**
 * SessionLog
 *
 * The session and the logger of a widget API.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/service-health-ping/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * The session and the logger of a widget API.
 *
 * @spec openspec/specs/service-health-ping/spec.md
 */
class SessionLog {
	/**
	 * Constructor.
	 *
	 * @param IUserSession    $userSession Session accessor.
	 * @param LoggerInterface $logger PSR logger.
	 */
	public function __construct(
		public readonly IUserSession $userSession,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
