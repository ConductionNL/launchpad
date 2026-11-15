<?php

/**
 * ExportNotifier
 *
 * The notifications of failed scheduled exports (REQ-SCH-002, REQ-SCH-006,
 * design open question 3): the export's creator and every administrator
 * hear about a fire blocked by a disabled creator and about deliveries
 * that failed for good.
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

use DateTime;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Notification\IManager;
use Throwable;

/**
 * Queues the failure notification of an export.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
 */
class ExportNotifier {

	public const SUBJECT = 'scheduled_export_failed';

	/**
	 * Constructor.
	 *
	 * @param IManager      $notifications The notification manager.
	 * @param IGroupManager $groups        Finds the administrators.
	 */
	public function __construct(
		private readonly IManager $notifications,
		private readonly IGroupManager $groups,
	) {
	}//end __construct()

	/**
	 * Notify the creator (when enabled) and every administrator.
	 *
	 * @param array<string, mixed> $export The export.
	 * @param string               $reason What failed.
	 *
	 * @return list<string> The users notified.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	public function failed(array $export, string $reason): array {
		$users = [];
		$admin = $this->groups->get('admin');
		if ($admin !== null) {
			$enabled = array_filter($admin->getUsers(), static fn (IUser $user): bool => $user->isEnabled());
			$users   = array_map(static fn (IUser $user): string => $user->getUID(), $enabled);
		}

		$creator = (string)($export['createdBy'] ?? '');
		if ($creator !== '' && ($export['creatorEnabled'] ?? true) === true) {
			$users[] = $creator;
		}

		$users = array_values(array_unique($users));
		foreach ($users as $uid) {
			try {
				$notification = $this->notifications->createNotification();
				$notification->setApp('launchpad')
					->setUser($uid)
					->setDateTime(new DateTime())
					->setObject('scheduled_export', (string)($export['id'] ?? ''))
					->setSubject(self::SUBJECT, [(string)($export['name'] ?? ''), $reason]);
				$this->notifications->notify($notification);
			} catch (Throwable) {
				// A notification that cannot be queued must not fail the run.
				continue;
			}
		}

		return $users;
	}//end failed()
}//end class
