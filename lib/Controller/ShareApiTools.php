<?php

/**
 * ShareApiTools
 *
 * The sharee directories and the logger of the dashboard share API.
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
 * @spec openspec/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCP\IGroupManager;
use OCP\IUserManager;
use OCA\LaunchPad\Service\SharingLimits;
use Psr\Log\LoggerInterface;

/**
 * The sharee directories and the logger of the dashboard share API.
 *
 * @spec openspec/specs/dashboard-sharing/spec.md
 */
class ShareApiTools {
	/**
	 * Constructor.
	 *
	 * @param IUserManager    $userManager  Nextcloud user manager (sharee lookup).
	 * @param IGroupManager   $groupManager Nextcloud group manager (sharee lookup).
	 * @param LoggerInterface $logger       PSR logger.
	 * @param SharingLimits|null $limits   Who may share and with whom; the sharee search offers only allowed recipients.
	 */
	public function __construct(
		public readonly IUserManager $userManager,
		public readonly IGroupManager $groupManager,
		public readonly LoggerInterface $logger,
		public readonly ?SharingLimits $limits = null,
	) {
	}//end __construct()
}//end class
