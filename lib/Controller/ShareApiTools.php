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

use OCA\LaunchPad\Event\DashboardAudienceChangedEvent;
use OCP\EventDispatcher\IEventDispatcher;
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
	 * @param IEventDispatcher|null $events Announces a changed dashboard audience (openregister-leaf-integrations).
	 */
	public function __construct(
		public readonly IUserManager $userManager,
		public readonly IGroupManager $groupManager,
		public readonly LoggerInterface $logger,
		public readonly ?SharingLimits $limits = null,
		public readonly ?IEventDispatcher $events = null,
	) {
	}//end __construct()

	/**
	 * Announce that a share change altered who may view a dashboard, or the
	 * dashboards of an owner, so the dashboard's discussion object follows it
	 * (openregister-leaf-integrations).
	 *
	 * @param int|null    $dashboardId The dashboard, when one is known.
	 * @param string|null $ownerId     The owner whose dashboards it can reach.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-8
	 */
	public function audienceChanged(?int $dashboardId = null, ?string $ownerId = null): void {
		$this->events?->dispatchTyped(new DashboardAudienceChangedEvent(dashboardId: $dashboardId, ownerId: $ownerId));
	}//end audienceChanged()
}//end class
