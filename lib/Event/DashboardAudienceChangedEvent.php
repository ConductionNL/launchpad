<?php

/**
 * DashboardAudienceChangedEvent
 *
 * Dispatched after something changed who may view a dashboard: a share was
 * added, changed or revoked, or a group dashboard was created. Names either
 * one dashboard or every dashboard of one owner (a revocation by recipient
 * can reach several). Listeners keep derived access in step, such as the
 * dashboard's discussion object in OpenRegister.
 *
 * @category  Event
 * @package   OCA\LaunchPad\Event
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/openregister-leaf-integrations/specs/dashboard-talk-leaf/spec.md#requirement-req-leaf-003-discussion-access-follows-the-existing-view-guard
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Event;

use OCP\EventDispatcher\Event;

/**
 * Who may view one dashboard, or the dashboards of one owner, changed.
 */
final class DashboardAudienceChangedEvent extends Event {

	/**
	 * Constructor.
	 *
	 * @param int|null    $dashboardId The dashboard whose audience changed, or null.
	 * @param string|null $ownerId     The owner whose dashboards' audience changed, or null.
	 */
	public function __construct(
		public readonly ?int $dashboardId = null,
		public readonly ?string $ownerId = null,
	) {
		parent::__construct();
	}//end __construct()
}//end class
