<?php

/**
 * MirrorSharedDashboards
 *
 * Gives every dashboard that was shared, or was a group dashboard, before
 * the discussion objects existed its object in OpenRegister. Idempotent:
 * a mirror in step is written unchanged. Does nothing without OpenRegister.
 *
 * @category  Repair
 * @package   OCA\LaunchPad\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-4
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use OCA\LaunchPad\Service\Discussion\DashboardDiscussionMirror;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Syncs the discussion object of every shared and group dashboard.
 */
class MirrorSharedDashboards implements IRepairStep {

	/**
	 * Constructor.
	 *
	 * @param DashboardDiscussionMirror $mirror Writes the discussion objects.
	 */
	public function __construct(
		private readonly DashboardDiscussionMirror $mirror,
	) {
	}//end __construct()

	/**
	 * The step's name.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-4
	 */
	public function getName(): string {
		return 'Mirror shared LaunchPad dashboards in OpenRegister for their discussions';
	}//end getName()

	/**
	 * Sync every shared and group dashboard.
	 *
	 * @param IOutput $output The repair output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-4
	 */
	public function run(IOutput $output): void {
		$output->info('LaunchPad: '.$this->mirror->syncAllShared().' shared dashboard(s) checked for a discussion object.');
	}//end run()
}//end class
