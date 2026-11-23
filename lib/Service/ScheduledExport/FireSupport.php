<?php

/**
 * FireSupport
 *
 * The collaborators of a fire besides render, dispatch and audit: the retry
 * policy, the failure notifier, the user manager (is the creator still
 * enabled?), Files (artefacts read back for a retry) and the clock.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

use OCA\LaunchPad\Service\RetryHandler;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\IUserManager;

/**
 * Retry policy, notifier, users, Files and clock for a fire.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-8
 */
final class FireSupport {

	/**
	 * Constructor.
	 *
	 * @param RetryHandler   $retry      The retry policy.
	 * @param ExportNotifier $notifier   Failure notifications.
	 * @param IUserManager   $users      Whether the creator is enabled.
	 * @param IRootFolder    $rootFolder Artefacts read back for a retry.
	 * @param ITimeFactory   $time       The clock.
	 */
	public function __construct(
		public readonly RetryHandler $retry,
		public readonly ExportNotifier $notifier,
		public readonly IUserManager $users,
		public readonly IRootFolder $rootFolder,
		public readonly ITimeFactory $time,
	) {
	}//end __construct()
}//end class
